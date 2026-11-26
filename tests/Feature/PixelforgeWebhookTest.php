<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Models\WebhookEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PixelforgeWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected string $secret = 'whsec_test_secret_key_123';

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.pixelforge.webhook_secret' => $this->secret]);
    }

    /**
     * Helper to sign a webhook payload.
     */
    protected function signPayload(string $payload, ?int $timestamp = null): string
    {
        $timestamp = $timestamp ?? time();
        $signature = hash_hmac('sha256', "{$timestamp}.{$payload}", $this->secret);

        return "t={$timestamp},v1={$signature}";
    }

    public function test_it_verifies_signature_and_processes_succeeded_event(): void
    {
        $user = User::factory()->create();
        $idempotencyKey = (string) Str::uuid();

        $generation = Generation::create([
            'user_id' => $user->id,
            'pixelforge_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
            'idempotency_key' => $idempotencyKey,
            'status' => GenerationStatus::Processing,
            'prompt' => 'A lighthouse on a cliff',
            'size' => '1024x1024',
            'quality' => 'medium',
        ]);

        $payloadData = [
            'id' => 'evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A',
            'object' => 'event',
            'type' => 'generation.succeeded',
            'created_at' => '2026-11-04T14:03:29Z',
            'data' => [
                'object' => [
                    'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
                    'object' => 'generation',
                    'status' => 'succeeded',
                    'prompt' => 'A lighthouse on a cliff',
                    'size' => '1024x1024',
                    'quality' => 'medium',
                    'image_url' => 'https://pixelforge.promptedit.dev/images/gen_01.png',
                    'image_url_expires_at' => '2026-11-05T14:03:29Z',
                    'usage' => [
                        'input_text_tokens' => 10,
                        'input_image_tokens' => 0,
                        'output_tokens' => 1100,
                        'reasoning_tokens' => 300,
                        'total_tokens' => 1410,
                    ],
                    'rates' => [
                        'input_text_tokens' => '5.00',
                        'input_image_tokens' => '10.00',
                        'output_tokens' => '40.00',
                        'reasoning_tokens' => '12.50',
                    ],
                    'completed_at' => '2026-11-04T14:03:29Z',
                ],
            ],
        ];

        $rawPayload = json_encode($payloadData);
        $signature = $this->signPayload($rawPayload);

        $response = $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'HTTP_Pixelforge-Signature' => $signature,
                'HTTP_Pixelforge-Event-Id' => 'evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A',
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );

        $response->assertNoContent();

        $generation->refresh();
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
        $this->assertSame('https://pixelforge.promptedit.dev/images/gen_01.png', $generation->image_url);
        $this->assertNotNull($generation->actual_cost);
        $this->assertNotNull($generation->provider_cost);
        $this->assertDatabaseHas('webhook_events', [
            'event_id' => 'evt_01JC2Z9B7K2M4P6R8T0V3X5Z7A',
        ]);
    }

    public function test_it_deduplicates_duplicate_webhook_deliveries(): void
    {
        $user = User::factory()->create();
        $generation = Generation::create([
            'user_id' => $user->id,
            'pixelforge_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Queued,
            'prompt' => 'A test prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
        ]);

        $payloadData = [
            'id' => 'evt_duplicate_test_123',
            'object' => 'event',
            'type' => 'generation.processing',
            'created_at' => '2026-11-04T14:03:15Z',
            'data' => [
                'object' => [
                    'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
                    'status' => 'processing',
                ],
            ],
        ];

        $rawPayload = json_encode($payloadData);
        $signature = $this->signPayload($rawPayload);

        // First delivery
        $res1 = $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'HTTP_Pixelforge-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );
        $res1->assertNoContent();

        // Second delivery (duplicate)
        $res2 = $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'HTTP_Pixelforge-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );
        $res2->assertNoContent();

        // Only one event record stored
        $this->assertSame(1, WebhookEvent::where('event_id', 'evt_duplicate_test_123')->count());
    }

    public function test_out_of_order_processing_event_does_not_revert_succeeded_generation(): void
    {
        $user = User::factory()->create();
        $generation = Generation::create([
            'user_id' => $user->id,
            'pixelforge_id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Succeeded,
            'prompt' => 'A completed prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'actual_cost' => '0.050000000',
        ]);

        // A late generation.processing event arrives
        $payloadData = [
            'id' => 'evt_late_processing_999',
            'object' => 'event',
            'type' => 'generation.processing',
            'created_at' => '2026-11-04T14:03:10Z',
            'data' => [
                'object' => [
                    'id' => 'gen_01JC2Z8Q4N6V0M3H7X5T9R2B1K',
                    'status' => 'processing',
                ],
            ],
        ];

        $rawPayload = json_encode($payloadData);
        $signature = $this->signPayload($rawPayload);

        $res = $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'HTTP_Pixelforge-Signature' => $signature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );
        $res->assertNoContent();

        $generation->refresh();
        // Crucial: Must remain Succeeded!
        $this->assertSame(GenerationStatus::Succeeded, $generation->status);
    }

    public function test_it_rejects_invalid_signatures(): void
    {
        $rawPayload = json_encode(['id' => 'evt_fake', 'type' => 'ping']);
        $badSignature = 't=' . time() . ',v1=invalid_hmac_hash';

        $res = $this->call(
            'POST',
            '/webhooks/pixelforge',
            [],
            [],
            [],
            [
                'HTTP_Pixelforge-Signature' => $badSignature,
                'CONTENT_TYPE' => 'application/json',
            ],
            $rawPayload
        );

        $res->assertStatus(400);
    }
}
