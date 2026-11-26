<?php

namespace App\Services;

use App\Enums\GenerationStatus;
use App\Models\Generation;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class UsageReportService
{
    public function __construct(
        protected PixelforgeClient $client,
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
     * Reconcile local records against Pixelforge provider usage API.
     *
     * @return array{
     *     reconciled: bool,
     *     discrepancies: array<string, array{local_provider_cost: string, remote_provider_cost: string}>,
     *     local_stats: Collection<string, mixed>,
     *     remote_usage: array<string, mixed>
     * }
     */
    public function reconcileWithProvider(?string $startDate = null, ?string $endDate = null): array
    {
        $remote = $this->client->getUsage($startDate, $endDate);
        $local = $this->getLocalDailyStats($startDate, $endDate);

        $discrepancies = [];
        $reconciled = true;

        foreach ($remote['data'] ?? [] as $remoteDay) {
            $date = $remoteDay['date'];
            $remoteCost = $remoteDay['cost_usd'] ?? '0.000000000';
            $localDay = $local->get($date);
            $localCost = $localDay['provider_cost'] ?? '0.000000000';

            if (bccomp($localCost, $remoteCost, 9) !== 0) {
                $reconciled = false;
                $discrepancies[$date] = [
                    'local_provider_cost' => $localCost,
                    'remote_provider_cost' => $remoteCost,
                ];
            }
        }

        return [
            'reconciled' => $reconciled,
            'discrepancies' => $discrepancies,
            'local_stats' => $local,
            'remote_usage' => $remote,
        ];
    }
}
