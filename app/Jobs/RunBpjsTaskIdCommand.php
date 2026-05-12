<?php

namespace App\Jobs;

use App\Services\TaskExecutionTracker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\Console\Output\BufferedOutput;

class RunBpjsTaskIdCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The maximum number of unhandled exceptions to be reported.
     *
     * @var int
     */
    public $maxExceptions = 3;
    
    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 600; // 10 minutes
    
    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1; // Let our internal retry logic handle retries

    protected $options;
    protected $jobId;

    /**
     * Create a new job instance.
     */
    public function __construct(array $options = [], $jobId = null)
    {
        $this->options = $options;
        $this->jobId = $jobId ?? 'bpjs-task-' . uniqid();
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Initialize execution tracker with proper cache structure
            $tracker = new TaskExecutionTracker($this->jobId);
            
            // Initialize tracking data in cache
            Cache::put('task-execution:' . $this->jobId, [
                'job_id' => $this->jobId,
                'bookings' => [],
                'summary' => [
                    'total_bookings' => 0,
                    'task_ids' => [],
                    'completed' => 0,
                    'failed' => 0,
                    'pending' => 0,
                    'started_at' => now()->timezone('Asia/Jakarta')->toIso8601String()
                ]
            ], 3600);
            
            // Get existing cache entry or create a new one
            $cacheEntry = Cache::get('command-output:' . $this->jobId, [
                'output' => []
            ]);
            
            // Update the status to running and maintain any existing output
            $cacheEntry['status'] = 'running';
            $cacheEntry['started_at'] = now()->timezone('Asia/Jakarta')->toIso8601String();
            
            // Add a message that the job is now running
            $cacheEntry['output'][] = "Command started at " . now()->timezone('Asia/Jakarta')->format('Y-m-d H:i:s') . "\n";
            
            // Limit the size of the output array to prevent excessive memory usage
            if (count($cacheEntry['output']) > 100) {
                // Keep only the last 100 entries
                $cacheEntry['output'] = array_slice($cacheEntry['output'], -100);
                $cacheEntry['output'][] = "[Some older output was truncated to conserve memory]";
            }
            
            // Update the cache
            Cache::put('command-output:' . $this->jobId, $cacheEntry, 3600);

            // Build command options
            $commandOptions = [];
            
            if (!empty($this->options['date-from'])) {
                $commandOptions['--date-from'] = $this->options['date-from'];
            } else {
                $commandOptions['--date-from'] = now()->timezone('Asia/Jakarta')->format('Y-m-d');
            }
            
            if (!empty($this->options['date-to'])) {
                $commandOptions['--date-to'] = $this->options['date-to'];
            } else {
                $commandOptions['--date-to'] = now()->timezone('Asia/Jakarta')->format('Y-m-d');
            }
            
            if (!empty($this->options['dry-run'])) {
                $commandOptions['--dry-run'] = true;
            }
            
            if (!empty($this->options['mjkn'])) {
                $commandOptions['--mjkn'] = true;
            }

            // Run the command with retry logic
            $maxAttempts = (int) env('BPJS_TASK_RETRY_MAX', 5);
            $retryInterval = (int) env('BPJS_TASK_RETRY_INTERVAL', 10); // seconds
            $attempt = 0;
            $success = false;
            $combinedOutput = '';

            while (!$success && $attempt < $maxAttempts) {
                $attempt++;
                try {
                    // Set memory and time limits for the Artisan command
                    ini_set('memory_limit', '512M');
                    set_time_limit(300); // 5 minutes
                    
                    $outputBuffer = new BufferedOutput;
                    $startTime = microtime(true);
                    $exitCode = Artisan::call('bpjs:send-task-ids', $commandOptions, $outputBuffer);
                    $duration = microtime(true) - $startTime;
                    
                    $output = $outputBuffer->fetch();
                    
                    // Parse output to track bookings
                    $this->parseAndTrackOutput($output, $tracker, $exitCode, $duration);
                    
                    // Only keep the last 2000 characters of output if it's very large
                    if (strlen($output) > 5000) {
                        $output = "[Output was truncated]...\n" . substr($output, -2000);
                    }
                    
                    $combinedOutput .= "\n--- Attempt {$attempt} ---\n" . $output;

                    Log::info('BPJS Task Command Attempt', [
                        'job_id' => $this->jobId,
                        'attempt' => $attempt,
                        'exit_code' => $exitCode,
                        'output_length' => strlen($output),
                        'duration' => $duration
                    ]);

                    // Store incremental output in cache
                    $currentOutput = Cache::get('command-output:' . $this->jobId, ['output' => []]);
                    
                    // Limit array size
                    if (count($currentOutput['output']) > 20) {
                        // Keep only first entry (start message) and last 19 entries
                        $firstEntry = isset($currentOutput['output'][0]) ? $currentOutput['output'][0] : null;
                        $currentOutput['output'] = array_slice($currentOutput['output'], -19);
                        if ($firstEntry) {
                            array_unshift($currentOutput['output'], $firstEntry);
                            $currentOutput['output'][] = "[Some output was truncated to conserve memory]";
                        }
                    }
                    
                    $currentOutput['output'][] = "Attempt {$attempt}: " . $output;
                    $currentOutput['attempts'] = $attempt;
                    $currentOutput['status'] = $exitCode === 0 ? 'completed' : 'retrying';
                    Cache::put('command-output:' . $this->jobId, $currentOutput, 3600);

                    if ($exitCode === 0) {
                        $success = true;
                        $currentOutput['status'] = 'completed';
                        $currentOutput['completed_at'] = now()->timezone('Asia/Jakarta')->toIso8601String();
                        $currentOutput['exit_code'] = $exitCode;
                        Cache::put('command-output:' . $this->jobId, $currentOutput, 3600);
                        break;
                    }

                    // If not successful and we have more attempts, wait then retry
                    if ($attempt < $maxAttempts) {
                        sleep($retryInterval);
                    }
                } catch (\Exception $e) {
                    Log::error('Error running BPJS task command (attempt '.$attempt.')', [
                        'job_id' => $this->jobId,
                        'error' => $e->getMessage(),
                        'trace' => $e->getTraceAsString()
                    ]);

                    $currentOutput = Cache::get('command-output:' . $this->jobId, ['output' => []]);
                    
                    // Limit output size here too
                    if (count($currentOutput['output']) > 20) {
                        $firstEntry = isset($currentOutput['output'][0]) ? $currentOutput['output'][0] : null;
                        $currentOutput['output'] = array_slice($currentOutput['output'], -19);
                        if ($firstEntry) {
                            array_unshift($currentOutput['output'], $firstEntry);
                            $currentOutput['output'][] = "[Some output was truncated to conserve memory]";
                        }
                    }
                    
                    $currentOutput['output'][] = "Attempt {$attempt} Exception: " . $e->getMessage();
                    $currentOutput['attempts'] = $attempt;
                    $currentOutput['status'] = 'retrying';
                    Cache::put('command-output:' . $this->jobId, $currentOutput, 3600);
                    
                    // Track failure
                    $tracker->failBooking('SYSTEM', 'Error: ' . $e->getMessage());

                    if ($attempt < $maxAttempts) {
                        sleep($retryInterval);
                    }
                }
            }

            if (!$success) {
                $finalOutput = Cache::get('command-output:' . $this->jobId, ['output' => []]);
                $finalOutput['status'] = 'failed';
                $finalOutput['completed_at'] = now()->timezone('Asia/Jakarta')->toIso8601String();
                $finalOutput['exit_code'] = $exitCode ?? 1;
                Cache::put('command-output:' . $this->jobId, $finalOutput, 3600);

                Log::error('BPJS Task Command failed after retries', ['job_id' => $this->jobId, 'attempts' => $attempt]);
            }
        } catch (\Exception $e) {
            // Catch any exceptions that might occur outside the inner try-catch
            Log::critical('Critical error in BPJS task job', [
                'job_id' => $this->jobId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Update cache with the failure
            $finalOutput = Cache::get('command-output:' . $this->jobId, ['output' => []]);
            $finalOutput['status'] = 'error';
            $finalOutput['completed_at'] = now()->timezone('Asia/Jakarta')->toIso8601String();
            $finalOutput['error'] = $e->getMessage();
            Cache::put('command-output:' . $this->jobId, $finalOutput, 3600);
            
            // Re-throw to let Laravel handle job failure
            throw $e;
        }
    }

    /**
     * Parse the command output and track execution details.
     *
     * @param string $output Command output
     * @param TaskExecutionTracker $tracker
     * @param int $exitCode
     * @param float $duration
     * @return void
     */
    private function parseAndTrackOutput($output, $tracker, $exitCode, $duration)
    {
        // Try to parse output for booking information
        // This is a generic parser - adjust based on your actual command output format
        
        $lines = explode("\n", $output);
        $noRawat = null;
        $taskId = null;
        
        foreach ($lines as $line) {
            // Look for booking number (adjust pattern based on your output)
            if (preg_match('/no_rawat[:\s]+([A-Z0-9]+)/i', $line, $matches)) {
                $noRawat = $matches[1];
            }
            
            // Look for task ID (adjust pattern based on your output)
            if (preg_match('/task[_\s]?id[:\s]+(\d+)/i', $line, $matches)) {
                $taskId = (int) $matches[1];
            }
            
            // Look for status indicators
            if ($noRawat && $taskId) {
                $status = 'pending';
                
                if (strpos($line, 'success') !== false || strpos($line, 'completed') !== false) {
                    $status = 'completed';
                } elseif (strpos($line, 'error') !== false || strpos($line, 'failed') !== false) {
                    $status = 'failed';
                } elseif (strpos($line, 'processing') !== false) {
                    $status = 'processing';
                }
                
                if ($status !== 'pending') {
                    $tracker->recordStep($noRawat, $taskId, $status, trim($line), (int)$duration);
                    $noRawat = null;
                    $taskId = null;
                }
            }
        }
    }

    /**
     * Get the job ID.
     */
    public function getJobId(): string
    {
        return $this->jobId;
    }
    
    /**
     * Handle a job failure.
     *
     * @param \Throwable $exception
     * @return void
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('BPJS task job failed', [
            'job_id' => $this->jobId,
            'error' => $exception->getMessage()
        ]);
        
        $finalOutput = Cache::get('command-output:' . $this->jobId, ['output' => []]);
        $finalOutput['status'] = 'failed';
        $finalOutput['completed_at'] = now()->timezone('Asia/Jakarta')->toIso8601String();
        $finalOutput['error'] = $exception->getMessage();
        Cache::put('command-output:' . $this->jobId, $finalOutput, 3600);
    }
}
