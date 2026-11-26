<?php

namespace Tests\Feature;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use App\Services\PixelforgeClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class GenerationFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_generations_index(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('generations.index'));

        $response->assertOk();
    }

    public function test_authenticated_user_can_view_generation_create_page(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('generations.create'));

        $response->assertOk();
    }

    public function test_estimate_endpoint_returns_json_calculation(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->postJson(route('generations.estimate'), [
            'prompt' => 'A lighthouse on a cliff',
            'size' => '1024x1024',
            'quality' => 'medium',
            'reference_images_count' => 1,
        ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'estimated_tokens' => [
                'input_text',
                'input_image',
                'output',
                'reasoning',
            ],
            'estimated_user_cost' => [
                'min',
                'expected',
                'max',
            ],
        ]);
    }

    public function test_user_with_insufficient_balance_cannot_create_generation(): void
    {
        $user = User::factory()->create([
            'balance' => '0.00000000',
        ]);

        $response = $this->actingAs($user)->post(route('generations.store'), [
            'prompt' => 'A futuristic neon city at night',
            'size' => '1536x1024',
            'quality' => 'high',
        ]);

        $response->assertSessionHasErrors(['prompt']);
        $this->assertDatabaseCount('generations', 0);
    }

    public function test_wallet_top_up_increases_user_balance(): void
    {
        $user = User::factory()->create([
            'balance' => '5.00000000',
        ]);

        $response = $this->actingAs($user)->post(route('wallet.top-up'), [
            'amount' => 25,
        ]);

        $response->assertRedirect();
        $user->refresh();
        $this->assertSame('30.00000000', (string) $user->balance);
    }

    public function test_polling_endpoint_returns_generation_status(): void
    {
        $user = User::factory()->create();
        $generation = Generation::create([
            'user_id' => $user->id,
            'idempotency_key' => (string) Str::uuid(),
            'status' => GenerationStatus::Processing,
            'prompt' => 'Test prompt',
            'size' => '1024x1024',
            'quality' => 'medium',
            'estimated_cost' => '0.05000000',
        ]);

        $response = $this->actingAs($user)->getJson(route('generations.poll', $generation));

        $response->assertOk();
        $response->assertJson([
            'id' => $generation->id,
            'status' => 'processing',
            'is_terminal' => false,
            'user_balance' => '10.00000000',
        ]);
    }

    public function test_wallet_balance_endpoint_returns_current_balance(): void
    {
        $user = User::factory()->create([
            'balance' => '15.50000000',
        ]);

        $response = $this->actingAs($user)->getJson(route('wallet.balance'));

        $response->assertOk();
        $response->assertJson([
            'balance' => '15.50000000',
        ]);
    }
}
