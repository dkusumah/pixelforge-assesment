<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use Carbon\Carbon;

class GenerationService
{
    public function __construct(
        protected PricingService $pricingService,
    ) {}

    /**
     * Update a Generation model from a Pixelforge generation payload.
     * Guards against out-of-order transitions (e.g. late webhook after completion).
     *
     * @param array<string, mixed> $payload
     */
    public function syncFromPayload(Generation $generation, array $payload): Generation
    {
        $newStatusString = $payload['status'] ?? null;
        $newStatus = $newStatusString ? GenerationStatus::tryFrom($newStatusString) : null;

        // If the current generation is already in a terminal state (succeeded or failed),
        // do not let out-of-order events revert it to queued or processing.
        if ($generation->status?->isTerminal() && $newStatus !== null && !$newStatus->isTerminal()) {
            return $generation;
        }

        if (isset($payload['id'])) {
            $generation->pixelforge_id = $payload['id'];
        }

        if ($newStatus !== null) {
            $generation->status = $newStatus;

            if ($newStatus === GenerationStatus::Failed) {
                // Failed generations are never billed
                $generation->actual_cost = '0.00000000';
                $generation->provider_cost = '0.00000000';
            }
        }

        if (isset($payload['image_url'])) {
            $generation->image_url = $payload['image_url'];
        }

        if (isset($payload['image_url_expires_at'])) {
            $generation->image_url_expires_at = Carbon::parse($payload['image_url_expires_at']);
        }

        if (isset($payload['rates']) && is_array($payload['rates'])) {
            $generation->rates = $payload['rates'];
        }

        if (isset($payload['usage']) && is_array($payload['usage'])) {
            $generation->usage = $payload['usage'];
        }

        if ($generation->status === GenerationStatus::Succeeded && !empty($generation->usage) && !empty($generation->rates)) {
            $costs = $this->pricingService->calculateActualCost($generation->usage, $generation->rates);
            $generation->provider_cost = $costs['provider_cost'];
            $generation->actual_cost = $costs['user_cost'];
        }

        if (isset($payload['started_at']) && $payload['started_at'] !== null) {
            $generation->started_at = Carbon::parse($payload['started_at']);
        }

        if (isset($payload['completed_at']) && $payload['completed_at'] !== null) {
            $generation->completed_at = Carbon::parse($payload['completed_at']);
        }

        if (isset($payload['error']) && is_array($payload['error'])) {
            $generation->error_code = $payload['error']['code'] ?? null;
            $generation->error_message = $payload['error']['message'] ?? null;
        }

        $generation->save();

        return $generation;
    }
}
