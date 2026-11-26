<?php

namespace App\Http\Controllers;

use App\Services\UsageReportService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminUsageController extends Controller
{
    public function __construct(
        protected UsageReportService $usageReportService,
    ) {}

    /**
     * Display operator usage, spend, profit and provider reconciliation.
     */
    public function index(Request $request): Response
    {
        $startDate = $request->query('start_date', Carbon::now('UTC')->subDays(14)->toDateString());
        $endDate = $request->query('end_date', Carbon::now('UTC')->toDateString());

        $reconciliation = $this->usageReportService->reconcileWithProvider($startDate, $endDate, autoSync: true);
        $userStats = $this->usageReportService->getUserStats($startDate, $endDate);

        return Inertia::render('Admin/Usage', [
            'dailyStats' => $reconciliation['local_stats']->values(),
            'userStats' => $userStats,
            'remoteUsage' => $reconciliation['remote_usage'],
            'reconciled' => $reconciliation['reconciled'],
            'discrepancies' => $reconciliation['discrepancies'],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
            ],
        ]);
    }

    /**
     * Manually synchronize generation records from Pixelforge provider.
     */
    public function sync(): RedirectResponse
    {
        $count = $this->usageReportService->syncFromProvider();

        return redirect()->back()->with('success', "Successfully synced {$count} generation records from Pixelforge provider.");
    }
}
