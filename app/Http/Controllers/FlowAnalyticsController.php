<?php

namespace App\Http\Controllers;

use App\Services\FlowAnalyticsService;
use App\Services\MobileJknService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\SyncRangeRequest;
use App\Http\Requests\SyncPatientRequest;
use App\Http\Requests\SyncBatchRequest;
use App\Http\Resources\PatientDetailResource;
use App\Http\Resources\SyncResultResource;
use App\Http\Resources\ApiSuccessResource;

class FlowAnalyticsController extends Controller
{
    protected $flowAnalyticsService;
    protected $mobileJknService;

    public function __construct(
        FlowAnalyticsService $flowAnalyticsService,
        MobileJknService $mobileJknService
    ) {
        $this->flowAnalyticsService = $flowAnalyticsService;
        $this->mobileJknService = $mobileJknService;
    }

    /**
     * Display the Flow Analytics dashboard
     */
    public function index(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::today()->format('Y-m-d'));
        $dateTo = $request->get('date_to', $dateFrom);

        try {
            $carbonFrom = Carbon::parse($dateFrom);
            $carbonTo = Carbon::parse($dateTo);

            if ($carbonFrom->diffInDays($carbonTo) > 31) {
                $dateTo = $carbonFrom->copy()->addDays(31)->format('Y-m-d');
                session()->flash('warning', 'Rentang tanggal penarikan data dibatasi maksimal 31 hari demi mencegah database timeout atau memory limit.');
            }
        } catch (\Exception $e) {
            $dateFrom = Carbon::today()->format('Y-m-d');
            $dateTo = $dateFrom;
        }

        $analytics = $this->flowAnalyticsService->getAnalyticsData($dateFrom, $dateTo);

