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
     * @return bool
     */
    public function logRequest(int $code, string $request, string $message, string $url, string $method = 'GET'): bool
    {
        try {
            BpjsWsRsLog::create([
                'request_id' => (string) \Illuminate\Support\Str::uuid(),
                'status' => in_array($code, [200, 201, 204]) ? 'success' : 'fail',
                'code' => $code,
                'request' => substr($request, 0, 1000), // Limit to 1000 characters
                'message' => substr($message, 0, 1000), // Limit to 1000 characters
                'url' => substr($url, 0, 1000), // Limit to 1000 characters
                'method' => $method,
                'created_at' => now(),
            ]);

            return true;
        } catch (\Exception $e) {
            // Fallback to Laravel log if database logging fails
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
     * Get task ID related logs (for updatewaktu API calls)
     *
     * @param int $limit Number of records to retrieve
     * @param int $page Page number
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getTaskIdLogs(int $limit = 50, int $page = 1)
    {
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
        return BpjsWsRsLog::where('url', 'like', '%/antrean/add%')
            ->orderBy('created_at', 'desc')
            ->paginate($limit, ['*'], 'page', $page);
    }
}
