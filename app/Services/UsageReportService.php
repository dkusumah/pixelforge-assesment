<?php

namespace App\Services;

use App\Enums\GenerationQuality;
use App\Enums\GenerationSize;
use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class UsageReportService
{
    public function __construct(
        protected PixelforgeClient $client,
        protected PricingService $pricingService,
    ) {}

    /**
     * Get local daily generation statistics and costs grouped by UTC completion date.
     *
     * @return Collection<string, array{
     *     date: string,
     *     generations_count: int,
     *     user_spend: string,
     *     provider_cost: string,
     *     profit: string
     * }>
     */
    public function getLocalDailyStats(?string $startDate = null, ?string $endDate = null): Collection
    {
        $start = $startDate ? Carbon::parse($startDate, 'UTC')->startOfDay() : Carbon::now('UTC')->subDays(29)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate, 'UTC')->endOfDay() : Carbon::now('UTC')->endOfDay();

        $generations = Generation::query()
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->get();

        // Group by UTC date of completed_at
        $grouped = $generations->groupBy(fn (Generation $g) => $g->completed_at->setTimezone('UTC')->toDateString());

        // Generate full date range
        $results = collect();
        $current = $start->copy();
        while ($current->lte($end)) {
            $dateString = $current->toDateString();
            $items = $grouped->get($dateString, collect());

            $providerTotal = '0.000000000';
            $userTotal = '0.000000000';

            foreach ($items as $gen) {
                if ($gen->provider_cost !== null) {
                    $providerTotal = bcadd($providerTotal, (string) $gen->provider_cost, 9);
                }
                if ($gen->actual_cost !== null) {
                    $userTotal = bcadd($userTotal, (string) $gen->actual_cost, 9);
                }
            }

            $profit = bcsub($userTotal, $providerTotal, 9);

            $results->put($dateString, [
                'date' => $dateString,
                'generations_count' => $items->count(),
                'user_spend' => $userTotal,
                'provider_cost' => $providerTotal,
                'profit' => $profit,
            ]);

            $current->addDay();
        }

        return $results;
    }

    /**
     * Get breakdown by user for a given date range.
     *
     * @return Collection<int, array{
     *     user_id: int,
     *     user_name: string,
     *     user_email: string,
     *     generations_count: int,
     *     user_spend: string,
     *     provider_cost: string,
     *     profit: string
     * }>
     */
    public function getUserStats(?string $startDate = null, ?string $endDate = null): Collection
    {
        $start = $startDate ? Carbon::parse($startDate, 'UTC')->startOfDay() : Carbon::now('UTC')->subDays(29)->startOfDay();
        $end = $endDate ? Carbon::parse($endDate, 'UTC')->endOfDay() : Carbon::now('UTC')->endOfDay();

        $generations = Generation::with('user')
            ->where('status', GenerationStatus::Succeeded)
            ->whereNotNull('completed_at')
            ->whereBetween('completed_at', [$start, $end])
            ->get()
            ->groupBy('user_id');

        return $generations->map(function ($items, $userId) {
            $first = $items->first();
            $providerTotal = '0.000000000';
            $userTotal = '0.000000000';

            foreach ($items as $gen) {
                if ($gen->provider_cost !== null) {
                    $providerTotal = bcadd($providerTotal, (string) $gen->provider_cost, 9);
                }
                if ($gen->actual_cost !== null) {
                    $userTotal = bcadd($userTotal, (string) $gen->actual_cost, 9);
                }
            }

            return [
                'user_id' => (int) $userId,
                'user_name' => $first?->user?->name ?? 'Unknown',
                'user_email' => $first?->user?->email ?? '',
                'generations_count' => $items->count(),
                'user_spend' => $userTotal,
                'provider_cost' => $providerTotal,
                'profit' => bcsub($userTotal, $providerTotal, 9),
            ];
        })->values();
    }

    /**
     * Synchronize generations from Pixelforge provider API into local database.
     * Guarantees local provider records match GET /v1/usage source of truth.
     */
    public function syncFromProvider(): int
    {
        $cursor = null;
        $syncedCount = 0;
        $fallbackUser = User::query()->first();

        do {
            $params = ['limit' => 50];
            if ($cursor !== null) {
                $params['cursor'] = $cursor;
            }

            $response = $this->client->listGenerations($params);
            $items = $response['data'] ?? [];

            foreach ($items as $item) {
                $pixelforgeId = $item['id'] ?? null;
                if (!$pixelforgeId) {
                    continue;
                }

                $generation = Generation::where('pixelforge_id', $pixelforgeId)->first();
                if (!$generation) {
                    $generation = new Generation();
                    $generation->pixelforge_id = $pixelforgeId;
                    $generation->idempotency_key = $item['metadata']['idempotency_key'] ?? (string) Str::uuid();

                    $userId = isset($item['metadata']['user_id']) ? (int) $item['metadata']['user_id'] : null;
                    if ($userId && !User::where('id', $userId)->exists()) {
                        $userId = $fallbackUser?->id;
                    }
                    $generation->user_id = $userId ?? $fallbackUser?->id;
                    $generation->prompt = $item['prompt'] ?? 'Remote generation';
                    $generation->size = GenerationSize::tryFrom($item['size'] ?? '') ?? GenerationSize::Square;
                    $generation->quality = GenerationQuality::tryFrom($item['quality'] ?? '') ?? GenerationQuality::Medium;
                    $generation->reference_images = $item['reference_images'] ?? [];
                    $generation->metadata = $item['metadata'] ?? [];
                    $generation->estimated_cost = '0.00000000';
                }

                $statusString = $item['status'] ?? null;
                if ($statusString) {
                    $generation->status = GenerationStatus::tryFrom($statusString) ?? GenerationStatus::Succeeded;
                }

                if (isset($item['image_url'])) {
                    $generation->image_url = $item['image_url'];
                }
                if (isset($item['image_url_expires_at'])) {
                    $generation->image_url_expires_at = Carbon::parse($item['image_url_expires_at']);
                }
                if (isset($item['usage']) && is_array($item['usage'])) {
                    $generation->usage = $item['usage'];
                }
                if (isset($item['rates']) && is_array($item['rates'])) {
                    $generation->rates = $item['rates'];
                }

                if ($generation->status === GenerationStatus::Succeeded && !empty($generation->usage) && !empty($generation->rates)) {
                    $costs = $this->pricingService->calculateActualCost($generation->usage, $generation->rates);
                    $generation->provider_cost = $costs['provider_cost'];
                    $generation->actual_cost = $costs['user_cost'];
                    $generation->is_billed = true;
                } elseif ($generation->status === GenerationStatus::Failed) {
                    $generation->provider_cost = '0.00000000';
                    $generation->actual_cost = '0.00000000';
                    $generation->is_billed = true;
                }

                if (isset($item['created_at'])) {
                    $generation->created_at = Carbon::parse($item['created_at']);
                }
                if (isset($item['started_at'])) {
                    $generation->started_at = Carbon::parse($item['started_at']);
                }
                if (isset($item['completed_at'])) {
                    $generation->completed_at = Carbon::parse($item['completed_at']);
                } elseif ($generation->status === GenerationStatus::Succeeded) {
                    $generation->completed_at = $generation->completed_at ?? Carbon::now('UTC');
                }

                $generation->save();
                $syncedCount++;
            }

            $cursor = $response['next_cursor'] ?? null;
            $hasMore = $response['has_more'] ?? false;
        } while ($hasMore && $cursor);

        return $syncedCount;
    }

    /**
     * Reconcile local records against Pixelforge provider usage API.
     * If autoSync is enabled and discrepancies exist, syncs provider generations to ensure 100% reconciliation.
     *
     * @return array{
     *     reconciled: bool,
     *     discrepancies: array<string, array{local_provider_cost: string, remote_provider_cost: string}>,
     *     local_stats: Collection<string, mixed>,
     *     remote_usage: array<string, mixed>
     * }
     */
    public function reconcileWithProvider(?string $startDate = null, ?string $endDate = null, bool $autoSync = false): array
    {
        $remote = $this->client->getUsage($startDate, $endDate);
        $local = $this->getLocalDailyStats($startDate, $endDate);

        $checkDiscrepancies = function (Collection $localStats) use ($remote) {
            $discrepancies = [];
            $reconciled = true;

            foreach ($remote['data'] ?? [] as $remoteDay) {
                $date = $remoteDay['date'];
                $remoteCost = $remoteDay['cost_usd'] ?? '0.000000000';
                $localDay = $localStats->get($date);
                $localCost = $localDay['provider_cost'] ?? '0.000000000';

                if (bccomp($localCost, $remoteCost, 9) !== 0) {
                    $reconciled = false;
                    $discrepancies[$date] = [
                        'local_provider_cost' => $localCost,
                        'remote_provider_cost' => $remoteCost,
                    ];
                }
            }

            return [$reconciled, $discrepancies];
        };

        [$reconciled, $discrepancies] = $checkDiscrepancies($local);

        if (!$reconciled && $autoSync) {
            $this->syncFromProvider();
            $local = $this->getLocalDailyStats($startDate, $endDate);
            [$reconciled, $discrepancies] = $checkDiscrepancies($local);
        }

        return [
            'reconciled' => $reconciled,
            'discrepancies' => $discrepancies,
            'local_stats' => $local,
            'remote_usage' => $remote,
        ];
    }
}
