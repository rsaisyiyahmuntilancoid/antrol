<?php

namespace App\Services;

use App\Models\BpjsWsRsLog;
use Illuminate\Support\Facades\Log;

class BpjsLogService
{
    /**
     * Log a BPJS web service request/response
     *
     * @param int $code HTTP status code
     * @param string $request The request data
     * @param string $message Response message
     * @param string $url The API endpoint URL
     * @param string $method HTTP method (GET, POST, etc.)
     * @param array $extra Optional new schema fields (operation, response, norm, nokartu, etc.)
     * @return bool
     */
    public function logRequest(
        int $code,
        string $request,
        string $message,
        string $url,
        string $method = 'GET',
        array $extra = []
    ): bool {
        try {
            $isSuccess = in_array($code, [200, 201, 204]);
            $operation = $extra['operation'] ?? null;
            $endpoint = $extra['endpoint'] ?? null;
            $response = $extra['response'] ?? null;
            $statusCode = $extra['status_code'] ?? $code;
            $httpStatus = $extra['http_status'] ?? $code;
            $errorType = $extra['error_type'] ?? null;
            $bpjsMetaCode = $extra['bpjs_meta_code'] ?? null;
            $bpjsMetaMessage = $extra['bpjs_meta_message'] ?? null;
            $norm = $extra['norm'] ?? null;
            $nokartu = $extra['nokartu'] ?? null;
            $noSep = $extra['no_sep'] ?? null;
            $noRujukan = $extra['no_rujukan'] ?? null;
            $exception = $extra['exception'] ?? null;

            // Auto-detect operation and endpoint if not provided
            if (!$operation && !empty($url)) {
                if (str_contains($url, '/antrean/updatewaktu')) {
                    $operation = 'updatewaktu';
                    $endpoint = '/antrean/updatewaktu';
                } elseif (str_contains($url, '/antrean/add')) {
                    $operation = 'add_antrean';
                    $endpoint = '/antrean/add';
                } elseif (str_contains($url, '/antrean/batal')) {
                    $operation = 'batal_antrean';
                    $endpoint = '/antrean/batal';
                } elseif (str_contains($url, '/antrean/getlisttask')) {
                    $operation = 'listtask';
                    $endpoint = '/antrean/getlisttask';
                } elseif (str_contains($url, '/antrean/pendaftaran')) {
                    $operation = 'get_booking';
                    $endpoint = '/antrean/pendaftaran/kodebooking';
                } elseif (str_contains($url, '/antrean/dashboard')) {
                    $operation = 'dashboard';
                    $endpoint = '/antrean/dashboard/pertanggal';
                }
            }

            $data = [
                'request_id' => $extra['request_id'] ?? (string) \Illuminate\Support\Str::uuid(),
                'status' => $extra['status'] ?? ($isSuccess ? 'success' : 'fail'),
                'code' => $code,
                'request' => $request,
                'message' => substr($message, 0, 1000),
                'url' => substr($url, 0, 1000),
                'method' => $method,
                'created_at' => now(),
            ];

            // Add new schema fields if available
            if ($operation !== null) $data['operation'] = $operation;
            if ($endpoint !== null) $data['endpoint'] = substr($endpoint, 0, 255);
            if ($response !== null) $data['response'] = $response;
            if ($statusCode !== null) $data['status_code'] = $statusCode;
            if ($httpStatus !== null) $data['http_status'] = $httpStatus;
            if ($errorType !== null) $data['error_type'] = substr($errorType, 0, 50);
            if ($bpjsMetaCode !== null) $data['bpjs_meta_code'] = substr((string)$bpjsMetaCode, 0, 50);
            if ($bpjsMetaMessage !== null) $data['bpjs_meta_message'] = $bpjsMetaMessage;
            if ($norm !== null) $data['norm'] = substr($norm, 0, 30);
            if ($nokartu !== null) $data['nokartu'] = substr($nokartu, 0, 30);
            if ($noSep !== null) $data['no_sep'] = substr($noSep, 0, 50);
            if ($noRujukan !== null) $data['no_rujukan'] = substr($noRujukan, 0, 50);
            if ($exception !== null) $data['exception'] = $exception;

            BpjsWsRsLog::create($data);

            return true;
        } catch (\Exception $e) {
            // If it failed because a column is missing (e.g. before migration runs in prod), retry with base columns
            if (str_contains($e->getMessage(), 'Unknown column') || str_contains($e->getMessage(), "doesn't have a default value")) {
                try {
                    BpjsWsRsLog::create([
                        'request_id' => (string) \Illuminate\Support\Str::uuid(),
                        'status' => in_array($code, [200, 201, 204]) ? 'success' : 'fail',
                        'code' => $code,
                        'request' => substr($request, 0, 1000),
                        'message' => substr($message, 0, 1000),
                        'url' => substr($url, 0, 1000),
                        'method' => $method,
                        'created_at' => now(),
                    ]);
                    return true;
                } catch (\Exception $fallbackEx) {
                    Log::error('Failed to log BPJS request fallback: ' . $fallbackEx->getMessage());
                }
            }

            Log::error('Failed to log BPJS request to database: ' . $e->getMessage(), [
                'code' => $code,
                'request' => $request,
                'message' => $message,
                'url' => $url,
                'method' => $method,
            ]);

            return false;
        }
    }

