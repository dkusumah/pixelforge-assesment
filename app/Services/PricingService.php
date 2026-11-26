<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class PricingService
{
    public const MARKUP_MULTIPLIER = '1.25'; // 25% markup
    public const BC_SCALE = 9;

    /**
     * Fallback pricing table matching Pixelforge defaults if API is temporarily unreachable.
     *
     * @var array<string, mixed>
     */
    protected array $fallbackPricing = [
        'rates' => [
            'input_text_tokens' => '5.00',
            'input_image_tokens' => '10.00',
            'output_tokens' => '40.00',
            'reasoning_tokens' => '12.50',
        ],
        'tokens' => [
            'characters_per_text_token' => 4,
            'text_token_variance' => 0.15,
            'per_reference_image' => 765,
            'output' => [
                'low' => [
                    '1024x1024' => 280,
                    '1536x1024' => 420,
                    '1024x1536' => 420,
                ],
                'medium' => [
                    '1024x1024' => 1100,
                    '1536x1024' => 1650,
                    '1024x1536' => 1650,
                ],
                'high' => [
                    '1024x1024' => 4300,
                    '1536x1024' => 6450,
                    '1024x1536' => 6450,
                ],
            ],
            'reasoning' => [
                'low' => ['min' => 0, 'max' => 500],
                'medium' => ['min' => 200, 'max' => 2000],
                'high' => ['min' => 1000, 'max' => 8000],
            ],
        ],
    ];

    public function __construct(
        protected PixelforgeClient $client,
    ) {}

    /**
     * Get the live pricing schema, cached for 1 hour.
     *
     * @return array<string, mixed>
     */
    public function getPricing(bool $refresh = false): array
    {
        if ($refresh) {
            Cache::forget('pixelforge_pricing');
        }

        return Cache::remember('pixelforge_pricing', 3600, function () {
            try {
                return $this->client->getPricing();
            } catch (\Throwable) {
                return $this->fallbackPricing;
            }
        });
    }

    /**
     * Calculate cost for a specific token count given a rate per 1M tokens.
     */
    public function calculateTokenCost(int|string $tokens, string $ratePer1M): string
    {
        $product = bcmul((string) $tokens, $ratePer1M, self::BC_SCALE);

        return bcdiv($product, '1000000', self::BC_SCALE);
    }

    /**
     * Calculate actual costs for completed generation usage and rates.
     *
     * @param array{
     *     input_text_tokens?: int,
     *     input_image_tokens?: int,
     *     output_tokens?: int,
     *     reasoning_tokens?: int,
     *     total_tokens?: int
     * } $usage
     * @param array{
     *     input_text_tokens: string,
     *     input_image_tokens: string,
     *     output_tokens: string,
     *     reasoning_tokens: string
     * } $rates
     * @return array{
     *     input_text_cost: string,
     *     input_image_cost: string,
     *     output_cost: string,
     *     reasoning_cost: string,
     *     provider_cost: string,
     *     user_cost: string
     * }
     */
    public function calculateActualCost(array $usage, array $rates): array
    {
        $inputTextCost = $this->calculateTokenCost($usage['input_text_tokens'] ?? 0, $rates['input_text_tokens'] ?? '0.00');
        $inputImageCost = $this->calculateTokenCost($usage['input_image_tokens'] ?? 0, $rates['input_image_tokens'] ?? '0.00');
        $outputCost = $this->calculateTokenCost($usage['output_tokens'] ?? 0, $rates['output_tokens'] ?? '0.00');
        $reasoningCost = $this->calculateTokenCost($usage['reasoning_tokens'] ?? 0, $rates['reasoning_tokens'] ?? '0.00');

        $totalProviderCost = bcadd($inputTextCost, $inputImageCost, self::BC_SCALE);
        $totalProviderCost = bcadd($totalProviderCost, $outputCost, self::BC_SCALE);
        $totalProviderCost = bcadd($totalProviderCost, $reasoningCost, self::BC_SCALE);

        $userCost = bcmul($totalProviderCost, self::MARKUP_MULTIPLIER, self::BC_SCALE);

        return [
            'input_text_cost' => $inputTextCost,
            'input_image_cost' => $inputImageCost,
            'output_cost' => $outputCost,
            'reasoning_cost' => $reasoningCost,
            'provider_cost' => $totalProviderCost,
            'user_cost' => $userCost,
        ];
    }

    /**
     * Estimate token counts and cost range for a generation request upfront.
     *
     * @return array{
     *     estimated_tokens: array{
     *         input_text: array{min: int, expected: int, max: int},
     *         input_image: int,
     *         output: int,
     *         reasoning: array{min: int, max: int}
     *     },
     *     estimated_provider_cost: array{min: string, expected: string, max: string},
     *     estimated_user_cost: array{min: string, expected: string, max: string},
     *     rates: array<string, string>
     * }
     */
    public function estimateCost(
        string $prompt,
        string $size = '1024x1024',
        string $quality = 'medium',
        int $referenceImagesCount = 0,
        ?array $customRates = null,
    ): array {
        $pricing = $this->getPricing();
        $rates = $customRates ?? $pricing['rates'] ?? $this->fallbackPricing['rates'];
        $tokenSpecs = $pricing['tokens'] ?? $this->fallbackPricing['tokens'];

        $charsPerToken = $tokenSpecs['characters_per_text_token'] ?? 4;
        $variance = $tokenSpecs['text_token_variance'] ?? 0.15;
        $perImageTokens = $tokenSpecs['per_reference_image'] ?? 765;

        // 1. Text tokens
        $charLength = mb_strlen($prompt);
        $textTokensBaseline = (int) ceil($charLength / $charsPerToken);
        $textTokensMin = max(1, (int) floor($textTokensBaseline * (1.0 - $variance)));
        $textTokensMax = (int) ceil($textTokensBaseline * (1.0 + $variance));

        // 2. Image tokens
        $imageTokens = $referenceImagesCount * $perImageTokens;

        // 3. Output tokens
        $outputTokens = $tokenSpecs['output'][$quality][$size]
            ?? $this->fallbackPricing['tokens']['output'][$quality][$size]
            ?? 1100;

        // 4. Reasoning tokens
        $reasoningMin = $tokenSpecs['reasoning'][$quality]['min']
            ?? $this->fallbackPricing['tokens']['reasoning'][$quality]['min']
            ?? 200;
        $reasoningMax = $tokenSpecs['reasoning'][$quality]['max']
            ?? $this->fallbackPricing['tokens']['reasoning'][$quality]['max']
            ?? 2000;
        $reasoningExpected = (int) round(($reasoningMin + $reasoningMax) / 2);

        // Calculate costs
        $calc = function (int $text, int $image, int $output, int $reasoning) use ($rates): array {
            return $this->calculateActualCost([
                'input_text_tokens' => $text,
                'input_image_tokens' => $image,
                'output_tokens' => $output,
                'reasoning_tokens' => $reasoning,
            ], $rates);
        };

        $minCosts = $calc($textTokensMin, $imageTokens, $outputTokens, $reasoningMin);
        $expectedCosts = $calc($textTokensBaseline, $imageTokens, $outputTokens, $reasoningExpected);
        $maxCosts = $calc($textTokensMax, $imageTokens, $outputTokens, $reasoningMax);

        return [
            'estimated_tokens' => [
                'input_text' => [
                    'min' => $textTokensMin,
                    'expected' => $textTokensBaseline,
                    'max' => $textTokensMax,
                ],
                'input_image' => $imageTokens,
                'output' => $outputTokens,
                'reasoning' => [
                    'min' => $reasoningMin,
                    'max' => $reasoningMax,
                ],
            ],
            'estimated_provider_cost' => [
                'min' => $minCosts['provider_cost'],
                'expected' => $expectedCosts['provider_cost'],
                'max' => $maxCosts['provider_cost'],
            ],
            'estimated_user_cost' => [
                'min' => $minCosts['user_cost'],
                'expected' => $expectedCosts['user_cost'],
                'max' => $maxCosts['user_cost'],
            ],
            'rates' => $rates,
        ];
    }
}
