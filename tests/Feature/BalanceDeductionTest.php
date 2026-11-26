<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\GenerationService;
use App\Services\PricingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class BalanceDeductionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_balance_is_deducted_when_generation_succeeds(): void
    {
        $user = User::factory()->create([
            'balance' => '10.00000000',
        ]);

        $generation = Generation::create([
            'user_id' => $user->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Processing,
            'prompt' => 'A lighthouse on a cliff',
            'size' => '1024x1024',
            'quality' => 'medium',
            'estimated_cost' => '0.05000000',
        ]);

        $service = app(GenerationService::class);

        // Simulation payload from Pixelforge webhook / poll
        $payload = [
            'id' => 'gen_test_deduct_123',
            'status' => 'succeeded',
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
            'image_url' => 'https://pixelforge.promptedit.dev/images/gen_01.png',
        ];

        $service->syncFromPayload($generation, $payload);

        $generation->refresh();
        $user->refresh();

        $this->assertTrue($generation->is_billed);
        $this->assertNotNull($generation->actual_cost);

        // Verify balance decreased exactly by actual_cost
        $expectedBalance = bcsub('10.00000000', (string) $generation->actual_cost, 8);
        $this->assertSame($expectedBalance, (string) $user->balance);

        // Verify second sync (duplicate event) does not deduct twice
        $service->syncFromPayload($generation, $payload);
        $user->refresh();
        $this->assertSame($expectedBalance, (string) $user->balance);
    }

    public function test_failed_generation_costs_nothing_and_does_not_deduct_balance(): void
    {
        $user = User::factory()->create([
            'balance' => '10.00000000',
        ]);

        $generation = Generation::create([
            'user_id' => $user->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Processing,
            'prompt' => 'A prompt that fails [fail]',
            'size' => '1024x1024',
            'quality' => 'medium',
            'estimated_cost' => '0.05000000',
        ]);

        $service = app(GenerationService::class);

        $payload = [
            'id' => 'gen_test_failed_456',
            'status' => 'failed',
            'error' => [
                'code' => 'generation_failed',
                'message' => 'Failed during processing',
            ],
        ];

        $service->syncFromPayload($generation, $payload);

        $generation->refresh();
        $user->refresh();

        $this->assertSame('0.00000000', (string) $generation->actual_cost);
        $this->assertSame('10.00000000', (string) $user->balance);
    }

    public function test_warning_message_and_validation_error_when_balance_hits_zero(): void
    {
        $user = User::factory()->create([
            'balance' => '0.00000000',
        ]);

        $response = $this->actingAs($user)->post(route('generations.store'), [
            'prompt' => 'A futuristic cityscape',
            'size' => '1024x1024',
            'quality' => 'medium',
        ]);

        $response->assertSessionHasErrors(['prompt']);
        $errorMsg = session('errors')->first('prompt');
        $this->assertStringContainsString('Your credit balance has hit $0.000000', $errorMsg);
    }
}