    /**
     * Get recent BPJS logs
     *
     * @param int $limit Number of records to retrieve
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getRecentLogs(int $limit = 100): \Illuminate\Database\Eloquent\Collection
    {
        return BpjsWsRsLog::orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsByDateRange(string $startDate, string $endDate): \Illuminate\Database\Eloquent\Collection
    {
        return BpjsWsRsLog::whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * Get logs by HTTP status code
     *
     * @param int $code
     * @param int $limit
     * @return \Illuminate\Database\Eloquent\Collection
     */
    public function getLogsByCode(int $code, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return BpjsWsRsLog::where('code', $code)
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get logs by task ID and no_rawat (improved search)
     *
     * @param string $noRawat
     * @param int $taskId
     * @return BpjsWsRsLog|null
     */
    public function getLogByTaskAndNoRawat(string $noRawat, int $taskId): ?BpjsWsRsLog
    {
        // First try exact match with both taskid and kodebooking
        $log = BpjsWsRsLog::where('request', 'like', '%"taskid": "' . $taskId . '"%')
            ->where('request', 'like', '%"kodebooking": "' . $noRawat . '"%')
            ->orderBy('created_at', 'desc')
            ->first();

        // If not found, try with nomorantrean (queue number) which might be used instead
        if (!$log) {
            $log = BpjsWsRsLog::where('request', 'like', '%"nomorantrean":"' . $noRawat . '"%')
                ->where('request', 'like', '%"taskid": "' . $taskId . '"%')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        // If still not found, try broader search for kodebooking in any field
        if (!$log) {
            $log = BpjsWsRsLog::where('request', 'like', '%' . $noRawat . '%')
                ->where('request', 'like', '%"taskid": "' . $taskId . '"%')
                ->orderBy('created_at', 'desc')
                ->first();
        }

        return $log;
    }

    /**
     * Get logs by booking code only (for operations without task ID)
     *
     * @param string $bookingCode
     * @return BpjsWsRsLog|null
     */
    public function getLogByBookingCode(string $bookingCode): ?BpjsWsRsLog
    {
        return BpjsWsRsLog::where('request', 'like', '%"kodebooking": "' . $bookingCode . '"%')
            ->orderBy('created_at', 'desc')
            ->first();
    }

    /**
     * Get aggregate statistics for Task ID logs and Antrean Add logs
     * Fast single-query aggregation on indexed operation column with fallback
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array
     */
    public function getTaskIdStats(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?? now()->subDays(30)->toDateString();
        $dateTo = $dateTo ?? now()->toDateString();

        try {
            $stats = BpjsWsRsLog::whereBetween('created_at', [
                    "{$dateFrom} 00:00:00",
                    "{$dateTo} 23:59:59"
                ])
                ->whereIn('operation', ['updatewaktu', 'add_antrean'])
                ->selectRaw("
                    COALESCE(SUM(CASE WHEN operation = 'updatewaktu' AND code BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as task_success,
                    COALESCE(SUM(CASE WHEN operation = 'updatewaktu' AND code NOT BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as task_error,
                    COALESCE(SUM(CASE WHEN operation = 'updatewaktu' THEN 1 ELSE 0 END), 0) as task_total,
                    COALESCE(SUM(CASE WHEN operation = 'add_antrean' AND code BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as antrean_success,
                    COALESCE(SUM(CASE WHEN operation = 'add_antrean' AND code NOT BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as antrean_error,
                    COALESCE(SUM(CASE WHEN operation = 'add_antrean' THEN 1 ELSE 0 END), 0) as antrean_total
                ")
                ->first();

            $result = $stats ? $stats->toArray() : [];

            // If task_total and antrean_total are both 0, check if operation column has no rows yet (pre-migration)
            if (($result['task_total'] ?? 0) == 0 && ($result['antrean_total'] ?? 0) == 0) {
                // Fallback query using url like but scoped to date range
                $fallback = BpjsWsRsLog::whereBetween('created_at', [
                        "{$dateFrom} 00:00:00",
                        "{$dateTo} 23:59:59"
                    ])
                    ->selectRaw("
                        COALESCE(SUM(CASE WHEN url LIKE '%/antrean/updatewaktu%' AND code BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as task_success,
                        COALESCE(SUM(CASE WHEN url LIKE '%/antrean/updatewaktu%' AND code NOT BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as task_error,
                        COALESCE(SUM(CASE WHEN url LIKE '%/antrean/updatewaktu%' THEN 1 ELSE 0 END), 0) as task_total,
                        COALESCE(SUM(CASE WHEN url LIKE '%/antrean/add%' AND code BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as antrean_success,
                        COALESCE(SUM(CASE WHEN url LIKE '%/antrean/add%' AND code NOT BETWEEN 200 AND 299 THEN 1 ELSE 0 END), 0) as antrean_error,
                        COALESCE(SUM(CASE WHEN url LIKE '%/antrean/add%' THEN 1 ELSE 0 END), 0) as antrean_total
                    ")
                    ->first();
                if ($fallback) {
                    $result = $fallback->toArray();
                }
            }

            return [
                'task_success' => (int) ($result['task_success'] ?? 0),
                'task_error' => (int) ($result['task_error'] ?? 0),
                'task_total' => (int) ($result['task_total'] ?? 0),
                'antrean_success' => (int) ($result['antrean_success'] ?? 0),
                'antrean_error' => (int) ($result['antrean_error'] ?? 0),
                'antrean_total' => (int) ($result['antrean_total'] ?? 0),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
        } catch (\Exception $e) {
            Log::error('Error fetching task ID stats: ' . $e->getMessage());
            return [
                'task_success' => 0,
                'task_error' => 0,
                'task_total' => 0,
                'antrean_success' => 0,
                'antrean_error' => 0,
                'antrean_total' => 0,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ];
        }
    }

    /**
     * Get task ID related logs (for updatewaktu API calls)
     *
     * @param int $limit Number of records to retrieve
     * @param int $page Page number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTaskIdLogs(int $limit = 50, int $page = 1)
    {
        try {
            $paginator = BpjsWsRsLog::where('operation', 'updatewaktu')
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            if ($paginator->total() > 0) {
                return $paginator;
            }
        } catch (\Exception $e) {
            // column operation might not exist yet
        }

        return BpjsWsRsLog::where('url', 'like', '%/antrean/updatewaktu%')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Filter task ID logs by date range
     *
     * @param string $startDate
     * @param string $endDate
     * @param int $limit Number of records to retrieve
     * @param int $page Page number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function filterTaskIdLogs(string $startDate, string $endDate, int $limit = 50, int $page = 1)
    {
        try {
            $paginator = BpjsWsRsLog::where('operation', 'updatewaktu')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            if ($paginator->total() > 0) {
                return $paginator;
            }
        } catch (\Exception $e) {
            // column operation might not exist yet
        }

        return BpjsWsRsLog::where('url', 'like', '%/antrean/updatewaktu%')
            ->whereBetween('created_at', [$startDate, $endDate])
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }

    /**
     * Get antrean add logs (for adding antrean)
     *
     * @param int $limit Number of records to retrieve
     * @param int $page Page number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAntreanAddLogs(int $limit = 50, int $page = 1)
    {
        try {
            $paginator = BpjsWsRsLog::where('operation', 'add_antrean')
                ->orderBy('created_at', 'desc')
                ->paginate($limit, ['*'], 'page', $page);

            if ($paginator->total() > 0) {
                return $paginator;
            }
        } catch (\Exception $e) {
            // column operation might not exist yet
        }

        return BpjsWsRsLog::where('url', 'like', '%/antrean/add%')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }
}
