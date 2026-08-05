<?php
namespace App\Services;

use App\Models\BpjsPatientVisit;
use App\Models\MapingDokterDpjpvclaim;
use App\Models\RegPeriksa;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class FlowAnalyticsService
{
    const TASK_NAMES = [
        1  => 'Kirim Antrian',
        2  => 'Ambil Antrian',
        3  => 'Mulai Waktu Tunggu Admistrasi',
        4  => 'Akhir Waktu Tunggu Admistrasi',
        5  => 'Mulai Waktu Tunggu Pelayanan',
        6  => 'Akhir Waktu Tunggu Pelayanan',
        7  => 'Selesai',
        99 => 'Batal',
    ];

    const MONITOR_TASKS          = [3, 4, 5, 6, 7];
    const CACHE_DURATION_MINUTES = 15;

    const ANOMALY_THRESHOLDS = [
        'negative_duration'  => true,
        'very_long'          => 120,
        'checkin_to_nurse'   => 30,
        'nurse_to_doctor'    => 60,
        'doctor_to_pharmacy' => 30,
        'pharmacy_to_done'   => 30,
    ];

    private function diffMinutes(?Carbon $t1, ?Carbon $t2): ?float
    {
        if (! $t1 || ! $t2) {
            return null;
        }
        return round($t1->diffInSeconds($t2, false) / 60.0, 2);
    }

    public function computeDurationsFromTaskData(?array $taskData): array
    {
        $tasks = [
            3 => null,
            4 => null,
            5 => null,
            6 => null,
            7 => null,
        ];

        foreach (($taskData ?? []) as $t) {
            $tid = (int) $t['taskid'];
            if (isset($t['wakturs']) && array_key_exists($tid, $tasks)) {
                $tasks[$tid] = $this->parseTaskWaktu($t['wakturs']);
            }
        }

        $durations = [
            'waktu_tunggu_poli'    => $this->diffMinutes($tasks[3], $tasks[4]),
            'waktu_layan_poli'     => $this->diffMinutes($tasks[4], $tasks[5]),
            'waktu_tunggu_farmasi' => $this->diffMinutes($tasks[5], $tasks[6]),
            'waktu_layan_farmasi'  => $this->diffMinutes($tasks[6], $tasks[7]),
            'total_waktu_rs'       => $this->diffMinutes($tasks[3], $tasks[7] ?? $tasks[5] ?? null),
        ];

        return $durations;
    }

    public function determineStatusFromTaskData(?array $taskData): string
    {
        if (! $taskData) {
            return 'Belum Terkirim';
        }

        $taskIds = array_column($taskData, 'taskid');
        $taskIds = array_map('intval', $taskIds);
        $taskIds = array_unique($taskIds);
        sort($taskIds);

        if (in_array(99, $taskIds)) {
            return 'Batal';
        }

        $monitoredTasks = array_values(array_intersect($taskIds, [3, 4, 5, 6, 7]));

        if (empty($monitoredTasks)) {
            $otherTasks = array_values(array_intersect($taskIds, [1, 2]));
            if (! empty($otherTasks)) {
                return 'Task ' . implode(',', $otherTasks);
            }
            return 'Belum Lengkap';
        }

        if ($monitoredTasks === [3, 4, 5, 6, 7]) {
            return 'Lengkap (3,4,5,6,7)';
        }
        if ($monitoredTasks === [3, 4, 5, 6]) {
            return 'Lengkap (3,4,5,6) - Farmasi Belum Selesai';
        }
        if ($monitoredTasks === [3, 4, 5]) {
            return 'Task 3,4,5';
        }
        if ($monitoredTasks === [3, 4]) {
            return 'Task 3,4';
        }
        if ($monitoredTasks === [3]) {
            return 'Task 3';
        }

        return 'Task ' . implode(',', $monitoredTasks);
    }

    public function syncTodayIfEmpty(string $date): array
    {
        $kdPj             = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli      = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        $query = RegPeriksa::with(['referensiMobilejknBpjs'])
            ->where('tgl_registrasi', $date)
            ->where('kd_pj', $kdPj);

        if (! empty($excludePoliArray)) {
            $query->whereNotIn('kd_poli', $excludePoliArray);
        }

        $registrations = $query->get();
        $total         = $registrations->count();
        $synced        = 0;

        $startTime = microtime(true);

        foreach ($registrations as $reg) {
            $kodebooking = $reg->referensiMobilejknBpjs?->nobooking ?? $reg->no_rawat;
            if (! $kodebooking) {
                continue;
            }

            $result = $this->syncSinglePatient($kodebooking, $reg->no_rawat);
            if ($result['success']) {
                $synced++;
            }

            usleep(250000); // Sleep 250ms to be safe and avoid rate limit

            // Limit execution time to 5 seconds to avoid blocking page load
            if ((microtime(true) - $startTime) >= 5.0) {
                break;
            }
        }

        return ['total' => $total, 'synced' => $synced];
    }

    public function syncDatePatientsDirectly(string $date): array
    {
        $kdPj             = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli      = config('mobilejkn.exclude_poli', 'HD, HDL,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        $query = RegPeriksa::with(['referensiMobilejknBpjs'])
            ->where('tgl_registrasi', $date)
            ->where('kd_pj', $kdPj);

        if (! empty($excludePoliArray)) {
            $query->whereNotIn('kd_poli', $excludePoliArray);
        }

        $registrations = $query->get();
        $total         = $registrations->count();
        $synced        = 0;
        $failed        = 0;

        foreach ($registrations as $reg) {
            $kodebooking = $reg->referensiMobilejknBpjs?->nobooking ?? $reg->no_rawat;
            if (! $kodebooking) {
                continue;
            }

            $result = $this->syncSinglePatient($kodebooking, $reg->no_rawat);
            if ($result['success']) {
                $synced++;
            } else {
                $failed++;
            }

            usleep(250000); // Sleep 250ms to be safe and avoid rate limit
        }

        return ['total' => $total, 'synced' => $synced, 'failed' => $failed];
    }

    /**
     * Get flow analytics data for the date range
     *
     * @param string|null $dateFrom
     * @param string|null $dateTo
     * @return array<string, mixed>
     */
    public function getAnalyticsData(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: Carbon::now()->toDateString();
        $dateTo   = $dateTo ?: Carbon::now()->toDateString();

        $dateFromObj = Carbon::parse($dateFrom);
        $dateToObj   = Carbon::parse($dateTo);
        $todayStr    = Carbon::now()->toDateString();

        $kdPj             = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli      = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));
        $excludePoliArray = array_unique(array_merge($excludePoliArray, ['HD', 'IGD', 'IGDK', 'HDL']));

        // Group & count registrations by date in SIMRS (filtered by BPJ and excluding polikliniks)
        $simrsQuery = RegPeriksa::whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
            ->where('kd_pj', $kdPj);
        if (! empty($excludePoliArray)) {
            $simrsQuery->whereNotIn('kd_poli', $excludePoliArray);
        }
        $simrsRegistrationsByDate = $simrsQuery->groupBy('tgl_registrasi')
            ->selectRaw('tgl_registrasi, count(*) as count')
            ->pluck('count', 'tgl_registrasi')
            ->toArray();

        // 1. If date range is today and cache count is 0, trigger syncTodayIfEmpty
        if ($dateFrom === $todayStr && $dateTo === $todayStr) {
            $existingCount = BpjsPatientVisit::where('tanggalperiksa', $todayStr)->count();
            if ($existingCount === 0 && isset($simrsRegistrationsByDate[$todayStr]) && $simrsRegistrationsByDate[$todayStr] > 0) {
                $this->syncTodayIfEmpty($todayStr);
            }
        }

        // 2. Load visits from bpjs_patient_visits cache table (strictly source from BPJS) with eager loading
        $visitsQuery = BpjsPatientVisit::with([
            'regPeriksa.pasien',
            'regPeriksa.poliklinik',
            'regPeriksa.dokter',
            'regPeriksa.referensiMobilejknBpjs',
            'regPeriksa.referensiMobilejknBpjsTaskid',
            'regPeriksa.pemeriksaanRalan',
            'regPeriksa.resepObat',
        ])
            ->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

        if (! empty($excludePoliArray)) {
            $visitsQuery->whereNotIn('kodepoli', $excludePoliArray);
        }

        $visits = $visitsQuery->orderBy('tanggalperiksa')
            ->orderBy('id')
            ->get();

        $visitsByNoRawat = $visits->keyBy('no_rawat');

        // Check if there are any registrations in SIMRS for this date range that are not in bpjs_patient_visits
        $simrsRegsQuery = RegPeriksa::with(['referensiMobilejknBpjs', 'dokter', 'poliklinik'])
            ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
            ->where('kd_pj', $kdPj);
        if (! empty($excludePoliArray)) {
            $simrsRegsQuery->whereNotIn('kd_poli', $excludePoliArray);
        }
        $simrsRegs = $simrsRegsQuery->get();

        $missingRegs = [];
        foreach ($simrsRegs as $reg) {
            if (! $visitsByNoRawat->has($reg->no_rawat)) {
                $missingRegs[] = $reg;
            }
        }

        if (! empty($missingRegs)) {
            $syncCount = 0;
            foreach ($missingRegs as $reg) {
                $kodebooking = $reg->referensiMobilejknBpjs?->nobooking ?? $reg->no_rawat;
                if (! $kodebooking) {
                    continue;
                }

                // Auto-sync up to 5 missing patients on page load to avoid request timeouts
                if ($syncCount < 5) {
                    $this->syncSinglePatient($kodebooking, $reg->no_rawat);
                    $syncCount++;
                } else {
                    // Create shell record so they show up on the dashboard list
                    BpjsPatientVisit::updateOrCreate(
                        ['kodebooking' => $kodebooking],
                        [
                            'no_rawat'       => $reg->no_rawat,
                            'tanggalperiksa' => $reg->tgl_registrasi,
                            'norm'           => $reg->no_rkm_medis,
                            'kodepoli'       => $reg->kd_poli,
                            'namapoli'       => $reg->poliklinik?->nm_poli,
                            'kodedokter'     => $reg->dokter?->kd_dokter,
                            'namadokter'     => $reg->dokter?->nm_dokter,
                            'task_data'      => [],
                            'last_sync'      => null,
                        ]
                    );
                }
            }

            // Reload visits to include newly created/synced ones
            $visitsQuery = BpjsPatientVisit::with([
                'regPeriksa.pasien',
                'regPeriksa.poliklinik',
                'regPeriksa.dokter',
                'regPeriksa.referensiMobilejknBpjs',
                'regPeriksa.referensiMobilejknBpjsTaskid',
                'regPeriksa.pemeriksaanRalan',
                'regPeriksa.resepObat',
            ])
                ->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

            if (! empty($excludePoliArray)) {
                $visitsQuery->whereNotIn('kodepoli', $excludePoliArray);
            }

            $visits = $visitsQuery->orderBy('tanggalperiksa')
                ->orderBy('id')
                ->get();
        }

        // Load doctor mappings from maping_dokter_dpjpvclaim
        $doctorMappings = MapingDokterDpjpvclaim::with('dokter')->get()->keyBy('kd_dokter_bpjs');

        // 3. Get visits count grouped by date
        $visitsCountQuery = BpjsPatientVisit::whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);
        if (! empty($excludePoliArray)) {
            $visitsCountQuery->whereNotIn('kodepoli', $excludePoliArray);
        }
        $visitsCountByDate = $visitsCountQuery->groupBy('tanggalperiksa')
            ->selectRaw('tanggalperiksa, count(*) as count')
            ->pluck('count', 'tanggalperiksa')
            ->toArray();

        $daysInRange  = $dateFromObj->diffInDays($dateToObj) + 1;
        $missingDates = array_keys(array_diff_key($simrsRegistrationsByDate, $visitsCountByDate));

        // 4. Build flows from JKN task data via helper
        $patientFlows = $this->buildPatientFlows($visits, $doctorMappings);

        // Aggregate statistics using the computed JKN flows
        $stats       = $this->calculateStatistics($patientFlows);
        $clinicStats = $this->getClinicStatistics($patientFlows);
        $doctorStats = $this->getDoctorStatistics($patientFlows);
        $timeDist    = $this->getTimeDistribution($patientFlows);
        $anomalies   = $this->aggregateAnomalies($patientFlows);
        $globalStats = $this->calculateGlobalStats($patientFlows);

        // Count cancelled patients
        $batalCount = collect($patientFlows)->where('status', 'Batal')->count();

        return [
            'date_from'               => $dateFrom,
            'date_to'                 => $dateTo,
            'days_in_range'           => $daysInRange,
            'days_with_registrations' => count($simrsRegistrationsByDate),
            'days_with_data'          => count($visitsCountByDate),
            'missing_dates'           => $missingDates,
            'patients'                => $patientFlows,
            'stats'                   => $stats,
            'clinic_stats'            => $clinicStats,
            'doctor_stats'            => $doctorStats,
            'time_distribution'       => $timeDist,
            'global_stats'            => $globalStats,
            'anomalies'               => $anomalies,
            'summary'                 => [
                'total_patients'     => $stats['total_patients'],
                'batal_patients'     => $batalCount,
                'completed_patients' => $stats['completed'],
                'waiting_patients'   => $stats['waiting'] + $stats['in_progress'],
            ],
        ];
    }

    /**
     * Build patient flow structures from visit records
     *
     * @param \Illuminate\Support\Collection $visits
     * @param \Illuminate\Support\Collection $doctorMappings
     * @return array
     */
    private function buildPatientFlows($visits, $doctorMappings): array
    {
        $patientFlows = [];
        foreach ($visits as $visit) {
            /** @var BpjsPatientVisit $visit */
            $realTimestamps = $visit->regPeriksa
                ? $this->getRealTimestamps($visit->regPeriksa)
                : [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];

            $taskData    = $visit->task_data;
            $hasBpjsData = ($taskData !== null && count($taskData) > 0);
            $syncStatus  = $hasBpjsData ? 'synced' : 'pending';

            if ($hasBpjsData) {
                $durations = $this->computeDurationsFromTaskData($taskData);
                $status    = $this->determineStatusFromTaskData($taskData);
            } else {
                $durations = [
                    'checkin_to_nurse'     => null,
                    'nurse_to_doctor'      => null,
                    'doctor_to_pharmacy'   => null,
                    'pharmacy_to_done'     => null,
                    'total_time'           => null,
                    'waktu_tunggu_poli'    => null,
                    'waktu_layan_poli'     => null,
                    'waktu_tunggu_farmasi' => null,
                    'waktu_layan_farmasi'  => null,
                    'total_waktu_rs'       => null,
                ];
                $status = 'Belum Terkirim';
            }

            $isBatalInSimrs = ($visit->regPeriksa && strtolower(trim($visit->regPeriksa->stts ?? '')) === 'batal');
            $isRefBatal     = ($visit->status === 'Batal');

            if ($isBatalInSimrs || $isRefBatal) {
                $status = 'Batal';
            }

            $bpjsTimestamps = $this->getBpjsTimestamps($visit);
            $comparison     = $this->compareBpjsAndSimrs($bpjsTimestamps, $realTimestamps);
            $anomalies      = $this->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);

            // Resolve doctor name using SIMRS first, then mapping table, then BPJS namadokter
            $docName = 'N/A';
            if ($visit->regPeriksa && $visit->regPeriksa->dokter) {
                $docName = $visit->regPeriksa->dokter->nm_dokter;
            } elseif ($visit->kodedokter && isset($doctorMappings[$visit->kodedokter]) && $doctorMappings[$visit->kodedokter]->dokter) {
                $docName = $doctorMappings[$visit->kodedokter]->dokter->nm_dokter;
            } else {
                $docName = $visit->namadokter ?? 'N/A';
            }

            $waktuBatal = null;
            if ($hasBpjsData && is_array($taskData)) {
                foreach ($taskData as $t) {
                    if ((int) ($t['taskid'] ?? 0) === 99 && ! empty($t['wakturs'])) {
                        $parsedBatal = $this->parseTaskWaktu($t['wakturs']);
                        if ($parsedBatal) {
                            $waktuBatal = $parsedBatal->toDateTimeString();
                        }
                        break;
                    }
                }
            }
            if (! $waktuBatal && $visit->regPeriksa) {
                $batalRecord = \App\Models\ReferensiMobilejknBpjsBatal::where('nobooking', $visit->kodebooking)->first();
                if ($batalRecord && $batalRecord->tanggalbatal) {
                    $waktuBatal = $batalRecord->tanggalbatal->toDateTimeString();
                }
            }

            // Determine source: JKN or Onsite
            $hasBooking = $visit->regPeriksa && $visit->regPeriksa->referensiMobilejknBpjs;
            if (! $hasBooking && strpos($visit->kodebooking, '/') === false) {
                $hasBooking = $visit->regPeriksa && $visit->regPeriksa->referensiMobilejknBpjsAll && $visit->regPeriksa->referensiMobilejknBpjsAll->isNotEmpty();
            }
            $sumber = $hasBooking ? 'Mobile JKN' : 'Onsite';

            $patientFlows[] = [
                'sumber'          => $sumber,
                'no_rawat'        => $visit->no_rawat,
                'no_rkm_medis'    => $visit->norm ?? $visit->regPeriksa?->no_rkm_medis,
                'nm_pasien'       => $visit->regPeriksa?->pasien?->nm_pasien ?? 'N/A',
                'nm_poli'         => $visit->namapoli ?? ($visit->regPeriksa?->poliklinik?->nm_poli ?? 'N/A'),
                'nm_dokter'       => $docName,
                'jam_reg'         => $visit->regPeriksa?->jam_reg ? ($visit->regPeriksa->jam_reg instanceof \DateTimeInterface ? $visit->regPeriksa->jam_reg->format('H:i') : substr((string) $visit->regPeriksa->jam_reg, 0, 5)) : '00:00',
                'tgl_registrasi'  => $visit->tanggalperiksa ? ($visit->tanggalperiksa instanceof Carbon ? $visit->tanggalperiksa->toDateString() : (string) $visit->tanggalperiksa) : '',
                'has_booking'     => (strpos($visit->kodebooking, '/') === false),
                'kode_booking'    => $visit->kodebooking,
                'timestamps_real' => $this->formatTimestampMap($realTimestamps),
                'timestamps_sent' => $this->formatTimestampMap($bpjsTimestamps),
                'durations'       => $durations,
                'status'          => $status,
                'waktu_batal'     => $waktuBatal,
                'anomalies'       => $anomalies,
                'has_anomalies'   => count($anomalies) > 0,
                'comparison'      => $comparison,
                'is_bpjs_source'  => $syncStatus === 'synced',
                'sync_status'     => $syncStatus,
                'last_sync'       => $visit->last_sync?->toDateTimeString(),
            ];
        }

        return $patientFlows;
    }

    private function calculateGlobalStats(array $patientFlows): array
    {
        $stats = [
            'waktu_tunggu_poli'    => ['median' => 0, 'count' => 0],
            'waktu_layan_poli'     => ['median' => 0, 'count' => 0],
            'waktu_tunggu_farmasi' => ['median' => 0, 'count' => 0],
            'waktu_layan_farmasi'  => ['median' => 0, 'count' => 0],
            'total_waktu_rs'       => ['median' => 0, 'count' => 0],
        ];

        $durationsByKey = [];
        foreach ($stats as $key => $_) {
            $durationsByKey[$key] = [];
        }

        foreach ($patientFlows as $p) {
            foreach (array_keys($stats) as $key) {
                if (isset($p['durations'][$key]) && $p['durations'][$key] !== null) {
                    $durationsByKey[$key][] = $p['durations'][$key];
                }
            }
        }

        foreach ($stats as $key => &$stat) {
            $durations     = $durationsByKey[$key];
            $stat['count'] = count($durations);
            if ($stat['count'] > 0) {
                sort($durations);
                $mid            = floor(($stat['count'] - 1) / 2);
                $stat['median'] = $stat['count'] % 2
                    ? $durations[$mid]
                    : (($durations[$mid] + $durations[$mid + 1]) / 2);
                $stat['median'] = round($stat['median'], 1);
            }
        }

        return $stats;
    }

    /**
     * Synchronize a single patient's task data from BPJS API
     *
     * @param string $kodebooking
     * @param string $noRawat
     * @return array<string, mixed>
     */
    public function syncSinglePatient(string $kodebooking, string $noRawat): array
    {
        try {
            $reg = RegPeriksa::with([
                'referensiMobilejknBpjs',
                'referensiMobilejknBpjsTaskid',
                'pasien',
                'poliklinik',
                'dokter',
            ])->find($noRawat);

            if (! $reg) {
                return ['success' => false, 'message' => 'Pasien tidak ditemukan'];
            }

            $listTaskResult = app(MobileJknService::class)->getListTask($kodebooking);

            if (! $listTaskResult['success']) {
                return [
                    'success' => false,
                    'message' => $listTaskResult['message'] ?? $listTaskResult['error'] ?? 'Gagal mengambil data dari BPJS',
                ];
            }

            $visitData              = $this->buildVisitDataFromReg($reg, $kodebooking);
            $visitData['task_data'] = $listTaskResult['data'] ?? [];

            BpjsPatientVisit::updateOrCreate(['kodebooking' => $kodebooking], $visitData);

            $cachedVisit    = BpjsPatientVisit::where('kodebooking', $kodebooking)->first();
            $realTimestamps = $this->getRealTimestamps($reg);
            $bpjsTimestamps = $this->getBpjsTimestamps($cachedVisit);
            $durations      = $this->computeDurations($bpjsTimestamps);
            $status         = $this->determineFlowStatus($bpjsTimestamps, $reg->stts ?? '');
            $anomalies      = $this->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);
            $comparison     = $this->compareBpjsAndSimrs($bpjsTimestamps, $realTimestamps);

            // Map durations
            $mappedDurations = array_merge($durations, [
                'waktu_tunggu_poli'    => $durations['checkin_to_nurse'],
                'waktu_layan_poli'     => $durations['nurse_to_doctor'],
                'waktu_tunggu_farmasi' => $durations['doctor_to_pharmacy'],
                'waktu_layan_farmasi'  => $durations['pharmacy_to_done'],
                'total_waktu_rs'       => $durations['total_time'],
            ]);

            return [
                'success' => true,
                'patient' => [
                    'kode_booking'    => $kodebooking,
                    'no_rawat'        => $reg->no_rawat,
                    'no_rkm_medis'    => $reg->no_rkm_medis,
                    'nm_pasien'       => $reg->pasien->nm_pasien ?? 'N/A',
                    'nm_poli'         => $reg->poliklinik->nm_poli ?? 'N/A',
                    'nm_dokter'       => $reg->dokter->nm_dokter ?? 'N/A',
                    'tgl_registrasi'  => (string) $reg->tgl_registrasi,
                    'timestamps_real' => $this->formatTimestampMap($realTimestamps),
                    'timestamps_sent' => $this->formatTimestampMap($bpjsTimestamps),
                    'durations'       => $mappedDurations,
                    'status'          => $status,
                    'anomalies'       => $anomalies,
                    'has_anomalies'   => count($anomalies) > 0,
                    'comparison'      => $comparison,
                    'is_bpjs_source'  => true,
                    'sync_status'     => 'synced',
                    'last_sync'       => $cachedVisit->last_sync->toDateTimeString(),
                ],
            ];
        } catch (\Exception $e) {
            Log::error("Error syncing patient {$kodebooking}: " . $e->getMessage());
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }

    /**
     * Helper to construct visit data payload from SIMRS registration record
     *
     * @param RegPeriksa $reg
     * @param string $kodebooking
     * @return array<string, mixed>
     */
    private function buildVisitDataFromReg(RegPeriksa $reg, string $kodebooking): array
    {
        $visitData = [
            'kodebooking'    => $kodebooking,
            'last_sync'      => now(),
            'no_rawat'       => $reg->no_rawat,
            'tanggalperiksa' => $reg->tgl_registrasi,
        ];

        if ($reg->referensiMobilejknBpjs) {
            $rawValidasi = $reg->referensiMobilejknBpjs->validasi ?? null;
            $validasiVal = null;
            if ($rawValidasi) {
                if ($rawValidasi instanceof \DateTimeInterface) {
                    if ((int) $rawValidasi->format('Y') > 1970) {
                        $validasiVal = $rawValidasi;
                    }
                } else {
                    $vStr = (string) $rawValidasi;
                    if ($vStr !== '' && $vStr !== '0000-00-00 00:00:00' && strpos($vStr, '-0001') === false && strpos($vStr, '0000-') === false) {
                        $validasiVal = $vStr;
                    }
                }
            }

            return array_merge($visitData, [
                'nomorkartu'       => $reg->referensiMobilejknBpjs->nomorkartu ?? null,
                'nik'              => $reg->referensiMobilejknBpjs->nik ?? null,
                'nohp'             => $reg->referensiMobilejknBpjs->nohp ?? null,
                'norm'             => $reg->referensiMobilejknBpjs->norm ?? null,
                'kodepoli'         => $reg->referensiMobilejknBpjs->kodepoli ?? null,
                'namapoli'         => $reg->poliklinik?->nm_poli ?? null,
                'kodedokter'       => $reg->referensiMobilejknBpjs->kodedokter ?? null,
                'namadokter'       => $reg->dokter?->nm_dokter ?? null,
                'jampraktek'       => $reg->referensiMobilejknBpjs->jampraktek ?? null,
                'jeniskunjungan'   => $reg->referensiMobilejknBpjs->jeniskunjungan ? intval($reg->referensiMobilejknBpjs->jeniskunjungan) : null,
                'nomorreferensi'   => $reg->referensiMobilejknBpjs->nomorreferensi ?? null,
                'nomorantrean'     => $reg->referensiMobilejknBpjs->nomorantrean ?? null,
                'angkaantrean'     => $reg->referensiMobilejknBpjs->angkaantrean ?? null,
                'estimasidilayani' => $reg->referensiMobilejknBpjs->estimasidilayani ?? null,
                'sisakuotajkn'     => $reg->referensiMobilejknBpjs->sisakuotajkn ?? null,
                'kuotajkn'         => $reg->referensiMobilejknBpjs->kuotajkn ?? null,
                'sisakuotanonjkn'  => $reg->referensiMobilejknBpjs->sisakuotanonjkn ?? null,
                'kuotanonjkn'      => $reg->referensiMobilejknBpjs->kuotanonjkn ?? null,
                'status'           => $reg->referensiMobilejknBpjs->status ?? null,
                'validasi'         => $validasiVal,
            ]);
        }

        return array_merge($visitData, [
            'nomorkartu'   => $reg->pasien->no_peserta ?? null,
            'nik'          => $reg->pasien->no_ktp ?? null,
            'nohp'         => $reg->pasien->no_tlp ?? null,
            'norm'         => $reg->no_rkm_medis,
            'kodepoli'     => $reg->kd_poli,
            'namapoli'     => $reg->poliklinik?->nm_poli ?? null,
            'kodedokter'   => $reg->kd_dokter,
            'namadokter'   => $reg->dokter?->nm_dokter ?? null,
            'nomorantrean' => $reg->no_reg,
            'angkaantrean' => intval($reg->no_reg),
        ]);
    }

    private function parseTaskWaktu($waktu, $defaultDate = null): ?Carbon
    {
        if (! $waktu) {
            return null;
        }

        if (is_string($waktu)) {
            $waktu = trim(str_replace(['WIB', 'WITA', 'WIT'], '', $waktu));
            if (preg_match('/^\d{2}\.\d{2}\.\d{2}$/', $waktu)) {
                $waktu = str_replace('.', ':', $waktu);
            }
            if (preg_match('/^(\d{4}-\d{2}-\d{2}) (\d{2})\.(\d{2})\.(\d{2})$/', $waktu, $m)) {
                $waktu = "{$m[1]} {$m[2]}:{$m[3]}:{$m[4]}";
            }
            if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $waktu) && $defaultDate) {
                $dateStr = $defaultDate instanceof Carbon ? $defaultDate->toDateString() : (string)$defaultDate;
                $dateStr = explode(' ', $dateStr)[0];
                $waktu = "{$dateStr} {$waktu}";
            }
        }

        if (is_numeric($waktu)) {
            $val = (int) $waktu;
            if ($val > 0) {
                if ($val < 9999999999) {
                    $val = $val * 1000;
                }
                return Carbon::createFromTimestampMs($val, 'Asia/Jakarta');
            }
            return null;
        }

        try {
            return Carbon::parse($waktu, 'Asia/Jakarta');
        } catch (\Exception $e) {
            try {
                return Carbon::createFromFormat('d-m-Y H:i:s', $waktu, 'Asia/Jakarta');
            } catch (\Exception $e2) {
                try {
                    return Carbon::createFromFormat('Y-m-d H:i:s', $waktu, 'Asia/Jakarta');
                } catch (\Exception $e3) {
                    return null;
                }
            }
        }
    }

    public function getBpjsTimestamps(BpjsPatientVisit $visit): array
    {
        $timestamps = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null, 99 => null];
        $taskData   = $visit->task_data ?? [];
        if (! is_array($taskData)) {
            $taskData = json_decode($taskData, true) ?: [];
        }
        foreach ($taskData as $task) {
            $taskId = (int) ($task['taskid'] ?? $task['task_id'] ?? 0);
            $waktu  = $task['wakturs'] ?? $task['waktu'] ?? $task['waktu_sent'] ?? null;
            if ($taskId > 0 && $waktu && array_key_exists($taskId, $timestamps)) {
                $timestamps[$taskId] = $this->parseTaskWaktu($waktu, $visit->tanggalperiksa);
            }
        }
        return $timestamps;
    }

    public function compareBpjsAndSimrs(array $bpjsTimestamps, array $simrsTimestamps): array
    {
        $comparison = [];
        $tasksToMonitor = self::MONITOR_TASKS;
        
        if (!empty($bpjsTimestamps[99]) || !empty($simrsTimestamps[99])) {
            $tasksToMonitor[] = 99;
        }

        foreach ($tasksToMonitor as $taskId) {
            $bpjsTime    = $bpjsTimestamps[$taskId] ?? null;
            $simrsTime   = $simrsTimestamps[$taskId] ?? null;
            $diffMinutes = null;
            $status      = 'missing';

            if ($bpjsTime && $simrsTime) {
                $diffMinutes = $bpjsTime->diffInMinutes($simrsTime, false);
                $status      = abs($diffMinutes) <= 5 ? 'match' : 'mismatch';
            } elseif ($bpjsTime) {
                $status = 'bpjs_only';
            } elseif ($simrsTime) {
                $status = 'simrs_only';
            }

            $comparison[$taskId] = [
                'status'       => $status,
                'diff_minutes' => $diffMinutes,
                'task_name'    => self::TASK_NAMES[$taskId],
            ];
        }
        return $comparison;
    }

    public function getRealTimestamps(RegPeriksa $reg): array
    {
        $timestamps = [
            1 => null,
            2 => null,
            3 => null,
            4 => null,
            5 => null,
            6 => null,
            7 => null,
            99 => null,
        ];

        // Task 1 & 2: Registration
        $timestamps[1] = ($reg->tgl_registrasi && (string)$reg->jam_reg !== '00:00:00')
            ? $this->parseTimestamp($reg->tgl_registrasi, $reg->jam_reg)
            : null;
        $timestamps[2] = $timestamps[1];

        // Task 3: Check-in / Waktu Validasi Mobile JKN (jika ada data), jika tidak ambil dari RegPeriksa (tgl_registrasi + jam_reg)
        $refMjkn = $reg->referensiMobilejknBpjs;
        if ($refMjkn) {
            // Pasien MJKN: Jika validasi ada, parse datanya. Jika kosong/0000, kembalikan '00.00.00'
            $timestamps[3] = '00.00.00';
            if ($refMjkn->validasi) {
                $validasiStr = (string) $refMjkn->validasi;
                if ($validasiStr !== '0000-00-00 00:00:00' && $validasiStr !== '' && strpos($validasiStr, '-0001') === false && strpos($validasiStr, '0000-') === false) {
                    $parsedV = Carbon::parse($validasiStr);
                    if ($parsedV->year > 1970) {
                        $timestamps[3] = $parsedV;
                    }
                }
            }
        } else {
            // Pasien Onsite: fallback ke jam registrasi SIMRS
            $timestamps[3] = $timestamps[1];
        }

        // Task 4: Pemeriksaan Ralan - Cek NIP Petugas (Perawat)
        if ($reg->pemeriksaanRalan && $reg->pemeriksaanRalan->isNotEmpty()) {
            $nipsInPemeriksaan = $reg->pemeriksaanRalan->pluck('nip')->filter()->unique()->toArray();
            $petugasNips = !empty($nipsInPemeriksaan) ? \App\Models\Petugas::whereIn('nip', $nipsInPemeriksaan)->pluck('nip')->toArray() : [];

            $pemeriksaanPetugas = $reg->pemeriksaanRalan
                ->filter(function($p) use ($petugasNips) {
                    if (empty($p->nip) || (string)$p->jam_rawat === '00:00:00') {
                        return false;
                    }
                    return $p->petugas !== null || in_array($p->nip, $petugasNips);
                })
                ->sortBy('jam_rawat')
                ->first();

            if (!$pemeriksaanPetugas) {
                $pemeriksaanPetugas = $reg->pemeriksaanRalan
                    ->filter(fn($p) => (string)$p->jam_rawat !== '00:00:00')
                    ->sortBy('jam_rawat')
                    ->first();
            }

            if ($pemeriksaanPetugas && $pemeriksaanPetugas->jam_rawat) {
                $timestamps[4] = $this->parseTimestamp($pemeriksaanPetugas->tgl_perawatan, $pemeriksaanPetugas->jam_rawat);
            }
        }

        // Task 5: Pemeriksaan Ralan - Cek NIP Dokter
        if ($reg->pemeriksaanRalan && $reg->pemeriksaanRalan->isNotEmpty()) {
            $nipsInPemeriksaan = $reg->pemeriksaanRalan->pluck('nip')->filter()->unique()->toArray();
            $dokterNips = !empty($nipsInPemeriksaan) ? \App\Models\Dokter::whereIn('kd_dokter', $nipsInPemeriksaan)->pluck('kd_dokter')->toArray() : [];

            $pemeriksaanDokter = $reg->pemeriksaanRalan
                ->filter(function($p) use ($dokterNips) {
                    if (empty($p->nip) || (string)$p->jam_rawat === '00:00:00') {
                        return false;
                    }
                    return $p->dokter !== null || in_array($p->nip, $dokterNips);
                })
                ->sortByDesc('jam_rawat')
                ->first();

            if (!$pemeriksaanDokter) {
                $pemeriksaanDokter = $reg->pemeriksaanRalan
                    ->filter(fn($p) => (string)$p->jam_rawat !== '00:00:00')
                    ->sortByDesc('jam_rawat')
                    ->first();
            }

            if ($pemeriksaanDokter && $pemeriksaanDokter->jam_rawat) {
                $timestamps[5] = $this->parseTimestamp($pemeriksaanDokter->tgl_perawatan, $pemeriksaanDokter->jam_rawat);
            }
        }

        // Task 6: Resep Obat - Jam Peresapan / Pembuatan Resep (ResepObat.jam)
        if ($reg->resepObat && $reg->resepObat->isNotEmpty()) {
            $resep6 = $reg->resepObat
                ->filter(fn($r) => !empty($r->jam) && (string)$r->jam !== '00:00:00')
                ->sortByDesc('jam')
                ->first();
            if ($resep6 && $resep6->jam) {
                $timestamps[6] = $this->parseTimestamp($resep6->tgl_perawatan ?: $resep6->tgl_peresapan ?: $reg->tgl_registrasi, $resep6->jam);
            }
        }

        // Task 7: Resep Obat - Jam Penyerahan Obat (ResepObat.jam_penyerahan)
        if ($reg->resepObat && $reg->resepObat->isNotEmpty()) {
            $resep7 = $reg->resepObat
                ->filter(fn($r) => !empty($r->jam_penyerahan) && (string)$r->jam_penyerahan !== '00:00:00')
                ->sortByDesc('jam_penyerahan')
                ->first();
            if ($resep7 && $resep7->jam_penyerahan) {
                $timestamps[7] = $this->parseTimestamp($resep7->tgl_penyerahan ?: $resep7->tgl_perawatan ?: $reg->tgl_registrasi, $resep7->jam_penyerahan);
            }
        }

        // Task 99: Pembatalan Antrean
        $batal = \App\Models\ReferensiMobilejknBpjsBatal::where('no_rawat_batal', $reg->no_rawat)->first();
        if ($batal && $batal->tanggalbatal) {
            $timestamps[99] = Carbon::parse($batal->tanggalbatal);
        }

        return $timestamps;
    }

    private function parseTimestamp($date, $time = null): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $dateStr = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        if ($time instanceof \DateTimeInterface) {
            $timeStr = $time->format('H:i:s');
        } else {
            $timeStr = $time ? (string) $time : '00:00:00';
        }
        $dateStr = explode(' ', $dateStr)[0];

        try {
            return Carbon::parse("{$dateStr} {$timeStr}");
        } catch (\Exception $e) {
            return null;
        }
    }

    public function computeDurations(array $timestamps): array
    {
        $durations = [
            'checkin_to_nurse'   => null,
            'nurse_to_doctor'    => null,
            'doctor_to_pharmacy' => null,
            'pharmacy_to_done'   => null,
            'total_time'         => null,
        ];

        if ($timestamps[3] && $timestamps[4]) {
            $durations['checkin_to_nurse'] = $timestamps[4]->diffInMinutes($timestamps[3]);
        }
        if ($timestamps[4] && $timestamps[5]) {
            $durations['nurse_to_doctor'] = $timestamps[5]->diffInMinutes($timestamps[4]);
        }
        if ($timestamps[5] && $timestamps[6]) {
            $durations['doctor_to_pharmacy'] = $timestamps[6]->diffInMinutes($timestamps[5]);
        }
        if ($timestamps[6] && $timestamps[7]) {
            $durations['pharmacy_to_done'] = $timestamps[7]->diffInMinutes($timestamps[6]);
        }
        if ($timestamps[3] && $timestamps[7]) {
            $durations['total_time'] = $timestamps[7]->diffInMinutes($timestamps[3]);
        }

        return $durations;
    }

    private function determineFlowStatus(array $bpjsTimestamps, string $stts): string
    {
        $present = [];
        foreach ([3, 4, 5, 6, 7] as $tid) {
            if ($bpjsTimestamps[$tid] !== null) {
                $present[] = $tid;
            }
        }

        if ($stts == 'Batal') {
            return 'Batal';
        }

        if (empty($present)) {
            if ($stts == 'Sudah') {
                return 'Lengkap (3,4,5,6,7)';
            }
            return 'Belum Terkirim';
        }

        if ($present === [3, 4, 5, 6, 7]) {
            return 'Lengkap (3,4,5,6,7)';
        }
        if ($present === [3, 4, 5, 6]) {
            return 'Lengkap (3,4,5,6) - Farmasi Belum Selesai';
        }
        if ($present === [3, 4, 5]) {
            return 'Task 3,4,5';
        }
        if ($present === [3, 4]) {
            return 'Task 3,4';
        }
        if ($present === [3]) {
            return 'Task 3';
        }

        return 'Task ' . implode(',', $present);
    }

    private function aggregateAnomalies(array $patients): array
    {
        $counts = [
            'total_anomalies'  => 0,
            'timestamp_buatan' => [],
            'durasi_negatif'   => [],
            'farmasi_10_menit' => [],
            'outlier_durasi'   => [],
            'belum_terkirim'   => [],
        ];

        foreach ($patients as $p) {
            if (count($p['anomalies']) > 0) {
                $counts['total_anomalies']++;
                foreach ($p['anomalies'] as $type) {
                    if (isset($counts[$type])) {
                        $counts[$type][] = [
                            'no_rawat'  => $p['no_rawat'],
                            'nm_pasien' => $p['nm_pasien'],
                            'nm_poli'   => $p['nm_poli'],
                        ];
                    }
                }
            }
        }

        return $counts;
    }

    public function detectPatientAnomalies(array $real, array $sent, array $durations): array
    {
        $anomalies = [];

        // 1. Timestamp buatan (sent exists but real is null)
        $artificial = false;
        foreach ([4, 5, 6] as $tid) {
            if ($real[$tid] === null && $sent[$tid] !== null) {
                $artificial = true;
            }
        }
        if ($artificial) {
            $anomalies[] = 'timestamp_buatan';
        }

        // 2. Durasi negatif
        $negative = false;
        foreach ($durations as $key => $val) {
            if ($val !== null && $val < 0) {
                $negative = true;
            }
        }
        if ($negative) {
            $anomalies[] = 'durasi_negatif';
        }

        // 3. Farmasi tepat 10 menit
        $waktuTungguFarmasi = $durations['waktu_tunggu_farmasi'] ?? $durations['doctor_to_pharmacy'] ?? null;
        if ($waktuTungguFarmasi !== null && abs($waktuTungguFarmasi - 10.0) < 0.001) {
            $anomalies[] = 'farmasi_10_menit';
        }

        // 4. Outlier (durasi > 180 menit / 3 jam)
        $outlier = false;
        foreach ($durations as $key => $val) {
            if ($val !== null && $val > 180) {
                $outlier = true;
            }
        }
        if ($outlier) {
            $anomalies[] = 'outlier_durasi';
        }

        // 5. Belum terkirim (real exists but sent is null)
        $unsent = false;
        foreach ([3, 4, 5, 6, 7] as $tid) {
            if ($real[$tid] !== null && $sent[$tid] === null) {
                $unsent = true;
            }
        }
        if ($unsent) {
            $anomalies[] = 'belum_terkirim';
        }

        return $anomalies;
    }

    private function calculateStatistics(array $patientFlows): array
    {
        $total         = count($patientFlows);
        $completed     = 0;
        $waiting       = 0;
        $inProgress    = 0;
        $withAnomalies = 0;

        $durations = [
            'waktu_tunggu_poli'    => [],
            'waktu_layan_poli'     => [],
            'waktu_tunggu_farmasi' => [],
            'waktu_layan_farmasi'  => [],
            'total_waktu_rs'       => [],
        ];

        foreach ($patientFlows as $p) {
            if ($p['status'] === 'Lengkap (3,4,5,6,7)') {
                $completed++;
            } elseif ($p['status'] === 'Belum Terkirim') {
                $waiting++;
            } elseif ($p['status'] === 'Batal' || $p['status'] === 'Tidak Terdaftar') {
                // not counted as in progress
            } else {
                $inProgress++;
            }

            if ($p['has_anomalies']) {
                $withAnomalies++;
            }

            foreach (array_keys($durations) as $k) {
                if (isset($p['durations'][$k]) && $p['durations'][$k] !== null) {
                    $durations[$k][] = $p['durations'][$k];
                }
            }
        }

        return [
            'total_patients' => $total,
            'completed'      => $completed,
            'waiting'        => $waiting,
            'in_progress'    => $inProgress,
            'with_anomalies' => $withAnomalies,
            'avg_durations'  => [
                'waktu_tunggu_poli'    => ! empty($durations['waktu_tunggu_poli']) ? round(array_sum($durations['waktu_tunggu_poli']) / count($durations['waktu_tunggu_poli']), 1) : null,
                'waktu_layan_poli'     => ! empty($durations['waktu_layan_poli']) ? round(array_sum($durations['waktu_layan_poli']) / count($durations['waktu_layan_poli']), 1) : null,
                'waktu_tunggu_farmasi' => ! empty($durations['waktu_tunggu_farmasi']) ? round(array_sum($durations['waktu_tunggu_farmasi']) / count($durations['waktu_tunggu_farmasi']), 1) : null,
                'waktu_layan_farmasi'  => ! empty($durations['waktu_layan_farmasi']) ? round(array_sum($durations['waktu_layan_farmasi']) / count($durations['waktu_layan_farmasi']), 1) : null,
                'total_waktu_rs'       => ! empty($durations['total_waktu_rs']) ? round(array_sum($durations['total_waktu_rs']) / count($durations['total_waktu_rs']), 1) : null,
            ],
        ];
    }

    private function computeStats(array $values): array
    {
        $count = count($values);
        if ($count === 0) {
            return ['count' => 0, 'median' => null, 'min' => null, 'max' => null, 'avg' => null, 'p90' => null];
        }

        sort($values);
        $mid    = floor(($count - 1) / 2);
        $median = $count % 2
            ? $values[$mid]
            : (($values[$mid] + $values[$mid + 1]) / 2);

        $avg = array_sum($values) / $count;

        // P90: 90th percentile
        $p90Index = (int) ceil(0.9 * $count) - 1;
        $p90      = $values[min($p90Index, $count - 1)];

        return [
            'count'  => $count,
            'median' => round($median, 1),
            'min'    => round($values[0], 1),
            'max'    => round($values[$count - 1], 1),
            'avg'    => round($avg, 1),
            'p90'    => round($p90, 1),
        ];
    }

    private function getClinicStatistics(array $patientFlows): array
    {
        $byClinic = [];

        foreach ($patientFlows as $p) {
            $clinic = $p['nm_poli'];
            if (! isset($byClinic[$clinic])) {
                $byClinic[$clinic] = [
                    'patient_count'        => 0,
                    'waktu_tunggu_poli'    => [],
                    'waktu_layan_poli'     => [],
                    'waktu_tunggu_farmasi' => [],
                    'waktu_layan_farmasi'  => [],
                    'total_waktu_rs'       => [],
                ];
            }

            if ($p['status'] !== 'Batal') {
                $byClinic[$clinic]['patient_count']++;
                foreach (['waktu_tunggu_poli', 'waktu_layan_poli', 'waktu_tunggu_farmasi', 'waktu_layan_farmasi', 'total_waktu_rs'] as $metric) {
                    if (isset($p['durations'][$metric]) && $p['durations'][$metric] !== null) {
                        $byClinic[$clinic][$metric][] = $p['durations'][$metric];
                    }
                }
            }
        }

        $aggregated = [];
        foreach ($byClinic as $clinic => $data) {
            $aggregated[$clinic] = [
                'patient_count'        => $data['patient_count'],
                'waktu_tunggu_poli'    => $this->computeStats($data['waktu_tunggu_poli']),
                'waktu_layan_poli'     => $this->computeStats($data['waktu_layan_poli']),
                'waktu_tunggu_farmasi' => $this->computeStats($data['waktu_tunggu_farmasi']),
                'waktu_layan_farmasi'  => $this->computeStats($data['waktu_layan_farmasi']),
                'total_waktu_rs'       => $this->computeStats($data['total_waktu_rs']),
            ];
        }

        return $aggregated;
    }

    private function getDoctorStatistics(array $patientFlows): array
    {
        $byDoctor = [];

        foreach ($patientFlows as $p) {
            $doctor = $p['nm_dokter'];
            if (! isset($byDoctor[$doctor])) {
                $byDoctor[$doctor] = [
                    'patient_count'        => 0,
                    'waktu_tunggu_poli'    => [],
                    'waktu_layan_poli'     => [],
                    'waktu_tunggu_farmasi' => [],
                    'waktu_layan_farmasi'  => [],
                    'total_waktu_rs'       => [],
                ];
            }

            if ($p['status'] !== 'Batal') {
                $byDoctor[$doctor]['patient_count']++;
                foreach (['waktu_tunggu_poli', 'waktu_layan_poli', 'waktu_tunggu_farmasi', 'waktu_layan_farmasi', 'total_waktu_rs'] as $metric) {
                    if (isset($p['durations'][$metric]) && $p['durations'][$metric] !== null) {
                        $byDoctor[$doctor][$metric][] = $p['durations'][$metric];
                    }
                }
            }
        }

        $aggregated = [];
        foreach ($byDoctor as $doctor => $data) {
            $aggregated[$doctor] = [
                'patient_count'        => $data['patient_count'],
                'waktu_tunggu_poli'    => $this->computeStats($data['waktu_tunggu_poli']),
                'waktu_layan_poli'     => $this->computeStats($data['waktu_layan_poli']),
                'waktu_tunggu_farmasi' => $this->computeStats($data['waktu_tunggu_farmasi']),
                'waktu_layan_farmasi'  => $this->computeStats($data['waktu_layan_farmasi']),
                'total_waktu_rs'       => $this->computeStats($data['total_waktu_rs']),
            ];
        }

        return $aggregated;
    }

    private function getTimeDistribution(array $patientFlows): array
    {
        $dist = [
            'waktu_tunggu_poli'    => [],
            'waktu_layan_poli'     => [],
            'waktu_tunggu_farmasi' => [],
            'waktu_layan_farmasi'  => [],
        ];
        foreach ($patientFlows as $p) {
            foreach (array_keys($dist) as $k) {
                if (isset($p['durations'][$k]) && $p['durations'][$k] !== null) {
                    $dist[$k][] = $p['durations'][$k];
                }
            }
        }
        return $dist;
    }

    public function buildPatientDetailData(string $identifier, MobileJknService $mobileJknService): ?array
    {
        $bpjsVisit = BpjsPatientVisit::where('kodebooking', $identifier)
            ->orWhere('no_rawat', $identifier)
            ->first();

        $noRawat = $bpjsVisit?->no_rawat ?? $identifier;

        $reg = RegPeriksa::with([
                'pasien',
                'poliklinik',
                'dokter',
                'referensiMobilejknBpjs',
                'referensiMobilejknBpjsAll',
                'referensiMobilejknBpjsTaskid',
                'bridgingSep',
                'pemeriksaanRalan.petugas',
                'pemeriksaanRalan.dokter',
                'resepObat',
            ])
            ->where('no_rawat', $noRawat)
            ->first();

        if (! $reg && ! $bpjsVisit) {
            return null;
        }

        // Try matching specific ReferensiMobilejknBpjs record by nobooking first
        $refThisBooking = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $identifier)->first();
        if (! $refThisBooking && $reg) {
            $refThisBooking = $reg->referensiMobilejknBpjs ?? $reg->referensiMobilejknBpjsAll->first();
        }

        $kodebooking = $refThisBooking?->nobooking ?? $bpjsVisit?->kodebooking ?? $identifier;

        $batalInfo = \App\Models\ReferensiMobilejknBpjsBatal::where('nobooking', $kodebooking)
            ->orWhere('no_rawat_batal', $noRawat)
            ->first();

        if ($kodebooking && (! $bpjsVisit || ! $bpjsVisit->last_sync || $bpjsVisit->last_sync->lt(now()->subMinutes(15)))) {
            $mobileJknService->getListTask($kodebooking);
            $bpjsVisit = BpjsPatientVisit::where('kodebooking', $kodebooking)
                ->orWhere('no_rawat', $noRawat)
                ->first();
        }

        $realTimestamps = $reg ? $this->getRealTimestamps($reg) : [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];

        if ($bpjsVisit) {
            $bpjsTimestamps = $this->getBpjsTimestamps($bpjsVisit);
        } else {
            $bpjsTimestamps = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];
        }

        $hasBpjsData = ($bpjsVisit && $bpjsVisit->task_data !== null && count($bpjsVisit->task_data) > 0);

        if ($hasBpjsData) {
            $durations = $this->computeDurationsFromTaskData($bpjsVisit->task_data);
            $status    = $this->determineStatusFromTaskData($bpjsVisit->task_data);
        } else {
            $durations = [
                'checkin_to_nurse'     => null,
                'nurse_to_doctor'      => null,
                'doctor_to_pharmacy'   => null,
                'pharmacy_to_done'     => null,
                'total_time'           => null,
                'waktu_tunggu_poli'    => null,
                'waktu_layan_poli'     => null,
                'waktu_tunggu_farmasi' => null,
                'waktu_layan_farmasi'  => null,
                'total_waktu_rs'       => null,
            ];
            $status = ($refThisBooking?->status === 'Batal' || strtolower(trim($reg?->stts ?? '')) === 'batal') ? 'Batal' : 'Belum Terkirim';
        }

        if ($refThisBooking?->status === 'Batal' || strtolower(trim($reg?->stts ?? '')) === 'batal') {
            $status = 'Batal';
        }
        $anomalies = $this->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);
        $comparison = $this->compareBpjsAndSimrs($bpjsTimestamps, $realTimestamps);

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

        $noKartuBpjs = null;
        if ($reg) {
            $noKartuBpjs = $reg->bridgingSep->no_kartu ?? $reg->pasien->no_peserta ?? null;
        } elseif ($bpjsVisit) {
            $noKartuBpjs = $bpjsVisit->nomorkartu;
        }

        $docName = 'N/A';
        if ($reg && $reg->dokter) {
            $docName = $reg->dokter->nm_dokter;
        } elseif ($bpjsVisit && $bpjsVisit->kodedokter) {
            $mapping = MapingDokterDpjpvclaim::where('kd_dokter_bpjs', $bpjsVisit->kodedokter)->with('dokter')->first();
            $docName = $mapping->dokter->nm_dokter ?? $bpjsVisit->namadokter ?? 'N/A';
        } else {
            $docName = $bpjsVisit?->namadokter ?? 'N/A';
        }

        $hasBooking = $reg && $reg->referensiMobilejknBpjs;
        if (!$hasBooking && strpos($kodebooking, '/') === false) {
            $hasBooking = $reg && $reg->referensiMobilejknBpjsAll && $reg->referensiMobilejknBpjsAll->isNotEmpty();
        }
        $sumber = $hasBooking ? 'Mobile JKN' : 'Onsite';

        return [
            'sumber'         => $sumber,
            'no_rawat'       => $reg?->no_rawat ?? $bpjsVisit?->no_rawat,
            'no_rkm_medis'   => $reg?->no_rkm_medis ?? $bpjsVisit?->norm,
            'nm_pasien'      => $reg?->pasien->nm_pasien ?? 'N/A',
            'no_ktp'         => $reg?->pasien->no_ktp ?? $bpjsVisit?->nik ?? null,
            'no_kartu_bpjs'  => $noKartuBpjs,
            'tgl_lahir'      => $reg?->pasien?->tgl_lahir ? Carbon::parse($reg->pasien->tgl_lahir)->format('d M Y') : null,
            'jk'             => $reg?->pasien->jk ?? null,
            'nm_poli'        => $reg?->poliklinik->nm_poli ?? $bpjsVisit?->namapoli ?? 'N/A',
            'nm_dokter'      => $docName,
            'tgl_registrasi' => ($bpjsVisit && $bpjsVisit->tanggalperiksa instanceof Carbon) ? $bpjsVisit->tanggalperiksa->format('d M Y') : ($reg?->tgl_registrasi instanceof Carbon ? $reg->tgl_registrasi->format('d M Y') : (string) $reg?->tgl_registrasi),
            'jam_reg'        => $reg?->jam_reg ? ($reg->jam_reg instanceof \DateTimeInterface ? $reg->jam_reg->format('H:i') : substr((string) $reg->jam_reg, 0, 5)) : null,
            'stts'           => $reg?->stts ?? '',
            'has_booking'    => (bool) ($reg?->referensiMobilejknBpjs ?? $bpjsVisit),
            'kode_booking'   => $kodebooking,
            'timestamps_real' => $this->formatTimestampMap($realTimestamps),
            'timestamps_sent' => $this->formatTimestampMap($bpjsTimestamps),
            'durations'      => $durations,
            'status'         => $status,
            'anomalies'      => $anomalies,
            'anomaly_hints'  => $anomalyHints,
            'comparison'     => $comparison,
            'is_bpjs_source' => (bool) $bpjsVisit,
            'batal_info'     => $batalInfo ? [
                'tanggal_batal' => $batalInfo->tanggalbatal ? Carbon::parse($batalInfo->tanggalbatal)->format('d M Y H:i:s') : null,
                'keterangan'    => $batalInfo->keterangan ?? 'Dibatalkan',
            ] : null,
        ];
    }

    public function formatTimestampMap(array $timestamps): object
    {
        $result = [];
        foreach ($timestamps as $taskId => $val) {
            if ($val instanceof Carbon) {
                $result['task_' . $taskId] = $val->toDateTimeString();
            } elseif (is_string($val)) {
                $result['task_' . $taskId] = $val;
            } else {
                $result['task_' . $taskId] = null;
            }
        }
        return (object) $result;
    }
}
