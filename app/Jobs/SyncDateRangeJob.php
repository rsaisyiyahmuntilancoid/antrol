<?php

namespace App\Jobs;

use App\Services\FlowAnalyticsService;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SyncDateRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 1800; // 30 minutes

    protected $dateFrom;
    protected $dateTo;
    protected $syncKey;

    /**
     * Create a new job instance.
     */
    public function __construct(string $dateFrom, string $dateTo)
    {
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->syncKey = "sync_range_" . $dateFrom . "_" . $dateTo;
    }

    /**
     * Execute the job.
     */
    public function handle(FlowAnalyticsService $analyticsService): void
    {
        Log::info("Starting SyncDateRangeJob from {$this->dateFrom} to {$this->dateTo}");

        $period = CarbonPeriod::create($this->dateFrom, $this->dateTo);
        $dates = [];
        foreach ($period as $date) {
            $dates[] = $date->toDateString();
        }

        $totalDays = count($dates);
        
        Cache::put($this->syncKey, [
            'status' => 'processing',
            'total_days' => $totalDays,
            'processed_days' => 0,
            'current_date' => null,
            'percent' => 0,
            'started_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
        ], 86400);

        $processed = 0;
        foreach ($dates as $date) {
            Cache::put($this->syncKey, [
                'status' => 'processing',
                'total_days' => $totalDays,
                'processed_days' => $processed,
                'current_date' => $date,
                'percent' => (int) round(($processed / $totalDays) * 100),
                'started_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
            ], 86400);

            try {
                // Sync all patients for this single date
                $analyticsService->syncDatePatientsDirectly($date);
            } catch (\Exception $e) {
                Log::error("Failed syncing date {$date} in SyncDateRangeJob: " . $e->getMessage());
            }

            $processed++;
        }

        Cache::put($this->syncKey, [
            'status' => 'completed',
            'total_days' => $totalDays,
            'processed_days' => $totalDays,
            'current_date' => null,
            'percent' => 100,
            'completed_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
        ], 86400);

        Log::info("SyncDateRangeJob completed from {$this->dateFrom} to {$this->dateTo}");
    }

    /**
     * Handle job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("SyncDateRangeJob failed: " . $exception->getMessage());
        Cache::put($this->syncKey, [
            'status' => 'failed',
            'error' => $exception->getMessage(),
            'failed_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
        ], 86400);
    }
}
