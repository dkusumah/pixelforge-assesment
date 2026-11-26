<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\Client\Response;

class PixelforgeApiException extends Exception
{
    /**
     * @param array{
     *     type?: string,
     *     code?: string,
     *     message?: string,
     *     param?: string|null,
     *     errors?: array<string, array<string>>
     * } $errorData
     */
    public function __construct(
        string $message,
        int $statusCode = 0,
        public readonly ?string $errorType = null,
        public readonly ?string $errorCode = null,
        public readonly ?string $errorParam = null,
        public readonly array $errorDetails = [],
        public readonly ?int $retryAfter = null,
        public readonly array $rawPayload = [],
    ) {
        parent::__construct($message, $statusCode);
    }

    /**
     * Create an exception instance from a failed HTTP response.
     */
    public static function fromResponse(Response $response): self
    {
        $payload = $response->json() ?? [];
        $error = $payload['error'] ?? [];

        $message = $error['message'] ?? $response->reason() ?? 'Unknown Pixelforge API error';
        $type = $error['type'] ?? null;
        $code = $error['code'] ?? null;
        $param = $error['param'] ?? null;
        $errors = $error['errors'] ?? [];
        
        $retryAfterHeader = $response->header('Retry-After');
        $retryAfter = is_numeric($retryAfterHeader) ? (int) $retryAfterHeader : null;

        return new self(
            message: $message,
            statusCode: $response->status(),
            errorType: $type,
            errorCode: $code,
            errorParam: $param,
            errorDetails: $errors,
            retryAfter: $retryAfter,
            rawPayload: $payload,
        );
    }

    /**
     * Determine if this error indicates rate limiting or temporary backpressure.
     */
    public function isRetryable(): bool
    {
        return in_array($this->errorCode, [
            'rate_limit_exceeded',
            'concurrency_limit_exceeded',
            'overloaded',
        ], true) || in_array($this->getCode(), [429, 503], true) && !$this->isDailyLimitExceeded();
    }

    /**
     * Determine if request was rejected due to per-minute rate limit.
     */
    public function isRateLimitExceeded(): bool
    {
        return $this->errorCode === 'rate_limit_exceeded';
    }

    /**
     * Determine if request was rejected due to concurrency limit (max 3 queued/processing).
     */
    public function isConcurrencyLimitExceeded(): bool
    {
        return $this->errorCode === 'concurrency_limit_exceeded';
    }

    /**
     * Determine if the error is a prompt content policy violation.
     */
    public function isContentPolicyViolation(): bool
    {
        return $this->errorCode === 'content_policy_violation';
    }

    /**
     * Determine if the error is a validation error.
     */
    public function isValidationFailed(): bool
    {
        return $this->errorCode === 'validation_failed';
    }

    /**
     * Determine if the error is an idempotency key conflict.
     */
    public function isIdempotencyKeyReused(): bool
    {
        return $this->errorCode === 'idempotency_key_reused';
    }

    /**
     * Determine if the daily limit was reached.
     */
    public function isDailyLimitExceeded(): bool
    {
        return $this->errorCode === 'daily_limit_exceeded';
    }
}
