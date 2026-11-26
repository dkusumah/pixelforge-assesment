<?php

namespace App\Console\Commands;

use App\Services\UsageReportService;
use Illuminate\Console\Command;

class PixelforgeSyncUsageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'pixelforge:sync-usage {--start-date= : YYYY-MM-DD start date} {--end-date= : YYYY-MM-DD end date}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Synchronize generations from Pixelforge API to ensure local provider records match GET /v1/usage source of truth.';

    /**
     * Execute the console command.
     */
    public function handle(UsageReportService $usageReportService): int
    {
        $this->info('Synchronizing generations from Pixelforge API...');

        $synced = $usageReportService->syncFromProvider();
        $this->info("Successfully processed {$synced} generations from provider.");

        $startDate = $this->option('start-date');
        $endDate = $this->option('end-date');

        $reconciliation = $usageReportService->reconcileWithProvider($startDate, $endDate);

        if ($reconciliation['reconciled']) {
            $this->info('✅ Reconciliation Verified (100% Match with GET /v1/usage)!');
            return Command::SUCCESS;
        }

        $this->warn('⚠️ Reconciliation Discrepancies detected:');
        foreach ($reconciliation['discrepancies'] as $date => $disc) {
            $this->line(" - {$date}: Local={$disc['local_provider_cost']} USD, Remote={$disc['remote_provider_cost']} USD");
        }

        return Command::FAILURE;
    }
}
