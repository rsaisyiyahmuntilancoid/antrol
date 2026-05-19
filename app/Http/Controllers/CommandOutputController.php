<?php

namespace App\Http\Controllers;

use App\Jobs\RunBpjsTaskIdCommand;
use App\Models\ReferensiMobilejknBpjsTaskid;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Log;

class CommandOutputController extends Controller
{
    /**
     * Show the form to run the BPJS task ID command.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('mobilejkn.command-runner');
    }

    /**
     * Run the BPJS task ID command.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function runCommand(Request $request)
    {
        try {
            // Log incoming request
            Log::debug('runCommand called', [
                'method' => $request->method(),
                'path' => $request->path(),
                'has_content_type' => $request->hasHeader('Content-Type'),
                'content_type' => $request->header('Content-Type'),
                'all_data' => $request->all()
            ]);

            // Validate input
            try {
                $request->validate([
                    'date_from' => 'nullable|date',
                    'date_to' => 'nullable|date',
                    'dry_run' => 'nullable|boolean',
                    'mjkn' => 'nullable|boolean',
                    'all' => 'nullable|boolean',
                ]);
            } catch (\Exception $validationError) {
                Log::warning('Validation failed', ['error' => $validationError->getMessage()]);
                return response()->json([
                    'status' => 'error',
                    'error' => 'Validation failed: ' . $validationError->getMessage()
                ], 422);
            }

            // Create options array for the command
            $options = [
                'date-from' => $request->date_from ?? null,
                'date-to' => $request->date_to ?? null,
                'dry-run' => $request->has('dry_run') ? (bool)$request->dry_run : false,
                'mjkn' => $request->has('mjkn') ? (bool)$request->mjkn : false,
                'all' => $request->has('all') ? (bool)$request->all : false,
            ];

            // Create a unique job ID
            $jobId = 'bpjs-task-' . uniqid();
            
            Log::info('Creating job', ['job_id' => $jobId, 'options' => $options]);
            
            // Initialize the cache entry for immediate access
            Cache::put('command-output:' . $jobId, [
                'status' => 'pending',
                'output' => ['Job initialized, waiting to start...'],
                'started_at' => now()->toIso8601String(),
            ], 3600);

            Log::debug('Cache entry created', ['job_id' => $jobId]);

            // Create the job
            $job = new RunBpjsTaskIdCommand($options, $jobId);
            
            Log::debug('Job instance created', ['job_id' => $jobId]);
            
            // Dispatch the job with explicit queue information
            dispatch($job)->onQueue('default');
            
            Log::info('BPJS Task Command dispatched successfully', [
                'job_id' => $jobId,
                'options' => $options
            ]);
            
            return response()->json([
                'status' => 'started',
                'job_id' => $jobId,
                'queue_info' => [
                    'message' => 'Job dispatched to queue. If it stays in "pending" state, ensure queue workers are running with: php artisan queue:work',
                ]
            ], 200);
        } catch (\Exception $e) {
            // Log the full error for debugging
            Log::error('Error in runCommand', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            // Create a job ID for error tracking
            $jobId = 'bpjs-task-' . uniqid();
            
            // Store error in cache
            Cache::put('command-output:' . $jobId, [
                'status' => 'failed',
                'error' => $e->getMessage(),
                'output' => ['Error: ' . $e->getMessage()],
                'started_at' => now()->toIso8601String(),
            ], 3600);
            
            return response()->json([
                'status' => 'error',
                'job_id' => $jobId,
                'error' => $e->getMessage(),
                'debug_info' => env('APP_DEBUG') ? $e->getTraceAsString() : null
            ], 500);
        }
    }

    /**
     * Get the command output for a specific job ID.
     *
     * @param  string  $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getOutput($jobId)
    {
        $output = Cache::get('command-output:' . $jobId);

        if (!$output) {
            // Initialize a default response if cache entry doesn't exist
            $output = [
                'status' => 'initializing',
                'output' => ['Waiting for job to start...'],
                'started_at' => now()->toIso8601String(),
            ];
            
            // Store it for future requests
            Cache::put('command-output:' . $jobId, $output, 3600);
        }
        
        // If the job has been pending for more than 30 seconds, check queue status
        if ($output['status'] === 'pending' || $output['status'] === 'initializing') {
            $startedAt = \Carbon\Carbon::parse($output['started_at']);
            $now = \Carbon\Carbon::now();
            
            if ($now->diffInSeconds($startedAt) > 30) {
                // Check if queue workers are running
                $queueStatus = $this->checkQueueStatus($jobId);
                
                // Add queue status to the output
                $output['queue_status'] = $queueStatus;
                
                // Add warning about queue workers if needed
                if (isset($queueStatus['suggestion'])) {
                    $output['output'][] = "\n⚠️ " . $queueStatus['message'] . "\n";
                    $output['output'][] = "💡 " . $queueStatus['suggestion'] . "\n";
                }
            }
        }

        return response()->json($output);
    }

    /**
     * Get task IDs being sent in the current batch.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getTaskIds(Request $request)
    {
        try {
            $request->validate([
                'date_from' => 'nullable|date',
                'date_to' => 'nullable|date',
            ]);

            $dateFrom = $request->date_from ? \Carbon\Carbon::parse($request->date_from)->startOfDay() : now()->startOfDay();
            $dateTo = $request->date_to ? \Carbon\Carbon::parse($request->date_to)->endOfDay() : now()->endOfDay();

            Log::debug('getTaskIds called', [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString()
            ]);

            // Get all task IDs for the date range - without modification
            $taskIds = ReferensiMobilejknBpjsTaskid::whereBetween('waktu', [$dateFrom, $dateTo])
                ->orderBy('taskid')
                ->distinct('taskid')
                ->pluck('taskid')
                ->values()
                ->toArray();

            Log::debug('Task IDs retrieved', ['count' => count($taskIds), 'task_ids' => $taskIds]);

            return response()->json([
                'task_ids' => $taskIds,
                'date_range' => [
                    'from' => $dateFrom->toDateString(),
                    'to' => $dateTo->toDateString()
                ]
            ], 200);
        } catch (\Exception $e) {
            Log::error('Error in getTaskIds', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'task_ids' => [],
                'error' => $e->getMessage()
            ], 200); // Return 200 even on error to prevent UI issues
        }
    }

    /**
     * Ensure that task IDs 1-5 exist for the given date range.
     * Task ID 5 is automatically created based on Task ID 4 + 10-15 minutes.
     *
     * @param  \Carbon\Carbon  $dateFrom
     * @param  \Carbon\Carbon  $dateTo
     * @return void
     */
    private function ensureTaskIds($dateFrom, $dateTo)
    {
        try {
            Log::debug('ensureTaskIds called', [
                'date_from' => $dateFrom->toDateString(),
                'date_to' => $dateTo->toDateString()
            ]);

            // Get records with task ID 4 that don't have task ID 5
            $task4Records = ReferensiMobilejknBpjsTaskid::where('taskid', 4)
                ->whereBetween('waktu', [$dateFrom, $dateTo])
                ->cursor(); // Use cursor to avoid loading all at once

            $createdCount = 0;
            foreach ($task4Records as $task4Record) {
                $noRawat = $task4Record->no_rawat;
                
                // Check if task ID 5 already exists for this record
                $existingTask5 = ReferensiMobilejknBpjsTaskid::where('no_rawat', $noRawat)
                    ->where('taskid', 5)
                    ->first();

                if (!$existingTask5) {
                    // Create task ID 5 with time 10-15 minutes after task ID 4
                    $task4Time = \Carbon\Carbon::parse($task4Record->waktu);
                    $task5Time = $task4Time->addMinutes(rand(10, 15));

                    try {
                        ReferensiMobilejknBpjsTaskid::create([
                            'no_rawat' => $noRawat,
                            'taskid' => 5,
                            'waktu' => $task5Time,
                        ]);
                        $createdCount++;
                    } catch (\Exception $e) {
                        Log::warning('Failed to create task ID 5', [
                            'no_rawat' => $noRawat,
                            'error' => $e->getMessage()
                        ]);
                    }
                }
            }

            Log::debug('ensureTaskIds completed', ['created_count' => $createdCount]);
        } catch (\Exception $e) {
            Log::error('Error in ensureTaskIds', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }
    }

    /**
     * Stop a running command.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function stopCommand(Request $request)
    {
        $request->validate([
            'job_id' => 'required|string'
        ]);

        $jobId = $request->job_id;
        $cacheKey = 'command-output:' . $jobId;
        
        // Check if the job exists
        $output = Cache::get($cacheKey);
        if (!$output) {
            return response()->json([
                'success' => false,
                'message' => 'Job not found'
            ], 404);
        }
        
        // Update the cache entry to indicate the command was stopped
        $output['status'] = 'stopped';
        $output['stopped_at'] = now()->toIso8601String();
        $output['output'][] = "\n\n[Command manually stopped by user]";
        Cache::put($cacheKey, $output, 3600);
        
        // Try to find and terminate the job
        // Note: This is a basic implementation, actual job termination might require
        // queue worker configuration or direct process termination
        try {
            // Log the stop request
            Log::info('Command stop requested', [
                'job_id' => $jobId,
                'user_id' => auth()->id() ?? 'unauthenticated',
                'timestamp' => now()->toIso8601String()
            ]);
            
            return response()->json([
                'success' => true,
                'message' => 'Command marked as stopped'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error stopping command: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Debug method to check cache entries.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function debugCache($jobId = null)
    {
        if ($jobId) {
            $output = Cache::get('command-output:' . $jobId);
            return response()->json([
                'job_id' => $jobId,
                'cache_entry' => $output
            ]);
        }
        
        // We can't easily list all cache keys in Laravel
        // Just indicate that the cache debug function was called
        $keys = [
            'debug_accessed_at' => now()->toIso8601String(),
            'message' => 'Individual cache keys cannot be listed, please provide a specific job ID'
        ];
        
        return response()->json([
            'cache_keys' => $keys
        ]);
    }

    /**
     * Check if a job is actually running or still in the queue.
     * This helps identify if there might be an issue with queue workers.
     *
     * @param  string  $jobId
     * @return array
     */
    private function checkQueueStatus($jobId)
    {
        $result = [
            'queue_status' => 'unknown',
            'message' => 'Queue status could not be determined'
        ];

        try {
            // Check if the Laravel queue worker is running
            // This command varies depending on your queue configuration
            $queueProcessCount = 0;
            
            if (function_exists('exec')) {
                exec('ps aux | grep "queue:work\|queue:listen" | grep -v grep | wc -l', $output);
                if (!empty($output[0])) {
                    $queueProcessCount = (int)$output[0];
                }
            }
            
            $result['queue_workers_running'] = $queueProcessCount > 0;
            
            if ($queueProcessCount === 0) {
                $result['queue_status'] = 'no_workers';
                $result['message'] = 'No queue workers appear to be running. Jobs may not be processed.';
                $result['suggestion'] = 'Run "php artisan queue:work" in your terminal to start processing jobs.';
            } else {
                $result['queue_status'] = 'workers_running';
                $result['message'] = 'Queue workers are running. If jobs are stuck in "pending", check for errors in the worker output.';
            }
        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    /**
     * Show the log viewer page.
     *
     * @return \Illuminate\View\View
     */
    public function showLogViewer()
    {
        return view('mobilejkn.log-viewer');
    }

    /**
     * Stream Laravel logs in real-time using Server-Sent Events (SSE).
     *
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamLogs()
    {
        $logFile = storage_path('logs/laravel.log');

        $response = response()->stream(function () use ($logFile) {
            // Keep track of the last read position
            $lastPosition = Cache::get('log-stream:position', 0);
            
            // Read file in chunks
            $handle = fopen($logFile, 'r');
            
            if (!$handle) {
                echo "event: error\n";
                echo "data: " . json_encode(['message' => 'Unable to open log file']) . "\n\n";
                return;
            }

            // Seek to last position
            fseek($handle, $lastPosition);

            // Read new lines
            $newLines = [];
            while (($line = fgets($handle)) !== false) {
                $newLines[] = $line;
            }

            // Save current position
            $newPosition = ftell($handle);
            Cache::put('log-stream:position', $newPosition, now()->addDay());

            // Send new lines
            foreach ($newLines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                // Parse log line for color coding
                $type = 'info';
                if (strpos($line, '[ERROR]') !== false || strpos($line, 'error') !== false) {
                    $type = 'error';
                } elseif (strpos($line, '[WARNING]') !== false || strpos($line, 'warning') !== false) {
                    $type = 'warning';
                } elseif (strpos($line, '[DEBUG]') !== false || strpos($line, 'debug') !== false) {
                    $type = 'debug';
                }

                echo "event: log\n";
                echo "data: " . json_encode([
                    'line' => $line,
                    'type' => $type,
                    'timestamp' => now()->toIso8601String()
                ]) . "\n\n";

                // Flush output to send immediately
                echo "\n\n";
                ob_flush();
                flush();
            }

            fclose($handle);

            // Send keep-alive
            echo "event: keep-alive\n";
            echo "data: " . json_encode(['timestamp' => now()->toIso8601String()]) . "\n\n";

        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
            'Access-Control-Allow-Origin' => '*',
        ]);

        return $response;
    }

    /**
     * Get recent log lines as JSON.
     *
     * @param  int  $lines
     * @return \Illuminate\Http\JsonResponse
     */
    public function getRecentLogs($lines = 50)
    {
        try {
            $logFile = storage_path('logs/laravel.log');
            
            if (!file_exists($logFile)) {
                return response()->json(['logs' => [], 'error' => 'Log file not found']);
            }

            // Read last N lines from file
            $file = file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            $recentLines = array_slice($file, max(0, count($file) - $lines));

            $logs = [];
            foreach ($recentLines as $line) {
                $line = trim($line);
                if (empty($line)) continue;

                $type = 'info';
                if (strpos($line, '[ERROR]') !== false || strpos($line, 'error') !== false) {
                    $type = 'error';
                } elseif (strpos($line, '[WARNING]') !== false || strpos($line, 'warning') !== false) {
                    $type = 'warning';
                } elseif (strpos($line, '[DEBUG]') !== false || strpos($line, 'debug') !== false) {
                    $type = 'debug';
                }

                $logs[] = [
                    'line' => $line,
                    'type' => $type,
                ];
            }

            return response()->json(['logs' => $logs]);
        } catch (\Exception $e) {
            return response()->json(['logs' => [], 'error' => $e->getMessage()]);
        }
    }

    /**
     * Show execution details viewer page.
     *
     * @param  string  $jobId
     * @return \Illuminate\View\View
     */
    public function showExecutionViewer($jobId)
    {
        return view('mobilejkn.execution-details', ['jobId' => $jobId]);
    }

    /**
     * Get detailed task execution logs with tracking per booking and task ID.
     *
     * @param  string  $jobId
     * @return \Illuminate\Http\JsonResponse
     */
    public function getDetailedExecution($jobId)
    {
        try {
            $cacheKey = 'task-execution:' . $jobId;
            $executionData = Cache::get($cacheKey, [
                'bookings' => [],
                'summary' => [
                    'total_bookings' => 0,
                    'completed' => 0,
                    'failed' => 0,
                    'pending' => 0,
                ]
            ]);

            return response()->json($executionData, 200);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'bookings' => [],
                'summary' => []
            ], 200);
        }
    }

    /**
     * Get real-time task execution updates via SSE.
     *
     * @param  string  $jobId
     * @return \Symfony\Component\HttpFoundation\StreamedResponse
     */
    public function streamTaskExecution($jobId)
    {
        $response = response()->stream(function () use ($jobId) {
            $lastHash = null;
            $timeout = now()->addSeconds(300); // 5 minute timeout

            while (now()->isBefore($timeout)) {
                $cacheKey = 'task-execution:' . $jobId;
                $executionData = Cache::get($cacheKey, []);
                
                $currentHash = hash('md5', json_encode($executionData));
                
                if ($currentHash !== $lastHash) {
                    $lastHash = $currentHash;
                    echo "event: execution\n";
                    echo "data: " . json_encode($executionData) . "\n\n";
                }

                // Send keep-alive every 10 seconds
                sleep(1);
                echo "event: keep-alive\n";
                echo "data: " . json_encode(['timestamp' => now()->toIso8601String()]) . "\n\n";
            }
        }, 200, [
            'Content-Type' => 'text/event-stream',
            'Cache-Control' => 'no-cache',
            'Connection' => 'keep-alive',
            'X-Accel-Buffering' => 'no',
        ]);

        return $response;
    }

}