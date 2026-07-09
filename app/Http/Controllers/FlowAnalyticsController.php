<?php

namespace App\Http\Controllers;

use App\Services\FlowAnalyticsService;
use App\Services\MobileJknService;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;

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
    public function getAnalyticsData(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Get detail timeline for a specific patient
     */
    public function getPatientDetail(string $noRawat): JsonResponse
    {
        try {
            // First try to find BPJS cached data
            $bpjsVisit = \App\Models\BpjsPatientVisit::where('no_rawat', $noRawat)
                ->orWhere('kodebooking', $noRawat)
                ->first();
 
            $reg = \App\Models\RegPeriksa::with([
                    'pasien',
                    'poliklinik',
                    'dokter',
                    'referensiMobilejknBpjs',
                    'referensiMobilejknBpjsTaskid',
                    'bridgingSep',
                ])
                ->where('no_rawat', $bpjsVisit?->no_rawat ?? $noRawat)
                ->first();
 
            if (!$reg && !$bpjsVisit) {
                return response()->json([
                    'success' => false,
                    'message' => 'Data registrasi pasien tidak ditemukan'
                ], 404);
            }
 
            // Sync/fetch from BPJS automatically to populate/update the cache in DB
            $kodebooking = $reg?->referensiMobilejknBpjs?->nobooking ?? $bpjsVisit?->kodebooking ?? $noRawat;
            if ($kodebooking && (!$bpjsVisit || !$bpjsVisit->last_sync || $bpjsVisit->last_sync->lt(now()->subMinutes(15)))) {
                $this->mobileJknService->getListTask($kodebooking);
                // Reload visit after sync
                $bpjsVisit = \App\Models\BpjsPatientVisit::where('no_rawat', $noRawat)
                    ->orWhere('kodebooking', $noRawat)
                    ->first();
            }
 
            // Get timestamps
            $realTimestamps = $reg ? $this->flowAnalyticsService->getRealTimestamps($reg) : [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];
 
            if ($bpjsVisit) {
                $bpjsTimestamps = $this->flowAnalyticsService->getBpjsTimestamps($bpjsVisit);
            } else {
                $bpjsTimestamps = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];
            }
 
            $hasBpjsData = ($bpjsVisit && $bpjsVisit->task_data !== null && count($bpjsVisit->task_data) > 0);
 
            if ($hasBpjsData) {
                $durations = $this->flowAnalyticsService->computeDurationsFromTaskData($bpjsVisit->task_data);
                $status = $this->flowAnalyticsService->determineStatusFromTaskData($bpjsVisit->task_data);
            } else {
                $durations = [
                    'checkin_to_nurse'   => null,
                    'nurse_to_doctor'    => null,
                    'doctor_to_pharmacy' => null,
                    'pharmacy_to_done'   => null,
                    'total_time'         => null,
                    'waktu_tunggu_poli'    => null,
                    'waktu_layan_poli'     => null,
                    'waktu_tunggu_farmasi' => null,
                    'waktu_layan_farmasi'  => null,
                    'total_waktu_rs'       => null,
                ];
                $status = 'Belum Terkirim';
            }
            $anomalies = $this->flowAnalyticsService->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);
            $comparison = $this->flowAnalyticsService->compareBpjsAndSimrs($bpjsTimestamps, $realTimestamps);
 
            // Anomaly explanation hints for analyst
            $anomalyHints = [];
            if (in_array('durasi_negatif', $anomalies)) {
                $anomalyHints[] = 'Durasi negatif: kemungkinan data entry tidak urut, timestamp SIMRS terbalik, atau sinkronisasi jam server tidak konsisten antar modul.';
            }
            if (in_array('timestamp_buatan', $anomalies)) {
                $anomalyHints[] = 'Timestamp buatan: data terkirim ke BPJS namun tidak ada record asli di SIMRS — fallback random digunakan saat pengiriman Task ID.';
            }
            if (in_array('belum_terkirim', $anomalies)) {
                $anomalyHints[] = 'Belum terkirim: ada timestamp di SIMRS namun Task ID belum sampai ke server BPJS — periksa log pengiriman di menu Logs.';
            }
            if (in_array('farmasi_10_menit', $anomalies)) {
                $anomalyHints[] = 'Farmasi 10 menit: waktu tunggu farmasi tepat 10.0 menit — indikasi kuat menggunakan fallback random_int(5,10) saat data resep belum tersedia.';
            }
            if (in_array('outlier_durasi', $anomalies)) {
                $anomalyHints[] = 'Outlier durasi: terdapat durasi yang sangat panjang (>180 menit) — mungkin pasien menunggu lama atau ada gap data entry di SIMRS.';
            }
 
            // No. BPJS: prioritas dari bridging_sep.no_kartu, fallback pasien.no_peserta
            $noKartuBpjs = null;
            if ($reg) {
                $noKartuBpjs = $reg->bridgingSep->no_kartu ?? $reg->pasien->no_peserta ?? null;
            } elseif ($bpjsVisit) {
                $noKartuBpjs = $bpjsVisit->nomorkartu;
            }
 
            // Resolve doctor name using SIMRS first, then MapingDokterDpjpvclaim, then BPJS namadokter
            $docName = 'N/A';
            if ($reg && $reg->dokter) {
                $docName = $reg->dokter->nm_dokter;
            } elseif ($bpjsVisit && $bpjsVisit->kodedokter) {
                $mapping = \App\Models\MapingDokterDpjpvclaim::where('kd_dokter_bpjs', $bpjsVisit->kodedokter)->with('dokter')->first();
                $docName = $mapping->dokter->nm_dokter ?? $bpjsVisit->namadokter ?? 'N/A';
            } else {
                $docName = $bpjsVisit?->namadokter ?? 'N/A';
            }

            return response()->json([
                'success' => true,
                'data'    => [
                    'no_rawat'       => $reg?->no_rawat ?? $bpjsVisit?->no_rawat,
                    'no_rkm_medis'   => $reg?->no_rkm_medis ?? $bpjsVisit?->norm,
                    'nm_pasien'      => $reg?->pasien->nm_pasien ?? 'N/A',
                    'no_ktp'         => $reg?->pasien->no_ktp ?? $bpjsVisit?->nik ?? null, // NIK
                    'no_kartu_bpjs'  => $noKartuBpjs, // No. BPJS
                    'tgl_lahir'      => $reg?->pasien?->tgl_lahir ? \Carbon\Carbon::parse($reg->pasien->tgl_lahir)->format('d M Y') : null,
                    'jk'             => $reg?->pasien->jk ?? null,
                    'nm_poli'        => $reg?->poliklinik->nm_poli ?? $bpjsVisit?->namapoli ?? 'N/A',
                    'nm_dokter'      => $docName,
                    'tgl_registrasi' => ($bpjsVisit && $bpjsVisit->tanggalperiksa instanceof Carbon) ? $bpjsVisit->tanggalperiksa->format('d M Y') : ($reg?->tgl_registrasi instanceof Carbon ? $reg->tgl_registrasi->format('d M Y') : (string) $reg?->tgl_registrasi),
                    'jam_reg'        => $reg?->jam_reg ? ($reg->jam_reg instanceof \DateTimeInterface ? $reg->jam_reg->format('H:i') : substr((string) $reg->jam_reg, 0, 5)) : null,
                    'stts'           => $reg?->stts ?? '',
                    'has_booking'    => (bool) ($reg?->referensiMobilejknBpjs ?? $bpjsVisit),
                    'kode_booking'   => $kodebooking,
                    'timestamps_real' => array_map(fn($c) => $c?->toDateTimeString(), $realTimestamps),
                    'timestamps_sent' => array_map(fn($c) => $c?->toDateTimeString(), $bpjsTimestamps),
                    'durations'      => $durations,
                    'status'         => $status,
                    'anomalies'      => $anomalies,
                    'anomaly_hints'  => $anomalyHints,
                    'comparison'     => $comparison,
                    'is_bpjs_source' => (bool) $bpjsVisit,
                ]
            ]);
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
    public function syncToday(Request $request): JsonResponse
    {
        $date = $request->get('date', Carbon::today()->format('Y-m-d'));
        $result = $this->flowAnalyticsService->syncDatePatientsDirectly($date);
        return response()->json([
            'success' => true,
            'message' => "Berhasil menyinkronkan data tanggal {$date}",
            'data' => $result
        ]);
    }

    /**
     * Trigger background queue sync for a date range
     */
    public function syncRange(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to' => 'required|date_format:Y-m-d',
        ]);

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

        return response()->json([
            'success' => true,
            'message' => 'Sinkronisasi rentang tanggal dijadwalkan di latar belakang.',
            'sync_key' => $syncKey
        ]);
    }

    /**
     * Polling endpoint to check background queue range sync progress
     */
    public function syncStatus(Request $request): JsonResponse
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

        return response()->json([
            'success' => true,
            'data' => $status
        ]);
    }

    /**
     * Cross-verify local timestamps with BPJS servers
     */
    public function verifyBpjs(string $noRawat): JsonResponse
    {
        try {
            $reg = \App\Models\RegPeriksa::with(['referensiMobilejknBpjs'])->where('no_rawat', $noRawat)->first();
            if (!$reg) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient registration not found'
                ], 404);
            }

            $kodebooking = $reg->referensiMobilejknBpjs ? $reg->referensiMobilejknBpjs->nobooking : $noRawat;
            $bpjsData = $this->mobileJknService->getListTask($kodebooking);

            return response()->json([
                'success' => $bpjsData['success'],
                'data' => $bpjsData['data'] ?? [],
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
     * Get detailed statistics for a specific clinic
     */
    public function getClinicDetail(string $nmPoli): JsonResponse
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

            return response()->json([
                'success'            => true,
                'nm_poli'            => $nmPoli,
                'date_range'         => ['from' => $dateFrom, 'to' => $dateTo],
                'stats'              => $clinicStats,
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
    public function getBpjsDashboardTanggal(Request $request): JsonResponse
    {
        $tanggal = $request->get('tanggal', Carbon::today()->format('Y-m-d'));
        $waktu = $request->get('waktu', 'rs');

        $result = $this->mobileJknService->getDashboardPerTanggal($tanggal, $waktu);

        return response()->json($result);
    }

    /**
     * Get official monthly dashboard report from BPJS Kesehatan
     */
    public function getBpjsDashboardBulan(Request $request): JsonResponse
    {
        $bulan = $request->get('bulan', Carbon::today()->format('m'));
        $tahun = $request->get('tahun', Carbon::today()->format('Y'));
        $waktu = $request->get('waktu', 'rs');

        $result = $this->mobileJknService->getDashboardPerBulan($bulan, $tahun, $waktu);

        return response()->json($result);
    }

    /**
     * Sync a single patient from BPJS to local cache
     */
    public function syncPatient(Request $request): JsonResponse
    {
        $request->validate([
            'kodebooking' => 'required|string',
            'no_rawat' => 'required|string',
        ]);

        $result = $this->flowAnalyticsService->syncSinglePatient(
            $request->kodebooking,
            $request->no_rawat
        );

        return response()->json($result);
    }

    /**
     * Sync multiple patients from BPJS in one request
     */
    public function syncBatch(Request $request): JsonResponse
    {
        $request->validate([
            'patients' => 'required|array|min:1',
            'patients.*.kodebooking' => 'required|string',
            'patients.*.no_rawat' => 'required|string',
        ]);

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

        return response()->json([
            'success' => true,
            'total' => count($request->patients),
            'success_count' => $successCount,
            'failed_count' => $failedCount,
            'results' => $results,
        ]);
    }
}
