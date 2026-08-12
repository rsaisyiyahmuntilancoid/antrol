<?php

namespace App\Http\Controllers;

use App\Services\MobileJknService;
use App\Services\BpjsLogService;
use App\Models\BpjsWsRsLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Illuminate\Contracts\View\Factory;
use App\Http\Requests\UpdateTaskIdRequest;
use App\Http\Requests\UpdateTaskIdFromDbRequest;
use App\Http\Requests\UpdateTaskIdNowRequest;
use App\Http\Requests\BatchUpdateTaskIdsRequest;
use App\Http\Requests\BatalAntreanRequest;
use App\Http\Requests\FilteredTaskIdLogsRequest;
use App\Http\Requests\GetTaskIdLogsRequest;
use App\Http\Requests\ReferensiPendaftaranFilterRequest;
use App\Http\Requests\UpdateTaskIdByNoRawatRequest;
use App\Http\Resources\TaskIdLogResource;
use App\Http\Resources\ApiSuccessResource;

class MobileJknController extends Controller
{
    protected $mobileJknService;
    protected $bpjsLogService;

    public function __construct(MobileJknService $mobileJknService, BpjsLogService $bpjsLogService)
    {
        $this->mobileJknService = $mobileJknService;
        $this->bpjsLogService = $bpjsLogService;
    }

