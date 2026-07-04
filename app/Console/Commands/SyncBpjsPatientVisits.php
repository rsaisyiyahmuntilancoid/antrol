<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\RegPeriksa;
use App\Services\FlowAnalyticsService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class SyncBpjsPatientVisits extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bpjs:sync-patient-visits {--date= : Target registration date (YYYY-MM-DD), defaults to today}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Sync daily BPJS patient visit task lists and save to local cache';

    /**
     * Execute the console command.
     */
    public function handle(FlowAnalyticsService $analyticsService)
    {
        $dateInput = $this->option('date');
        if ($dateInput) {
            $dates = [Carbon::parse($dateInput)->toDateString()];
        } else {
            $dates = [
                Carbon::yesterday()->toDateString(),
                Carbon::today()->toDateString(),
            ];
        }

        foreach ($dates as $date) {
            $this->info("Starting BPJS patient visits sync for date: {$date}");
            Log::info("CLI: Starting BPJS patient visits sync for date: {$date}");

            $result = $analyticsService->syncDatePatientsDirectly($date);

            $summaryMessage = "Sync completed for {$date}. Total: {$result['total']}, Synced: {$result['synced']}, Failed: {$result['failed']}";
            $this->info($summaryMessage);
            Log::info("CLI: {$summaryMessage}");
        }

        return Command::SUCCESS;
    }
}