        return view('monitoring.index', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'analytics' => $analytics
        ]);
    }

    /**
     * Display printable report layout
     */
    public function print(Request $request)
    {
        $dateFrom = $request->get('date_from', Carbon::today()->format('Y-m-d'));
        $dateTo = $request->get('date_to', $dateFrom);

        try {
            $carbonFrom = Carbon::parse($dateFrom);
            $carbonTo = Carbon::parse($dateTo);

            if ($carbonFrom->diffInDays($carbonTo) > 31) {
                $dateTo = $carbonFrom->copy()->addDays(31)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            $dateFrom = Carbon::today()->format('Y-m-d');
            $dateTo = $dateFrom;
        }

        $analytics = $this->flowAnalyticsService->getAnalyticsData($dateFrom, $dateTo);

        return view('monitoring.print', [
            'dateFrom' => $dateFrom,
            'dateTo' => $dateTo,
            'analytics' => $analytics
        ]);
    }

    /**
     * Get flow analytics data via AJAX
     */
    public function getAnalyticsData(Request $request): ApiSuccessResource
    {
        $dateFrom = $request->get('date_from', Carbon::today()->format('Y-m-d'));
        $dateTo = $request->get('date_to', $dateFrom);

        try {
            $carbonFrom = Carbon::parse($dateFrom);
            $carbonTo = Carbon::parse($dateTo);

            if ($carbonFrom->diffInDays($carbonTo) > 31) {
                $dateTo = $carbonFrom->copy()->addDays(31)->format('Y-m-d');
            }
        } catch (\Exception $e) {
            $dateFrom = Carbon::today()->format('Y-m-d');
            $dateTo = $dateFrom;
        }

        $data = $this->flowAnalyticsService->getAnalyticsData($dateFrom, $dateTo);

        return new ApiSuccessResource($data);
    }

    /**
     * Get detail timeline for a specific patient
     */
    public function getPatientDetail(string $noRawat): ApiSuccessResource|JsonResponse
    {
        try {
            $data = $this->flowAnalyticsService->buildPatientDetailData($noRawat, $this->mobileJknService);

            if (!$data) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data registrasi pasien tidak ditemukan'
                ], 404);
            }

            return new ApiSuccessResource(new PatientDetailResource($data));
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Trigger on-demand sync for a specific date (today's registrations)
     */
    public function syncToday(Request $request): ApiSuccessResource
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $result = $this->flowAnalyticsService->syncDatePatientsDirectly($date);

        return new ApiSuccessResource($result, "Berhasil menyinkronkan data tanggal {$date}");
    }

    /**
     * Trigger background queue sync for a date range
     */
    public function syncRange(SyncRangeRequest $request): ApiSuccessResource
    {
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;

        \App\Jobs\SyncDateRangeJob::dispatch($dateFrom, $dateTo);

        $syncKey = "sync_range_" . $dateFrom . "_" . $dateTo;
        \Illuminate\Support\Facades\Cache::put($syncKey, [
            'status' => 'pending',
            'total_days' => Carbon::parse($dateFrom)->diffInDays(Carbon::parse($dateTo)) + 1,
            'processed_days' => 0,
            'current_date' => null,
            'percent' => 0,
            'started_at' => now()->timezone('Asia/Jakarta')->toIso8601String(),
        ], 86400);

        return (new ApiSuccessResource(
            ['sync_key' => $syncKey],
            'Sinkronisasi rentang tanggal dijadwalkan di latar belakang.'
        ));
    }

    /**
     * Polling endpoint to check background queue range sync progress
     */
    public function syncStatus(Request $request): ApiSuccessResource|JsonResponse
    {
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        if (!$dateFrom || !$dateTo) {
            return response()->json(['success' => false, 'message' => 'Parameter date_from dan date_to diperlukan'], 400);
        }

        $syncKey = "sync_range_" . $dateFrom . "_" . $dateTo;
        $status = \Illuminate\Support\Facades\Cache::get($syncKey);

        if (!$status) {
            $simrsQuery = \App\Models\RegPeriksa::whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
                ->where('kd_pj', config('mobilejkn.kd_pj', 'BPJ'));
            $excludePoli = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
            $excludePoliArray = array_filter(explode(',', $excludePoli));
            if (!empty($excludePoliArray)) {
                $simrsQuery->whereNotIn('kd_poli', $excludePoliArray);
            }
            $simrsDates = $simrsQuery->groupBy('tgl_registrasi')->pluck('tgl_registrasi')->toArray();

            $visitsCountByDate = \App\Models\BpjsPatientVisit::whereBetween('tanggalperiksa', [$dateFrom, $dateTo])
                ->groupBy('tanggalperiksa')
                ->pluck('tanggalperiksa')
                ->toArray();

            $daysWithRegistrations = count($simrsDates);
            $daysWithData = count($visitsCountByDate);

            if ($daysWithRegistrations > 0 && $daysWithData === $daysWithRegistrations) {
                $status = ['status' => 'completed', 'percent' => 100];
            } else {
                $status = ['status' => 'none', 'percent' => 0];
            }
        }

        return new ApiSuccessResource($status);
    }

    /**
     * Cross-verify local timestamps with BPJS servers
     */
    public function verifyBpjs(string $noRawat): ApiSuccessResource|JsonResponse
    {
        try {
            $reg = \App\Models\RegPeriksa::with(['referensiMobilejknBpjs'])
                ->where('no_rawat', $noRawat)
                ->orWhereHas('referensiMobilejknBpjs', function ($q) use ($noRawat) {
                    $q->where('nobooking', $noRawat);
                })
                ->first();

            $bpjsVisit = \App\Models\BpjsPatientVisit::where('no_rawat', $noRawat)
                ->orWhere('kodebooking', $noRawat)
                ->first();

            if (!$reg && !$bpjsVisit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient registration not found'
                ], 404);
            }

            $kodebooking = $reg?->referensiMobilejknBpjs?->nobooking
                ?? $bpjsVisit?->kodebooking
                ?? $reg?->no_rawat
                ?? $noRawat;

            // Fetch directly from BPJS (bypassing cache)
            $bpjsData = $this->mobileJknService->getListTaskDirect($kodebooking);

            if ($bpjsData['success'] && !empty($bpjsData['data'])) {
                $updateData = [
                    'task_data' => $bpjsData['data'],
                    'last_sync' => now()
                ];

                if ($reg) {
                    $updateData['no_rawat'] = $reg->no_rawat;
                    $updateData['tanggalperiksa'] = $reg->tgl_registrasi;

                    if ($reg->referensiMobilejknBpjs) {
                        $ref = $reg->referensiMobilejknBpjs;
                        $updateData = array_merge($updateData, [
                            'nomorkartu'       => $ref->nomorkartu,
                            'nik'              => $ref->nik,
                            'nohp'             => $ref->nohp,
                            'norm'             => $ref->norm,
                            'kodepoli'         => $ref->kodepoli,
                            'namapoli'         => $reg->poliklinik->nm_poli ?? '',
                            'kodedokter'       => $ref->kodedokter,
                            'namadokter'       => $reg->dokter->nm_dokter ?? '',
                            'jampraktek'       => $ref->jampraktek,
                            'jeniskunjungan'   => (int) filter_var($ref->jeniskunjungan, FILTER_SANITIZE_NUMBER_INT) ?: null,
                            'nomorreferensi'   => $ref->nomorreferensi,
                            'nomorantrean'     => $ref->nomorantrean,
                            'angkaantrean'     => (int) filter_var($ref->angkaantrean, FILTER_SANITIZE_NUMBER_INT) ?: 0,
                            'estimasidilayani' => $ref->estimasidilayani,
                            'sisakuotajkn'     => $ref->sisakuotajkn,
                            'kuotajkn'         => $ref->kuotajkn,
                            'sisakuotanonjkn'  => $ref->sisakuotanonjkn,
                            'kuotanonjkn'      => $ref->kuotanonjkn,
                            'status'           => $ref->status,
                        ]);
                    }
                }

                \App\Models\BpjsPatientVisit::updateOrCreate(
                    ['kodebooking' => $kodebooking],
                    $updateData
                );
            }

            return (new ApiSuccessResource(
                $bpjsData['data'] ?? [],
                'BPJS verification completed'
            ))->additional([
                'metadata' => $bpjsData['metadata'] ?? [],
                'status_code' => $bpjsData['status_code']
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get listtask directly from BPJS by kodebooking
     */
    public function getListTaskByKodeBooking(Request $request, ?string $kodebooking = null): ApiSuccessResource|JsonResponse
    {
        try {
            $bookingCode = $kodebooking ?: $request->input('kodebooking');

            if (!$bookingCode) {
                return response()->json([
                    'success' => false,
                    'message' => 'Parameter kodebooking wajib diisi'
                ], 400);
            }

            $bpjsData = $this->mobileJknService->getListTaskDirect($bookingCode);

            return (new ApiSuccessResource(
                $bpjsData['data'] ?? [],
                'Tasks retrieved successfully'
            ))->additional([
                'kodebooking' => $bookingCode,
                'metadata'    => $bpjsData['metadata'] ?? [],
                'status_code' => $bpjsData['status_code'] ?? 500
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get detailed statistics for a specific clinic
     */
    public function getClinicDetail(string $nmPoli): ApiSuccessResource|JsonResponse
    {
        try {
            $dateFrom = request()->get('date_from', Carbon::today()->format('Y-m-d'));
            $dateTo   = request()->get('date_to', $dateFrom);

            $data = $this->flowAnalyticsService->getAnalyticsData($dateFrom, $dateTo);

            $clinicStats = $data['clinic_stats'][$nmPoli] ?? null;

            if (!$clinicStats) {
                return response()->json([
                    'success' => false,
                    'message' => "Poliklinik '{$nmPoli}' tidak ditemukan pada rentang tanggal yang dipilih."
                ], 404);
            }

            // Filter patients for this clinic only
            $clinicPatients = array_filter($data['patients'], fn($p) => $p['nm_poli'] === $nmPoli);
            $clinicPatients = array_values($clinicPatients);

            // Negative duration breakdown for this clinic
            $negativeBreakdown = [];
            foreach ($clinicPatients as $p) {
                foreach (['waktu_tunggu_poli', 'waktu_layan_poli', 'waktu_tunggu_farmasi', 'waktu_layan_farmasi'] as $key) {
                    if (isset($p['durations'][$key]) && $p['durations'][$key] !== null && $p['durations'][$key] < 0) {
                        $negativeBreakdown[] = [
                            'no_rawat'  => $p['no_rawat'],
                            'nm_pasien' => $p['nm_pasien'],
                            'metric'    => $key,
                            'value'     => $p['durations'][$key],
                        ];
                    }
                }
            }

            // Fetch polyclinic mapping
            $poliklinik = \App\Models\Poliklinik::where('nm_poli', $nmPoli)->first();
            $bpjsPoliCode = null;
            $bpjsStats = null;

            if ($poliklinik) {
                $mapping = \App\Models\MapingPoliBpjs::where('kd_poli_rs', $poliklinik->kd_poli)->first();
                if ($mapping) {
                    $bpjsPoliCode = $mapping->kd_poli_bpjs;

                    // Fetch BPJS Official Dashboard data for this polyclinic
                    $bpjsDashboard = $this->mobileJknService->getDashboardPerTanggal($dateFrom, 'rs');
                    if ($bpjsDashboard['success'] && isset($bpjsDashboard['data']['list'])) {
                        foreach ($bpjsDashboard['data']['list'] as $bpjsItem) {
                            if ($bpjsItem['kodepoli'] === $bpjsPoliCode) {
                                $bpjsStats = $bpjsItem;
                                break;
                            }
                        }
                    }
                }
            }

            return (new ApiSuccessResource(
                $clinicStats,
                'Clinic statistics retrieved'
            ))->additional([
                'nm_poli'            => $nmPoli,
                'bpjs_poli_code'     => $bpjsPoliCode,
                'bpjs_stats'         => $bpjsStats,
                'date_range'         => ['from' => $dateFrom, 'to' => $dateTo],
                'patient_count'      => count($clinicPatients),
                'negative_durations' => $negativeBreakdown,
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get official daily dashboard report from BPJS Kesehatan
     */
    public function getBpjsDashboardTanggal(Request $request): ApiSuccessResource
    {
        $tanggal = $request->get('tanggal', Carbon::today()->format('Y-m-d'));
        $waktu = $request->get('waktu', 'rs');

        $result = $this->mobileJknService->getDashboardPerTanggal($tanggal, $waktu);

        return new ApiSuccessResource($result);
    }

    /**
     * Get official monthly dashboard report from BPJS Kesehatan
     */
    public function getBpjsDashboardBulan(Request $request): ApiSuccessResource
    {
        $bulan = $request->get('bulan', Carbon::today()->format('m'));
        $tahun = $request->get('tahun', Carbon::today()->format('Y'));
        $waktu = $request->get('waktu', 'rs');

        $result = $this->mobileJknService->getDashboardPerBulan($bulan, $tahun, $waktu);

        return new ApiSuccessResource($result);
    }

    /**
     * Sync a single patient from BPJS to local cache
     */
    public function syncPatient(SyncPatientRequest $request): ApiSuccessResource
    {
        $result = $this->flowAnalyticsService->syncSinglePatient(
            $request->kodebooking,
            $request->no_rawat
        );

        return new ApiSuccessResource(new SyncResultResource($result));
    }

    /**
     * Sync multiple patients from BPJS in one request
     */
    public function syncBatch(SyncBatchRequest $request): ApiSuccessResource
    {
        $results = [];
        $successCount = 0;
        $failedCount = 0;

        foreach ($request->patients as $patient) {
            try {
                $result = $this->flowAnalyticsService->syncSinglePatient(
                    $patient['kodebooking'],
                    $patient['no_rawat']
                );
                if ($result['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                }
                $results[$patient['kodebooking']] = $result;
            } catch (\Exception $e) {
                $failedCount++;
                $results[$patient['kodebooking']] = [
                    'success' => false,
                    'message' => $e->getMessage(),
                    'patient' => null,
                ];
            }
        }

        return (new ApiSuccessResource(
            $results,
            'Batch sync completed'
        ))->additional([
            'total' => count($request->patients),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
        ]);
    }
}