    /**
     * Update task ID for a booking
     *
     * @param UpdateTaskIdRequest $request
     * @return ApiSuccessResource|JsonResponse
     */
    public function updateTaskId(UpdateTaskIdRequest $request): ApiSuccessResource|JsonResponse
    {
        $kodebooking = $request->input('kodebooking');
        $taskid = (int)$request->input('taskid');

        try {
            if ($taskid === 99) {
                $this->mobileJknService->batalAntrean($kodebooking, 'Dibatalkan Oleh Admin');
            }

            $result = $this->mobileJknService->updateTaskId(
                $kodebooking,
                $taskid,
                null
            );
            
            return (new ApiSuccessResource(
                $result['data'] ?? $result['response'] ?? null,
                $result['error'] ?? $result['metadata']['message'] ?? $result['message'] ?? 'Success'
            ))->additional([
                'batal' => $result['batal']['data'] ?? null
            ]);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Update task ID for a booking by no_rawat (Khusus Postman)
     *
     * @param UpdateTaskIdByNoRawatRequest $request
     * @return ApiSuccessResource|JsonResponse
     */
    public function updateTaskIdByNoRawat(UpdateTaskIdByNoRawatRequest $request): ApiSuccessResource|JsonResponse
    {
        $noRawat = $request->input('no_rawat');
        $taskidInput = $request->input('taskid');
        $waktu = $request->input('waktu');
        
        $taskids = is_array($taskidInput) ? $taskidInput : [(int)$taskidInput];

        try {
            $kodebooking = $this->mobileJknService->getKodeBooking($noRawat);

            $results = [];
            $lastResult = null;

            foreach ($taskids as $taskid) {
                $taskid = (int)$taskid;

                if ($waktu) {
                    $res = $this->mobileJknService->updateTaskId($kodebooking, $taskid, (string)$waktu);
                } else {
                    // Default to picking up time from DB if waktu is not provided
                    $res = $this->mobileJknService->updateTaskIdFromDatabase($kodebooking, $taskid);
                }

                $results[] = [
                    'taskid' => $taskid,
                    'response' => $res['data'] ?? $res['response'] ?? null,
                    'message' => $res['error'] ?? $res['metadata']['message'] ?? $res['message'] ?? 'Success'
                ];
                $lastResult = $res;
            }
            
            // Jika array taskid lebih dari 1, kembalikan response array. Jika 1, gunakan format awal.
            if (count($taskids) > 1) {
                return new ApiSuccessResource($results, "Berhasil memproses " . count($taskids) . " task ID");
            } else {
                return (new ApiSuccessResource(
                    $lastResult['data'] ?? $lastResult['response'] ?? null,
                    $lastResult['error'] ?? $lastResult['metadata']['message'] ?? $lastResult['message'] ?? 'Success'
                ))->additional([
                    'batal' => $lastResult['batal']['data'] ?? null
                ]);
            }
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Get booking details from BPJS by kodebooking or no_rawat
     *
     * @param string $identifier kodebooking or no_rawat
     * @return JsonResponse
     */
    public function getBookingDetails(string $identifier): JsonResponse
    {
        try {
            $kodebooking = $this->mobileJknService->getKodeBooking($identifier);
            $result = $this->mobileJknService->getBookingDetails($kodebooking);

            $httpStatusCode = 200;
            if (isset($result['metadata']['code'])) {
                $code = (int)$result['metadata']['code'];
                if ($code >= 100 && $code <= 599) {
                    $httpStatusCode = $code;
                }
            }

            return response()->json($result, $httpStatusCode);
        } catch (\Throwable $e) {
            return response()->json([
                'response' => null,
                'metadata' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ]
            ], 500);
        }
    }

    /**
     * Update task ID with timestamp from database
     *
     * @param UpdateTaskIdFromDbRequest $request
     * @return ApiSuccessResource
     */
    public function updateTaskIdFromDatabase(UpdateTaskIdFromDbRequest $request): ApiSuccessResource
    {
        $result = $this->mobileJknService->updateTaskIdFromDatabase(
            $request->kodebooking,
            $request->taskid
        );

        return new ApiSuccessResource($result);
    }

    /**
     * Update task ID with current timestamp
     *
     * @param UpdateTaskIdNowRequest $request
     * @return ApiSuccessResource
     */
    public function updateTaskIdNow(UpdateTaskIdNowRequest $request): ApiSuccessResource
    {
        if ((int)$request->taskid === 99) {
            $this->mobileJknService->batalAntrean($request->kodebooking, 'Dibatalkan Oleh Admin');
        }

        $result = $this->mobileJknService->updateTaskIdNow(
            $request->kodebooking,
            $request->taskid
        );

        return new ApiSuccessResource($result);
    }

    /**
     * Batch update multiple task IDs
     *
     * @param BatchUpdateTaskIdsRequest $request
     * @return ApiSuccessResource
     */
    public function batchUpdateTaskIds(BatchUpdateTaskIdsRequest $request): ApiSuccessResource
    {
        $result = $this->mobileJknService->batchUpdateTaskIds($request->updates);

        return new ApiSuccessResource($result);
    }

    /**
     * Cancel antrean per patient (Task 99 & Batal Antrean)
     *
     * @param BatalAntreanRequest $request
     * @return JsonResponse|ApiSuccessResource
     */
    public function batalAntrean(BatalAntreanRequest $request): JsonResponse|ApiSuccessResource
    {
        $kodeBooking = $request->input('kodebooking');
        $noRawat = $request->input('no_rawat');
        $keterangan = $request->input('keterangan', 'Dibatalkan Oleh Admin');

        if (!$kodeBooking && !$noRawat) {
            return response()->json(['success' => false, 'message' => 'Harap isi kodebooking atau no_rawat'], 422);
        }

        // Reconcile and cancel
        $result = $this->mobileJknService->cancelAntreanAndReconcile($kodeBooking, $noRawat, $keterangan);

        return new ApiSuccessResource($result);
    }

    /**
     * Reconcile local database status with BPJS cancellations for the last 3 days
     *
     * @param Request $request
     * @return ApiSuccessResource
     */
    public function reconcileCancellations(Request $request): ApiSuccessResource
    {
        $days = $request->get('days', 3);
        $result = $this->mobileJknService->reconcileCancellationsFromBpjs($days);
        
        return new ApiSuccessResource($result);
    }

    /**
     * Display Task ID log view page
     *
     * @return View
     */
    public function taskIdLogs(): View
    {
        $limit = 25;
        $logs = $this->bpjsLogService->getTaskIdLogs($limit, 1);
        
        // Compute statistics for last 30 days
        $successCount = BpjsWsRsLog::where('url', 'like', '%/antrean/updatewaktu%')
            ->whereBetween('code', [200, 299])
            ->count();
            
        $errorCount = BpjsWsRsLog::where('url', 'like', '%/antrean/updatewaktu%')
            ->whereNotBetween('code', [200, 299])
            ->count();
            
        $totalCount = BpjsWsRsLog::where('url', 'like', '%/antrean/updatewaktu%')->count();
        
        $antreanSuccessCount = BpjsWsRsLog::where('url', 'like', '%/antrean/add%')
            ->whereBetween('code', [200, 299])
            ->count();
            
        $antreanErrorCount = BpjsWsRsLog::where('url', 'like', '%/antrean/add%')
            ->whereNotBetween('code', [200, 299])
            ->count();
            
        $antreanTotalCount = BpjsWsRsLog::where('url', 'like', '%/antrean/add%')->count();
        
        return view('mobilejkn.taskid-logs', compact(
            'logs', 
            'successCount', 
            'errorCount', 
            'totalCount',
            'antreanSuccessCount',
            'antreanErrorCount',
            'antreanTotalCount'
        ));
    }

    /**
     * Get task ID logs API endpoint
     *
     * @param GetTaskIdLogsRequest $request
     * @return ApiSuccessResource
     */
    public function getTaskIdLogs(GetTaskIdLogsRequest $request): ApiSuccessResource
    {
        $perPage = $request->perPage ?? 25;
        $page = $request->page ?? 1;

        $logs = $this->bpjsLogService->getTaskIdLogs($perPage, $page);

        return new ApiSuccessResource(TaskIdLogResource::collection($logs));
    }

    /**
     * Get filtered task ID logs API endpoint
     *
     * @param FilteredTaskIdLogsRequest $request
     * @return ApiSuccessResource
     */
    public function getFilteredTaskIdLogs(FilteredTaskIdLogsRequest $request): ApiSuccessResource
    {
        $perPage = $request->perPage ?? 25;
        $page = $request->page ?? 1;

        $logs = $this->bpjsLogService->filterTaskIdLogs(
            $request->startDate . ' 00:00:00',
            $request->endDate . ' 23:59:59',
            $perPage,
            $page
        );

        return new ApiSuccessResource(TaskIdLogResource::collection($logs));
    }
    
    /**
     * Get antrean add logs API endpoint
     *
     * @param GetTaskIdLogsRequest $request
     * @return ApiSuccessResource
     */
    public function getAntreanAddLogs(GetTaskIdLogsRequest $request): ApiSuccessResource
    {
        $perPage = $request->perPage ?? 25;
        $page = $request->page ?? 1;

        $logs = $this->bpjsLogService->getAntreanAddLogs($perPage, $page);

        return new ApiSuccessResource(TaskIdLogResource::collection($logs));
    }
    
    /**
     * Get patient data needed for task ID updates
     *
     * @param string $regNo
     * @return ApiSuccessResource|JsonResponse
     */
    public function getPatientData(string $regNo): ApiSuccessResource|JsonResponse
    {
        $data = $this->mobileJknService->getPatientDataForTaskId($regNo);
        
        if (!$data) {
            return response()->json([
                'success' => false,
                'message' => 'Data not found',
                'data' => null
            ], 404);
        }
        
        return new ApiSuccessResource($data, 'Data retrieved successfully');
    }
    
    /**
     * Display the patient data view
     *
     * @return View|Factory
     */
    public function showPatientDataForm()
    {
        return view('mobilejkn.patient-data');
    }
    
    /**
     * Send antrean by no_rawat
     *
     * @param Request $request
     * @return ApiSuccessResource|JsonResponse
     */
    public function sendAntrian(Request $request): ApiSuccessResource|JsonResponse
    {
        $noRawat = $request->input('no_rawat');
        if (!$noRawat) {
            return response()->json(['success' => false, 'message' => 'no_rawat is required', 'data' => [], 'payload' => null], 422);
        }

        try {
            $service = app(MobileJknService::class);
            $result = $service->sendAddAntreanByNoRawat($noRawat);
            return new ApiSuccessResource($result);
        } catch (\Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage(), 'data' => [], 'payload' => null], 500);
        }
    }

    /**
     * Display referensi pendaftaran MJKN page
     *
     * @param ReferensiPendaftaranFilterRequest $request
     * @return View
     */
    public function referensiPendaftaran(ReferensiPendaftaranFilterRequest $request): View
    {
        $query = \App\Models\ReferensiMobilejknBpjs::with(['regPeriksa.pasien', 'referensiMobilejknBpjsTaskid']);

        // Apply filters
        if ($request->filled('date_from') || $request->filled('date_to')) {
            if ($request->filled('date_from') && $request->filled('date_to')) {
                $query->whereBetween('tanggalperiksa', [$request->date_from, $request->date_to]);
            } elseif ($request->filled('date_from')) {
                $query->whereDate('tanggalperiksa', '>=', $request->date_from);
            } elseif ($request->filled('date_to')) {
                $query->whereDate('tanggalperiksa', '<=', $request->date_to);
            }
        }

        if ($request->filled('no_rawat')) {
            $query->where('no_rawat', 'like', '%' . $request->no_rawat . '%');
        }

        if ($request->filled('status')) {
            if ($request->status === 'belum') {
                $query->whereNull('status')->orWhere('status', '');
            } else {
                $query->where('status', $request->status);
            }
        }

        $referensis = $query->orderBy('tanggalperiksa', 'desc')
            ->paginate(10)
            ->appends($request->query());

        // Calculate statistics
        $totalReferensi = \App\Models\ReferensiMobilejknBpjs::count();
        $todayReferensi = \App\Models\ReferensiMobilejknBpjs::whereDate('tanggalperiksa', today())->count();

        // Calculate filtered statistics if filters are applied
        $filteredCount = null;
        if ($request->hasAny(['date_from', 'date_to', 'no_rawat', 'no_booking'])) {
            $filteredQuery = \App\Models\ReferensiMobilejknBpjs::query();

            if ($request->filled('date_from') || $request->filled('date_to')) {
                if ($request->filled('date_from') && $request->filled('date_to')) {
                    $filteredQuery->whereBetween('tanggalperiksa', [$request->date_from, $request->date_to]);
                } elseif ($request->filled('date_from')) {
                    $filteredQuery->whereDate('tanggalperiksa', '>=', $request->date_from);
                } elseif ($request->filled('date_to')) {
                    $filteredQuery->whereDate('tanggalperiksa', '<=', $request->date_to);
                }
            }

            if ($request->filled('no_rawat')) {
                $filteredQuery->where('no_rawat', 'like', '%' . $request->no_rawat . '%');
            }

            if ($request->filled('no_booking')) {
                $filteredQuery->where('nobooking', 'like', '%' . $request->no_booking . '%');
            }

            if ($request->filled('status')) {
                if ($request->status === 'belum') {
                    $filteredQuery->whereNull('status')->orWhere('status', '');
                } else {
                    $filteredQuery->where('status', $request->status);
                }
            }

            $filteredCount = $filteredQuery->count();
        }

        return view('mobilejkn.referensi-pendaftaran', compact('referensis', 'totalReferensi', 'todayReferensi', 'filteredCount', 'request'));
    }

    /**
     * Update status for filtered referensi records
     *
     * @param ReferensiPendaftaranFilterRequest $request
     * @return ApiSuccessResource|JsonResponse
     */
    public function updateReferensiStatus(ReferensiPendaftaranFilterRequest $request): ApiSuccessResource|JsonResponse
    {
        try {
            // If frontend provided explicit list of booking numbers (modal preview), use that
            $referensiQuery = \App\Models\ReferensiMobilejknBpjs::with(['regPeriksa.pasien']);

            if ($request->filled('no_booking_list')) {
                $list = json_decode($request->input('no_booking_list'), true);
                if (is_array($list) && count($list) > 0) {
                    $referensiQuery->whereIn('nobooking', $list);
                }
            } else {
                // Apply the same filters as the GET method when list is not provided
                if ($request->filled('date_from') || $request->filled('date_to')) {
                    if ($request->filled('date_from') && $request->filled('date_to')) {
                        $referensiQuery->whereBetween('tanggalperiksa', [$request->date_from, $request->date_to]);
                    } elseif ($request->filled('date_from')) {
                        $referensiQuery->whereDate('tanggalperiksa', '>=', $request->date_from);
                    } elseif ($request->filled('date_to')) {
                        $referensiQuery->whereDate('tanggalperiksa', '<=', $request->date_to);
                    }
                }

                if ($request->filled('no_rawat')) {
                    $referensiQuery->where('no_rawat', 'like', '%' . $request->no_rawat . '%');
                }

                if ($request->filled('no_booking')) {
                    $referensiQuery->where('nobooking', 'like', '%' . $request->no_booking . '%');
                }

                if ($request->filled('status')) {
                    if ($request->status === 'belum') {
                        $referensiQuery->whereNull('status')->orWhere('status', '');
                    } else {
                        $referensiQuery->where('status', $request->status);
                    }
                }
            }

            // Get all records that match the selected list or filters (not paginated)
            $referensis = $referensiQuery->get();

            $updatedCount = 0;
            $cancelledCount = 0;
            $checkinCount = 0;
            $errors = [];
            $updatedRecords = [];

            foreach ($referensis as $referensi) {
                try {
                    $regStts = strtolower(trim($referensi->regPeriksa->stts ?? ''));
                    $newStatus = null;
                    $action = '';

                    if ($regStts === 'sudah' || $regStts === 'berkas diterima') {
                        $newStatus = 'Checkin';
                        $action = 'Check-in';
                        $checkinCount++;
                    } elseif ($regStts === 'belum' || $regStts === 'batal') {
                        $newStatus = 'Batal';
                        $action = 'Batal';
                        $cancelledCount++;
                    } else {
                        // skip if other status
                        continue;
                    }

                    $oldStatus = $referensi->status;

                    // Calculate validation timestamp based on tanggal periksa and pemeriksaan ralan time
                    $validasiTimestamp = now();
                    $pemeriksaanRalan = \App\Models\PemeriksaanRalan::where('no_rawat', $referensi->no_rawat)
                        ->whereNotNull('nip')
                        ->first();
                    
                    if ($pemeriksaanRalan && $pemeriksaanRalan->jam_rawat) {
                        $validasiTimestamp = \Carbon\Carbon::parse($referensi->tanggalperiksa, 'Asia/Jakarta')
                            ->setTime(
                                $pemeriksaanRalan->jam_rawat->hour,
                                $pemeriksaanRalan->jam_rawat->minute,
                                $pemeriksaanRalan->jam_rawat->second
                            )
                            ->subMinutes(10);
                    }

                    $referensi->update([
                        'status' => $newStatus,
                        'validasi' => $validasiTimestamp,
                    ]);

                    $updatedCount++;

                    $updatedRecords[] = [
                        'no_booking' => $referensi->nobooking,
                        'no_rawat' => $referensi->no_rawat,
                        'nm_pasien' => $referensi->regPeriksa->pasien->nm_pasien ?? '-',
                        'old_status' => $oldStatus,
                        'new_status' => $newStatus,
                        'action' => $action,
                        'reg_status' => $referensi->regPeriksa->stts ?? 'N/A'
                    ];

                } catch (\Exception $e) {
                    $errors[] = "Error updating {$referensi->nobooking}: " . $e->getMessage();
                }
            }

            $message = "Status berhasil diupdate untuk {$updatedCount} data.";
            if ($checkinCount > 0) {
                $message .= "\n{$checkinCount} data di-checkin (Reg Periksa status 'sudah').";
            }
            if ($cancelledCount > 0) {
                $message .= "\n{$cancelledCount} data dibatalkan (Reg Periksa status 'batal'/'belum').";
            }
            if (!empty($errors)) {
                $message .= "\n\nError:\n" . implode("\n", $errors);
            }

            return (new ApiSuccessResource(
                $updatedRecords,
                $message
            ))->additional([
                'updated_count' => $updatedCount,
                'checkin_count' => $checkinCount,
                'cancelled_count' => $cancelledCount,
                'errors' => $errors
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }
}
