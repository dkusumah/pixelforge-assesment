<?php

namespace Tests\Unit;

use App\Services\PixelforgeClient;
use App\Services\PricingService;
use Tests\TestCase;

class PricingServiceTest extends TestCase
{
    public function test_it_calculates_exact_costs_matching_assessment_documentation_worked_example(): void
    {
        $client = $this->createMock(PixelforgeClient::class);
        $pricingService = new PricingService($client);

        $usage = [
            'input_text_tokens' => 115,
            'input_image_tokens' => 1530,
            'output_tokens' => 6450,
            'reasoning_tokens' => 8000,
        ];

        $rates = [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ];

        $cost = $pricingService->calculateActualCost($usage, $rates);

        $this->assertSame('0.000575000', $cost['input_text_cost']);
        $this->assertSame('0.015300000', $cost['input_image_cost']);
        $this->assertSame('0.258000000', $cost['output_cost']);
        $this->assertSame('0.100000000', $cost['reasoning_cost']);
        $this->assertSame('0.373875000', $cost['provider_cost']);
        // 0.373875000 * 1.25 = 0.467343750
        $this->assertSame('0.467343750', $cost['user_cost']);
    }

    public function test_it_estimates_costs_upfront_with_variance_and_quality(): void
    {
        $client = $this->createMock(PixelforgeClient::class);
        $pricingService = new PricingService($client);

        $prompt = str_repeat('a', 400); // 400 chars -> 100 baseline tokens
        $estimate = $pricingService->estimateCost(
            prompt: $prompt,
            size: '1536x1024',
            quality: 'high',
            referenceImagesCount: 2
        );

        $this->assertSame(85, $estimate['estimated_tokens']['input_text']['min']);
        $this->assertSame(100, $estimate['estimated_tokens']['input_text']['expected']);
        $this->assertSame(115, $estimate['estimated_tokens']['input_text']['max']);
        $this->assertSame(1530, $estimate['estimated_tokens']['input_image']);
        $this->assertSame(6450, $estimate['estimated_tokens']['output']);
        $this->assertSame(1000, $estimate['estimated_tokens']['reasoning']['min']);
        $this->assertSame(8000, $estimate['estimated_tokens']['reasoning']['max']);

        $this->assertSame('0.373875000', $estimate['estimated_provider_cost']['max']);
        $this->assertSame('0.467343750', $estimate['estimated_user_cost']['max']);
    }
}
