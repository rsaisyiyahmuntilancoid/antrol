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
            $reg = \App\Models\RegPeriksa::with(['pasien', 'poliklinik', 'dokter', 'referensiMobilejknBpjs', 'referensiMobilejknBpjsTaskid'])
                ->where('no_rawat', $noRawat)
                ->first();

            if (!$reg) {
                return response()->json([
                    'success' => false,
                    'message' => 'Patient registration not found'
                ], 404);
            }

            $realTimestamps = $this->flowAnalyticsService->getRealTimestamps($reg);
            $sentTimestamps = $this->flowAnalyticsService->getSentTimestamps($reg);
            $durations = $this->flowAnalyticsService->computeDurations($realTimestamps);
            $status = $this->flowAnalyticsService->determineFlowStatus($realTimestamps, $reg->stts);
            $anomalies = $this->flowAnalyticsService->detectPatientAnomalies($realTimestamps, $sentTimestamps, $durations);

            return response()->json([
                'success' => true,
                'data' => [
                    'no_rawat' => $reg->no_rawat,
                    'no_rkm_medis' => $reg->no_rkm_medis,
                    'nm_pasien' => $reg->pasien->nm_pasien ?? 'N/A',
                    'nm_poli' => $reg->poliklinik->nm_poli ?? 'N/A',
                    'nm_dokter' => $reg->dokter->nm_dokter ?? 'N/A',
                    'stts' => $reg->stts,
                    'has_booking' => $reg->referensiMobilejknBpjs ? true : false,
                    'kode_booking' => $reg->referensiMobilejknBpjs->nobooking ?? null,
                    'timestamps_real' => array_map(function ($c) {
                        return $c ? $c->toDateTimeString() : null;
                    }, $realTimestamps),
                    'timestamps_sent' => array_map(function ($c) {
                        return $c ? $c->toDateTimeString() : null;
                    }, $sentTimestamps),
                    'durations' => $durations,
                    'status' => $status,
                    'anomalies' => $anomalies,
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
}
