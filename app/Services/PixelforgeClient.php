<?php

namespace App\Services;

use App\Exceptions\PixelforgeApiException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

class PixelforgeClient
{
    protected ?int $rateLimitLimit = null;
    protected ?int $rateLimitRemaining = null;
    protected bool $lastRequestWasIdempotentReplayed = false;

    /**
     * Create a new PixelforgeClient instance.
     */
    public function __construct(
        protected ?string $apiKey = null,
        protected ?string $baseUrl = null,
    ) {
        $this->apiKey = $apiKey ?? config('services.pixelforge.api_key');
        $this->baseUrl = rtrim($baseUrl ?? config('services.pixelforge.base_url', 'https://pixelforge.promptedit.dev/v1'), '/');
    }

    /**
     * Build an authenticated HTTP client for Pixelforge requests.
     */
    public function http(?string $idempotencyKey = null): PendingRequest
    {
        $request = Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout(30);

        if ($idempotencyKey !== null) {
            $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        return $request;
    }

    /**
     * Execute a request and handle Pixelforge API errors and rate limit headers.
     *
     * @param callable(PendingRequest): Response $callback
     * @throws PixelforgeApiException
     */
    public function send(callable $callback, ?string $idempotencyKey = null): Response
    {
        $response = $callback($this->http($idempotencyKey));

        $this->lastRequestWasIdempotentReplayed = $response->header('Idempotent-Replayed') === 'true';

        if ($response->hasHeader('X-RateLimit-Limit')) {
            $this->rateLimitLimit = (int) $response->header('X-RateLimit-Limit');
        }

        if ($response->hasHeader('X-RateLimit-Remaining')) {
            $this->rateLimitRemaining = (int) $response->header('X-RateLimit-Remaining');
        }

        if ($response->failed()) {
            throw PixelforgeApiException::fromResponse($response);
        }

        return $response;
    }

    /**
     * Execute a request with controlled retries for transient backpressure / rate limits.
     *
     * @param callable(PendingRequest): Response $callback
     * @throws PixelforgeApiException
     */
    public function sendWithRetry(callable $callback, ?string $idempotencyKey = null, int $maxAttempts = 3): Response
    {
        $attempt = 0;

        while ($attempt < $maxAttempts) {
            $attempt++;

            try {
                return $this->send($callback, $idempotencyKey);
            } catch (PixelforgeApiException $e) {
                if (!$e->isRetryable() || $attempt >= $maxAttempts) {
                    throw $e;
                }

                $delaySeconds = $e->retryAfter ?? ($attempt * 2);
                $sleepSeconds = min($delaySeconds, 10);
                sleep($sleepSeconds);
            }
        }

        throw new PixelforgeApiException('Max retry attempts reached');
    }

    /**
     * Create an image generation via Pixelforge API.
     *
     * @param array{
     *     prompt: string,
     *     size?: string,
     *     quality?: string,
     *     reference_images?: array<string>,
     *     metadata?: array<string, string>
     * } $payload
     * @return array<string, mixed>
     * @throws PixelforgeApiException
     */
    public function createGeneration(array $payload, ?string $idempotencyKey = null): array
    {
        $filteredPayload = array_filter([
            'prompt' => $payload['prompt'],
            'size' => $payload['size'] ?? null,
            'quality' => $payload['quality'] ?? null,
            'reference_images' => !empty($payload['reference_images']) ? $payload['reference_images'] : null,
            'metadata' => !empty($payload['metadata']) ? $payload['metadata'] : null,
        ], fn ($value) => $value !== null);

        $response = $this->send(
            fn (PendingRequest $http) => $http->post('/generations', $filteredPayload),
            $idempotencyKey
        );

        return $response->json();
    }

    /**
     * Retrieve a generation by ID.
     *
     * @return array<string, mixed>
     * @throws PixelforgeApiException
     */
    public function getGeneration(string $id): array
    {
        $response = $this->send(
            fn (PendingRequest $http) => $http->get("/generations/{$id}")
        );

        return $response->json();
    }

    /**
     * List generations with optional filtering and cursor pagination.
     *
     * @param array{
     *     limit?: int,
     *     status?: string,
     *     cursor?: string
     * } $query
     * @return array{
     *     object: string,
     *     data: list<array<string, mixed>>,
     *     has_more: bool,
     *     next_cursor: string|null
     * }
     * @throws PixelforgeApiException
     */
    public function listGenerations(array $query = []): array
    {
        $filteredQuery = array_filter([
            'limit' => $query['limit'] ?? null,
            'status' => $query['status'] ?? null,
            'cursor' => $query['cursor'] ?? null,
        ], fn ($value) => $value !== null);

        $response = $this->send(
            fn (PendingRequest $http) => $http->get('/generations', $filteredQuery)
        );

        return $response->json();
    }

    /**
     * Fetch the current pricing table and token calculation specifications.
     * Unauthenticated endpoint.
     *
     * @return array<string, mixed>
     * @throws PixelforgeApiException
     */
    public function getPricing(): array
    {
        $response = Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout(10)
            ->get('/pricing');

        if ($response->failed()) {
            throw PixelforgeApiException::fromResponse($response);
        }

        return $response->json();
    }

    /**
     * Retrieve usage and provider billing per UTC day.
     *
     * @param string|null $startDate YYYY-MM-DD
     * @param string|null $endDate YYYY-MM-DD
     * @return array{
     *     object: string,
     *     start_date: string,
     *     end_date: string,
     *     data: list<array<string, mixed>>,
     *     total: array<string, mixed>
     * }
     * @throws PixelforgeApiException
     */
    public function getUsage(?string $startDate = null, ?string $endDate = null): array
    {
        $query = array_filter([
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $response = $this->send(
            fn (PendingRequest $http) => $http->get('/usage', $query)
        );

        return $response->json();
    }

    /**
     * Get the configured API key.
     */
    public function getApiKey(): ?string
    {
        return $this->apiKey;
    }

    /**
     * Get the configured base URL.
     */
    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /**
     * Get the last reported requests allowed per minute.
     */
    public function getRateLimitLimit(): ?int
    {
        return $this->rateLimitLimit;
    }

    /**
     * Get the last reported requests remaining this minute.
     */
    public function getRateLimitRemaining(): ?int
    {
        return $this->rateLimitRemaining;
    }

    /**
     * Determine if the last request was an idempotent replay.
     */
    public function wasLastRequestIdempotentReplayed(): bool
    {
        return $this->lastRequestWasIdempotentReplayed;
    }
}
