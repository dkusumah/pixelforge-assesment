<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class AdminUsageReconciliationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_usage(): void
    {
        $response = $this->get('/admin/usage');
        $response->assertRedirect('/login');
    }

    public function test_authenticated_user_can_view_admin_usage(): void
    {
        $user = User::factory()->create();

        $mockClient = Mockery::mock(PixelforgeClient::class);
        $mockClient->shouldReceive('getUsage')
            ->andReturn([
                'object' => 'usage',
                'start_date' => '2026-11-20',
                'end_date' => '2026-11-26',
                'data' => [
                    [
                        'date' => '2026-11-26',
                        'generations' => 1,
                        'cost_usd' => '0.250000000',
                    ],
                ],
                'total' => [
                    'generations' => 1,
                    'cost_usd' => '0.250000000',
                ],
            ]);

        $mockClient->shouldReceive('listGenerations')
            ->andReturn([
                'object' => 'list',
                'data' => [],
                'has_more' => false,
                'next_cursor' => null,
            ]);

        $this->app->instance(PixelforgeClient::class, $mockClient);

        // Create a matching local generation
        Generation::create([
            'user_id' => $user->id,
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'prompt' => 'Matching test prompt',
            'status' => GenerationStatus::Succeeded,
            'provider_cost' => '0.250000000',
            'actual_cost' => '0.312500000',
            'completed_at' => '2026-11-26 12:00:00',
            'estimated_cost' => '0.25000000',
            'is_billed' => true,
        ]);

        $response = $this->actingAs($user)->get('/admin/usage');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Admin/Usage')
            ->where('reconciled', true)
            ->has('dailyStats')
            ->has('userStats')
        );
    }

    public function test_manual_sync_endpoint_imports_remote_generations(): void
    {
        $user = User::factory()->create();

        $mockClient = Mockery::mock(PixelforgeClient::class);
        $mockClient->shouldReceive('listGenerations')
            ->once()
            ->andReturn([
                'object' => 'list',
                'data' => [
                    [
                        'id' => 'gen_mock_remote_123',
                        'status' => 'succeeded',
                        'prompt' => 'A test prompt from provider',
                        'size' => '1024x1024',
                        'quality' => 'medium',
                        'reference_images' => [],
                        'metadata' => [
                            'user_id' => (string) $user->id,
                        ],
                        'usage' => [
                            'input_text_tokens' => 20,
                            'input_image_tokens' => 0,
                            'output_tokens' => 1000,
                            'reasoning_tokens' => 500,
                            'total_tokens' => 1520,
                        ],
                        'rates' => [
                            'input_text_tokens' => '5.00',
                            'input_image_tokens' => '10.00',
                            'output_tokens' => '40.00',
                            'reasoning_tokens' => '12.50',
                        ],
                        'created_at' => '2026-11-26T10:00:00Z',
                        'completed_at' => '2026-11-26T10:01:00Z',
                    ],
                ],
                'has_more' => false,
                'next_cursor' => null,
            ]);

        $this->app->instance(PixelforgeClient::class, $mockClient);

        $response = $this->actingAs($user)->post('/admin/usage/sync');

        $response->assertRedirect();
        $this->assertDatabaseHas('generations', [
            'pixelforge_id' => 'gen_mock_remote_123',
            'status' => GenerationStatus::Succeeded->value,
        ]);
    }

    public function test_sync_usage_artisan_command(): void
    {
        $user = User::factory()->create();

        $mockClient = Mockery::mock(PixelforgeClient::class);
        $mockClient->shouldReceive('listGenerations')
            ->andReturn([
                'object' => 'list',
                'data' => [],
                'has_more' => false,
                'next_cursor' => null,
            ]);

        $mockClient->shouldReceive('getUsage')
            ->andReturn([
                'object' => 'usage',
                'start_date' => '2026-11-26',
                'end_date' => '2026-11-26',
                'data' => [],
                'total' => [
                    'generations' => 0,
                    'cost_usd' => '0.000000000',
                ],
            ]);

        $this->app->instance(PixelforgeClient::class, $mockClient);

        $this->artisan('pixelforge:sync-usage')
            ->expectsOutputToContain('Synchronizing generations from Pixelforge API...')
            ->assertSuccessful();
    }
}
