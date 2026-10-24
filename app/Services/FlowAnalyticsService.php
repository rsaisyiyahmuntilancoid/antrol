<?php
namespace App\Services;

use App\Models\BpjsPatientVisit;
use App\Models\MapingDokterDpjpvclaim;
use App\Models\RegPeriksa;
use App\Models\ReferensiMobilejknBpjs;
use App\Models\ReferensiMobilejknBpjsBatal;
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
            'checkin_to_nurse'     => $this->diffMinutes($tasks[3], $tasks[4]),
            'nurse_to_doctor'      => $this->diffMinutes($tasks[4], $tasks[5]),
            'doctor_to_pharmacy'   => $this->diffMinutes($tasks[5], $tasks[6]),
            'pharmacy_to_done'     => $this->diffMinutes($tasks[6], $tasks[7]),
            'total_time'           => $this->diffMinutes($tasks[3], $tasks[7] ?? $tasks[5] ?? null),
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

        $isMultiDay = ($dateFrom !== $dateTo);

        // 1. If single day (today only) and cache count is 0, trigger syncTodayIfEmpty
        if (! $isMultiDay && $dateFrom === $todayStr && $dateTo === $todayStr) {
            $existingCount = BpjsPatientVisit::where('tanggalperiksa', $todayStr)->count();
            if ($existingCount === 0 && isset($simrsRegistrationsByDate[$todayStr]) && $simrsRegistrationsByDate[$todayStr] > 0) {
                $this->syncTodayIfEmpty($todayStr);
            }
        }

        // 2. Load visits from bpjs_patient_visits cache table (strictly from local DB) with lightweight eager loading
        $visitsQuery = BpjsPatientVisit::with([
            'regPeriksa.pasien',
            'regPeriksa.referensiMobilejknBpjs',
        ])
            ->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

        if (! empty($excludePoliArray)) {
            $visitsQuery->whereNotIn('kodepoli', $excludePoliArray);
        }

        $visits = $visitsQuery->orderBy('tanggalperiksa')
            ->orderBy('id')
            ->get();

        // Check for missing bookings in SIMRS ONLY in single-day mode to keep multi-day fast and local-only
        if (! $isMultiDay) {
            $visitsByKodebooking = $visits->keyBy('kodebooking');

            $simrsRegsQuery = RegPeriksa::with(['referensiMobilejknBpjsAll', 'dokter', 'poliklinik'])
                ->where('tgl_registrasi', $dateFrom)
                ->where('kd_pj', $kdPj);
            if (! empty($excludePoliArray)) {
                $simrsRegsQuery->whereNotIn('kd_poli', $excludePoliArray);
            }
            $simrsRegs = $simrsRegsQuery->get();

            $missingBookings = [];
            foreach ($simrsRegs as $reg) {
                $refAll = $reg->referensiMobilejknBpjsAll;
                if ($refAll && $refAll->isNotEmpty()) {
                    foreach ($refAll as $ref) {
                        if ($ref->nobooking && ! $visitsByKodebooking->has($ref->nobooking)) {
                            $missingBookings[] = [
                                'kodebooking' => $ref->nobooking,
                                'no_rawat'    => $reg->no_rawat,
                                'reg'         => $reg,
                                'ref'         => $ref,
                            ];
                        }
                    }
                } else {
                    $kodebooking = $reg->no_rawat;
                    if ($kodebooking && ! $visitsByKodebooking->has($kodebooking)) {
                        $missingBookings[] = [
                            'kodebooking' => $kodebooking,
                            'no_rawat'    => $reg->no_rawat,
                            'reg'         => $reg,
                            'ref'         => null,
                        ];
                    }
                }
            }

            if (! empty($missingBookings)) {
                foreach ($missingBookings as $item) {
                    $kodebooking = $item['kodebooking'];
                    $noRawat     = $item['no_rawat'];
                    $reg         = $item['reg'];
                    $ref         = $item['ref'];

                    // Create shell record without blocking synchronous BPJS HTTP calls to prevent page load timeouts
                    BpjsPatientVisit::updateOrCreate(
                        ['kodebooking' => $kodebooking],
                        [
                            'no_rawat'       => $noRawat,
                            'tanggalperiksa' => $ref->tanggalperiksa ?? $reg->tgl_registrasi,
                            'norm'           => $ref->norm ?? $reg->no_rkm_medis,
                            'kodepoli'       => $ref->kodepoli ?? $reg->kd_poli,
                            'namapoli'       => $reg->poliklinik?->nm_poli,
                            'kodedokter'     => $ref->kodedokter ?? $reg->dokter?->kd_dokter,
                            'namadokter'     => $reg->dokter?->nm_dokter,
                            'task_data'      => [],
                            'last_sync'      => null,
                        ]
                    );
                }

                // Reload visits to include newly created shell records
                $visitsQuery = BpjsPatientVisit::with([
                    'regPeriksa.pasien',
                    'regPeriksa.referensiMobilejknBpjs',
                ])
                    ->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

                if (! empty($excludePoliArray)) {
                    $visitsQuery->whereNotIn('kodepoli', $excludePoliArray);
                }

                $visits = $visitsQuery->orderBy('tanggalperiksa')
                    ->orderBy('id')
                    ->get();
            }
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

        // Calculate status counts for donut chart
        $statusCounts = [];
        foreach ($patientFlows as $p) {
            $st = $p['status'] ?? 'Belum Terkirim';
            $statusCounts[$st] = ($statusCounts[$st] ?? 0) + 1;
        }

        // Collect visits that need background sync ONLY for single-day view (today or single date)
        // Never trigger auto-sync for multi-day date ranges to keep it strictly local and fast
        $needsSync = [];
        if (! $isMultiDay) {
            foreach ($visits as $visit) {
                $hasTaskData = ($visit->task_data !== null && is_array($visit->task_data) && count($visit->task_data) > 0);
                if (! $hasTaskData && $visit->status !== 'Batal') {
                    $needsSync[] = [
                        'kodebooking' => $visit->kodebooking,
                        'no_rawat'    => $visit->no_rawat,
                    ];
                }
            }
        }

        return [
            'date_from'               => $dateFrom,
            'date_to'                 => $dateTo,
            'days_in_range'           => $daysInRange,
            'days_with_registrations' => count($simrsRegistrationsByDate),
            'days_with_data'          => count($visitsCountByDate),
            'missing_dates'           => $missingDates,
            'needs_sync'              => $needsSync,
            'patients'                => $isMultiDay ? array_slice($patientFlows, 0, 200) : $patientFlows,
            'stats'                   => $stats,
            'status_counts'           => $statusCounts,
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
        $visitsCollection = is_array($visits) ? collect($visits) : $visits;
        $kodebookings = $visitsCollection->pluck('kodebooking')->filter()->toArray();
        $noRawats     = $visitsCollection->pluck('no_rawat')->filter()->toArray();

        // Preload batal records in a single query to eliminate N+1 queries
        $batalMap = [];
        if (! empty($kodebookings) || ! empty($noRawats)) {
            $batalRecords = \App\Models\ReferensiMobilejknBpjsBatal::whereIn('nobooking', $kodebookings)
                ->orWhereIn('no_rawat_batal', $noRawats)
                ->get();
            foreach ($batalRecords as $br) {
                if ($br->nobooking) {
                    $batalMap[$br->nobooking] = $br;
                }
                if ($br->no_rawat_batal) {
                    $batalMap[$br->no_rawat_batal] = $br;
                }
            }
        }

        foreach ($visits as $visit) {
            /** @var BpjsPatientVisit $visit */
            $realTimestamps = $visit->regPeriksa
                ? $this->getRealTimestamps($visit->regPeriksa, $visit->kodebooking, $batalMap)
                : [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null, 99 => null];

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
            $batalRecord    = $batalMap[$visit->kodebooking] ?? ($batalMap[$visit->no_rawat] ?? null);

            // If cancelled in SIMRS, BPJS cache status, or batal table, mark status as Batal
            if ($isBatalInSimrs || $isRefBatal || ! empty($batalRecord)) {
                $status = 'Batal';
            }

            $bpjsTimestamps = $this->getBpjsTimestamps($visit);
            $comparison     = $this->compareBpjsAndSimrs($bpjsTimestamps, $realTimestamps);
            $anomalies      = $this->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);

            // Resolve doctor name using local cache first, then mapping or eager loaded SIMRS
            $docName = $visit->namadokter;
            if (empty($docName) || $docName === 'N/A') {
                if ($visit->regPeriksa && $visit->regPeriksa->relationLoaded('dokter') && $visit->regPeriksa->dokter) {
                    $docName = $visit->regPeriksa->dokter->nm_dokter;
                } elseif ($visit->kodedokter && isset($doctorMappings[$visit->kodedokter]) && $doctorMappings[$visit->kodedokter]->dokter) {
                    $docName = $doctorMappings[$visit->kodedokter]->dokter->nm_dokter;
                } else {
                    $docName = 'N/A';
                }
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
            if (! $waktuBatal && $visit->regPeriksa && $batalRecord) {
                if ($batalRecord->tanggalbatal) {
                    $waktuBatal = $batalRecord->tanggalbatal instanceof Carbon
                        ? $batalRecord->tanggalbatal->toDateTimeString()
                        : (string) $batalRecord->tanggalbatal;
                }
            }

            // Determine source: JKN or Onsite without triggering N+1 queries
            $hasBooking = false;
            if ($visit->regPeriksa && $visit->regPeriksa->relationLoaded('referensiMobilejknBpjs')) {
                $hasBooking = ($visit->regPeriksa->referensiMobilejknBpjs !== null);
            }
            if (! $hasBooking && strpos($visit->kodebooking, '/') === false && strlen($visit->kodebooking) > 6) {
                $hasBooking = true;
            }
            $sumber = $hasBooking ? 'Mobile JKN' : 'Onsite';

            $patientFlows[] = [
                'sumber'          => $sumber,
                'no_rawat'        => $visit->no_rawat,
                'no_rkm_medis'    => $visit->norm ?? $visit->regPeriksa?->no_rkm_medis,
                'nm_pasien'       => $visit->regPeriksa?->pasien?->nm_pasien ?? 'N/A',
                'nm_poli'         => $visit->namapoli ?? ($visit->regPeriksa?->poliklinik?->nm_poli ?? 'N/A'),
                'nm_dokter'       => $docName,
                'jam_reg'         => $visit->regPeriksa?->jam_reg ? ($visit->regPeriksa->jam_reg instanceof \DateTimeInterface  ? $visit->regPeriksa->jam_reg->format('H:i') : substr((string) $visit->regPeriksa->jam_reg, 0, 5)) : '00:00',
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
            $realTimestamps = $this->getRealTimestamps($reg, $kodebooking);
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
                $dateStr = $defaultDate instanceof Carbon ? $defaultDate->toDateString() : (string) $defaultDate;
                $dateStr = explode(' ', $dateStr)[0];
                $waktu   = "{$dateStr} {$waktu}";
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
        $comparison     = [];
        $tasksToMonitor = self::MONITOR_TASKS;

        if (! empty($bpjsTimestamps[99]) || ! empty($simrsTimestamps[99])) {
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

    public function getRealTimestamps(RegPeriksa $reg, ?string $kodebooking = null, array $batalMap = []): array
    {
        $timestamps = [
            1  => null,
            2  => null,
            3  => null,
            4  => null,
            5  => null,
            6  => null,
            7  => null,
            99 => null,
        ];

        // Task 1 & 2: Registration
        $timestamps[1] = ($reg->tgl_registrasi && (string) $reg->jam_reg !== '00:00:00')
            ? $this->parseTimestamp($reg->tgl_registrasi, $reg->jam_reg)
            : null;
        $timestamps[2] = $timestamps[1];

        // Task 3: Check-in / Waktu Validasi Mobile JKN (jika ada data), jika tidak ambil dari RegPeriksa (tgl_registrasi + jam_reg)
        $refMjkn = $reg->relationLoaded('referensiMobilejknBpjs') ? $reg->referensiMobilejknBpjs : null;
        if (! $refMjkn && $kodebooking) {
            if (! $reg->relationLoaded('referensiMobilejknBpjs')) {
                $refMjkn = \App\Models\ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
            }
        }
        if (! $refMjkn && ! $reg->relationLoaded('referensiMobilejknBpjs')) {
            $refMjkn = $reg->referensiMobilejknBpjs;
        }

        if ($refMjkn) {
            // Pasien MJKN: Jika validasi ada, parse datanya. Jika kosong/0000, kembalikan '00.00.00'
            $timestamps[3] = '00.00.00';
            if ($refMjkn->validasi) {
                $validasiStr = (string) $refMjkn->validasi;
                if ($validasiStr !== '0000-00-00 00:00:00' && $validasiStr !== '' && strpos($validasiStr, '-0001') === false && strpos($validasiStr, '0000-') === false) {
                    $parsedV = Carbon::parse($validasiStr, 'Asia/Jakarta');
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
        // Guarded by relationLoaded to eliminate N+1 queries during bulk operations
        if ($reg->relationLoaded('pemeriksaanRalan') && $reg->pemeriksaanRalan && $reg->pemeriksaanRalan->isNotEmpty()) {
            $nipsInPemeriksaan = $reg->pemeriksaanRalan->pluck('nip')->filter()->unique()->toArray();
            $petugasNips       = ! empty($nipsInPemeriksaan) ? \App\Models\Petugas::whereIn('nip', $nipsInPemeriksaan)->pluck('nip')->toArray() : [];

            $pemeriksaanPetugas = $reg->pemeriksaanRalan
                ->filter(function ($p) use ($petugasNips) {
                    if (empty($p->nip) || (string) $p->jam_rawat === '00:00:00') {
                        return false;
                    }
                    return $p->relationLoaded('petugas') ? $p->petugas !== null : in_array($p->nip, $petugasNips);
                })
                ->sortBy('jam_rawat')
                ->first();

            if ($pemeriksaanPetugas && $pemeriksaanPetugas->jam_rawat) {
                $timestamps[4] = $this->parseTimestamp($pemeriksaanPetugas->tgl_perawatan, $pemeriksaanPetugas->jam_rawat);
            }
        }

        // Task 5: Pemeriksaan Ralan - Cek NIP Dokter
        if ($reg->relationLoaded('pemeriksaanRalan') && $reg->pemeriksaanRalan && $reg->pemeriksaanRalan->isNotEmpty()) {
            $nipsInPemeriksaan = $reg->pemeriksaanRalan->pluck('nip')->filter()->unique()->toArray();
            $dokterNips        = ! empty($nipsInPemeriksaan) ? \App\Models\Dokter::whereIn('kd_dokter', $nipsInPemeriksaan)->pluck('kd_dokter')->toArray() : [];

            $pemeriksaanDokter = $reg->pemeriksaanRalan
                ->filter(function ($p) use ($dokterNips) {
                    if (empty($p->nip) || (string) $p->jam_rawat === '00:00:00') {
                        return false;
                    }
                    return $p->relationLoaded('dokter') ? $p->dokter !== null : in_array($p->nip, $dokterNips);
                })
                ->sortByDesc('jam_rawat')
                ->first();

            if ($pemeriksaanDokter && $pemeriksaanDokter->jam_rawat) {
                $timestamps[5] = $this->parseTimestamp($pemeriksaanDokter->tgl_perawatan, $pemeriksaanDokter->jam_rawat);
            }
        }

        // Task 6: Resep Obat - Jam Peresapan / Pembuatan Resep (ResepObat.jam)
        if ($reg->relationLoaded('resepObat') && $reg->resepObat && $reg->resepObat->isNotEmpty()) {
            $resep6 = $reg->resepObat
                ->filter(fn($r) => ! empty($r->jam) && (string) $r->jam !== '00:00:00')
                ->sortByDesc('jam')
                ->first();
            if ($resep6 && $resep6->jam) {
                $timestamps[6] = $this->parseTimestamp($resep6->tgl_perawatan ?: $resep6->tgl_peresapan ?: $reg->tgl_registrasi, $resep6->jam);
            }
        }

        // Task 7: Resep Obat - Jam Penyerahan Obat (ResepObat.jam_penyerahan)
        if ($reg->relationLoaded('resepObat') && $reg->resepObat && $reg->resepObat->isNotEmpty()) {
            $resep7 = $reg->resepObat
                ->filter(fn($r) => ! empty($r->jam_penyerahan) && (string) $r->jam_penyerahan !== '00:00:00')
                ->sortByDesc('jam_penyerahan')
                ->first();
            if ($resep7 && $resep7->jam_penyerahan) {
                $timestamps[7] = $this->parseTimestamp($resep7->tgl_penyerahan ?: $resep7->tgl_perawatan ?: $reg->tgl_registrasi, $resep7->jam_penyerahan);
            }
        }

        // Task 99: Pembatalan Antrean (prioritaskan preloaded batalMap)
        $batal = null;
        if (! empty($batalMap)) {
            $batal = ($kodebooking && isset($batalMap[$kodebooking]))
                ? $batalMap[$kodebooking]
                : ($batalMap[$reg->no_rawat] ?? null);
        } else {
            if ($kodebooking) {
                $batal = \App\Models\ReferensiMobilejknBpjsBatal::where('nobooking', $kodebooking)->first();
            }
            if (! $batal && (! $kodebooking || ($refMjkn && $refMjkn->status === 'Batal'))) {
                $batal = \App\Models\ReferensiMobilejknBpjsBatal::where('no_rawat_batal', $reg->no_rawat)->first();
            }
        }
        if ($batal && $batal->tanggalbatal) {
            $timestamps[99] = Carbon::parse($batal->tanggalbatal, 'Asia/Jakarta');
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
        return [
            'checkin_to_nurse'   => $this->diffMinutes($timestamps[3] ?? null, $timestamps[4] ?? null),
            'nurse_to_doctor'    => $this->diffMinutes($timestamps[4] ?? null, $timestamps[5] ?? null),
            'doctor_to_pharmacy' => $this->diffMinutes($timestamps[5] ?? null, $timestamps[6] ?? null),
            'pharmacy_to_done'   => $this->diffMinutes($timestamps[6] ?? null, $timestamps[7] ?? null),
            'total_time'         => $this->diffMinutes($timestamps[3] ?? null, $timestamps[7] ?? $timestamps[5] ?? null),
        ];
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
            if (! empty($p['anomalies'])) {
                $counts['total_anomalies']++;
                foreach ($p['anomalies'] as $type) {
                    if (isset($counts[$type])) {
                        $counts[$type][] = 1;
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
            $refThisBooking = \App\Models\ReferensiMobilejknBpjs::where('no_rawat', $noRawat)
                ->orderByRaw("CASE WHEN status = 'Batal' THEN 1 ELSE 0 END ASC")
                ->orderBy('tanggalperiksa', 'desc')
                ->first();
        }

        $kodebooking = $refThisBooking?->nobooking ?? $bpjsVisit?->kodebooking ?? $identifier;

        $batalInfo = null;
        if ($refThisBooking?->status === 'Batal' || ($refThisBooking === null && strtolower(trim($reg?->stts ?? '')) === 'batal')) {
            $batalRecord = \App\Models\ReferensiMobilejknBpjsBatal::where('nobooking', $kodebooking)->first();
            if (! $batalRecord && $noRawat && (! $refThisBooking || $refThisBooking->status === 'Batal')) {
                $batalRecord = \App\Models\ReferensiMobilejknBpjsBatal::where('no_rawat_batal', $noRawat)->first();
            }
            if ($batalRecord) {
                $batalInfo = $batalRecord;
            }
        }

        // Only hit BPJS API if the visit is for today and hasn't been synced in last 15 minutes
        // For past visits, use existing local data to avoid unnecessary network latency and rate limits
        $isToday = ($bpjsVisit && $bpjsVisit->tanggalperiksa instanceof Carbon)
            ? $bpjsVisit->tanggalperiksa->isToday()
            : ($bpjsVisit?->tanggalperiksa === now()->toDateString());

        if ($kodebooking && $isToday && (! $bpjsVisit || ! $bpjsVisit->last_sync || $bpjsVisit->last_sync->lt(now()->subMinutes(15)))) {
            $mobileJknService->getListTask($kodebooking);
            $bpjsVisit = BpjsPatientVisit::where('kodebooking', $kodebooking)->first() ?? BpjsPatientVisit::where('no_rawat', $noRawat)->first();
        }

        $realTimestamps = $reg ? $this->getRealTimestamps($reg, $kodebooking) : [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null, 99 => null];

        if ($bpjsVisit) {
            $bpjsTimestamps = $this->getBpjsTimestamps($bpjsVisit);
        } else {
            $bpjsTimestamps = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null, 99 => null];
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
            $status = ($refThisBooking?->status === 'Batal' || ($refThisBooking === null && strtolower(trim($reg?->stts ?? '')) === 'batal')) ? 'Batal' : 'Belum Terkirim';
        }

        if ($refThisBooking?->status === 'Batal' || ($refThisBooking === null && strtolower(trim($reg?->stts ?? '')) === 'batal')) {
            $status = 'Batal';
        }
        $anomalies  = $this->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);
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
        if (! $hasBooking && strpos($kodebooking, '/') === false) {
            $hasBooking = $reg && $reg->referensiMobilejknBpjsAll && $reg->referensiMobilejknBpjsAll->isNotEmpty();
        }
        $sumber = $hasBooking ? 'Mobile JKN' : 'Onsite';

        return [
            'sumber'          => $sumber,
            'no_rawat'        => $reg?->no_rawat ?? $bpjsVisit?->no_rawat,
            'no_rkm_medis'    => $reg?->no_rkm_medis ?? $bpjsVisit?->norm,
            'nm_pasien'       => $reg?->pasien->nm_pasien ?? 'N/A',
            'no_ktp'          => $reg?->pasien->no_ktp ?? $bpjsVisit?->nik ?? null,
            'no_kartu_bpjs'   => $noKartuBpjs,
            'tgl_lahir'       => $reg?->pasien?->tgl_lahir ? Carbon::parse($reg->pasien->tgl_lahir)->format('d M Y') : null,
            'jk'              => $reg?->pasien->jk ?? null,
            'nm_poli'         => $reg?->poliklinik->nm_poli ?? $bpjsVisit?->namapoli ?? 'N/A',
            'nm_dokter'       => $docName,
            'tgl_registrasi'  => ($bpjsVisit && $bpjsVisit->tanggalperiksa instanceof Carbon) ? $bpjsVisit->tanggalperiksa->format('d M Y') : ($reg?->tgl_registrasi instanceof Carbon ? $reg->tgl_registrasi->format('d M Y') : (string) $reg?->tgl_registrasi),
            'jam_reg'         => $reg?->jam_reg ? ($reg->jam_reg instanceof \DateTimeInterface  ? $reg->jam_reg->format('H:i') : substr((string) $reg->jam_reg, 0, 5)) : null,
            'stts'            => $reg?->stts ?? '',
            'has_booking'     => (bool) ($reg?->referensiMobilejknBpjs ?? $bpjsVisit),
            'kode_booking'    => $kodebooking,
            'timestamps_real' => $this->formatTimestampMap($realTimestamps),
            'timestamps_sent' => $this->formatTimestampMap($bpjsTimestamps),
            'durations'       => $durations,
            'status'          => $status,
            'anomalies'       => $anomalies,
            'anomaly_hints'   => $anomalyHints,
            'comparison'      => $comparison,
            'is_bpjs_source'  => (bool) $bpjsVisit,
            'batal_info'      => $batalInfo ? [
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

    /**
     * Get lightweight analytics summary (counts and aggregates only)
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array<string, mixed>
     */
    public function getAnalyticsSummary(string $dateFrom, string $dateTo): array
    {
        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoliArray = array_filter(explode(',', config('mobilejkn.exclude_poli', 'HD,IGD,IGDK')));

        // 1. Count registrations from SIMRS
        $simrsQuery = RegPeriksa::whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
            ->where('kd_pj', $kdPj);
        if (! empty($excludePoliArray)) {
            $simrsQuery->whereNotIn('kd_poli', $excludePoliArray);
        }
        $totalRegistrations = $simrsQuery->count();

        // 2. Aggregate from cached visits (fast — uses indexes)
        $visitsQuery = BpjsPatientVisit::whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);
        if (! empty($excludePoliArray)) {
            $visitsQuery->whereNotIn('kodepoli', $excludePoliArray);
        }

        $visitStats = (clone $visitsQuery)->selectRaw("
            COUNT(*) as total,
            SUM(CASE WHEN status = 'Batal' THEN 1 ELSE 0 END) as batal,
            SUM(CASE WHEN task_data IS NOT NULL AND JSON_LENGTH(task_data) > 0 THEN 1 ELSE 0 END) as synced,
            SUM(CASE WHEN task_data IS NULL OR JSON_LENGTH(task_data) = 0 THEN 1 ELSE 0 END) as pending_sync
        ")->first();

        // 3. Dates with data
        $datesWithData = (clone $visitsQuery)->groupBy('tanggalperiksa')
            ->pluck('tanggalperiksa')->toArray();

        return [
            'date_from'           => $dateFrom,
            'date_to'             => $dateTo,
            'total_registrations' => $totalRegistrations,
            'total_visits'        => (int) ($visitStats->total ?? 0),
            'batal_count'         => (int) ($visitStats->batal ?? 0),
            'synced_count'        => (int) ($visitStats->synced ?? 0),
            'pending_sync'        => (int) ($visitStats->pending_sync ?? 0),
            'dates_with_data'     => $datesWithData,
        ];
    }

    /**
     * Get paginated patient flows with optional filters
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @param int $page
     * @param int $perPage
     * @param string|null $search
     * @param string|null $clinic
     * @param string|null $status
     * @return array<string, mixed>
     */
    public function getPatientsPaginated(
        string $dateFrom,
        string $dateTo,
        int $page = 1,
        int $perPage = 25,
        ?string $search = null,
        ?string $clinic = null,
        ?string $status = null
    ): array {
        $excludePoliArray = array_filter(explode(',', config('mobilejkn.exclude_poli', 'HD,IGD,IGDK')));

        $query = BpjsPatientVisit::with([
            'regPeriksa.pasien',
            'regPeriksa.referensiMobilejknBpjs',
        ])->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

        if (! empty($excludePoliArray)) {
            $query->whereNotIn('kodepoli', $excludePoliArray);
        }

        if ($clinic) {
            $query->where(function ($q) use ($clinic) {
                $q->where('kodepoli', $clinic)
                  ->orWhere('namapoli', $clinic);
            });
        }

        if ($status) {
            if ($status === 'Belum Terkirim' || $status === 'anomali:belum_terkirim') {
                $query->where(function ($q) {
                    $q->whereNull('task_data')->orWhere('task_data', '[]')->orWhere('task_data', '');
                })->where(function ($q) {
                    $q->where('status', '!=', 'Batal')->orWhereNull('status');
                });
            } elseif ($status === 'Batal') {
                $query->where(function ($q) {
                    $q->where('status', 'Batal')
                      ->orWhere('task_data', 'like', '%"taskid":99%')
                      ->orWhere('task_data', 'like', '%"taskid": 99%');
                });
            } elseif ($status === 'Lengkap (3,4,5,6,7)') {
                $query->where(function ($q) {
                    $q->where('task_data', 'like', '%"taskid":7%')
                      ->orWhere('task_data', 'like', '%"taskid": 7%');
                });
            } elseif ($status === 'Lengkap (3,4,5,6) - Farmasi Belum Selesai') {
                $query->where(function ($q) {
                    $q->where('task_data', 'like', '%"taskid":6%')
                      ->orWhere('task_data', 'like', '%"taskid": 6%');
                })->where(function ($q) {
                    $q->where('task_data', 'not like', '%"taskid":7%')
                      ->where('task_data', 'not like', '%"taskid": 7%');
                });
            } elseif ($status === 'Task 3,4,5') {
                $query->where(function ($q) {
                    $q->where('task_data', 'like', '%"taskid":5%')
                      ->orWhere('task_data', 'like', '%"taskid": 5%');
                })->where(function ($q) {
                    $q->where('task_data', 'not like', '%"taskid":6%')
                      ->where('task_data', 'not like', '%"taskid": 6%');
                });
            } elseif ($status === 'Task 3,4') {
                $query->where(function ($q) {
                    $q->where('task_data', 'like', '%"taskid":4%')
                      ->orWhere('task_data', 'like', '%"taskid": 4%');
                })->where(function ($q) {
                    $q->where('task_data', 'not like', '%"taskid":5%')
                      ->where('task_data', 'not like', '%"taskid": 5%');
                });
            } elseif ($status === 'Task 3') {
                $query->where(function ($q) {
                    $q->where('task_data', 'like', '%"taskid":3%')
                      ->orWhere('task_data', 'like', '%"taskid": 3%');
                })->where(function ($q) {
                    $q->where('task_data', 'not like', '%"taskid":4%')
                      ->where('task_data', 'not like', '%"taskid": 4%');
                });
            } elseif ($status === 'anomali') {
                $query->where(function ($q) {
                    $q->whereNull('task_data')->orWhere('task_data', '[]')->orWhere('task_data', '');
                });
            } else {
                $query->where('status', $status);
            }
        }

        if ($search) {
            $s = trim($search);
            $query->where(function ($q) use ($s) {
                $q->where('kodebooking', 'like', "%{$s}%")
                  ->orWhere('no_rawat', 'like', "%{$s}%")
                  ->orWhere('norm', 'like', "%{$s}%")
                  ->orWhere('namapoli', 'like', "%{$s}%")
                  ->orWhere('namadokter', 'like', "%{$s}%")
                  ->orWhereHas('regPeriksa.pasien', function ($pq) use ($s) {
                      $pq->where('nm_pasien', 'like', "%{$s}%");
                  });
            });
        }

        $total = (clone $query)->count();
        $visits = $query->orderBy('tanggalperiksa')
            ->orderBy('id')
            ->forPage($page, $perPage)
            ->get();

        $doctorMappings = MapingDokterDpjpvclaim::with('dokter')->get()->keyBy('kd_dokter_bpjs');
        $patientFlows = $this->buildPatientFlows($visits, $doctorMappings);

        return [
            'data'         => $patientFlows,
            'total'        => $total,
            'current_page' => $page,
            'per_page'     => $perPage,
            'last_page'    => (int) ceil($total / max(1, $perPage)),
        ];
    }

    /**
     * Send Task IDs manually for a single patient (either Task 99 if cancelled or Tasks 3-7 if active)
     */
    public function sendPatientTasksManually(string $kodebooking, string $noRawat): array
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
                return [
                    'success' => false,
                    'message' => "Data pasien dengan No. Rawat {$noRawat} tidak ditemukan",
                ];
            }

            $mobileJknService = app(MobileJknService::class);
            $ref = $reg->referensiMobilejknBpjs;

            // Check if patient is marked as Batal
            $isBatalInSimrs = (strtolower(trim($reg->stts ?? '')) === 'batal');
            $isRefBatal     = ($ref && strtolower(trim($ref->status ?? '')) === 'batal');
            $isBatalTabel   = ReferensiMobilejknBpjsBatal::where('nobooking', $kodebooking)
                ->orWhere('no_rawat_batal', $noRawat)
                ->exists();

            $isCancelled = ($isBatalInSimrs || $isRefBatal || $isBatalTabel);

            if ($isCancelled) {
                $nowStr = (string) (now()->timestamp * 1000);
                $result = $mobileJknService->updateTaskId($kodebooking, 99, $nowStr);

                $metaMsg = $result['data']['metadata']['message'] ?? ($result['metadata']['message'] ?? ($result['message'] ?? ''));
                $isAcceptable = is_string($metaMsg) && (
                    strpos($metaMsg, 'Ok') !== false ||
                    strpos($metaMsg, 'sudah ada') !== false ||
                    strpos(strtolower($metaMsg), 'batal') !== false ||
                    strpos($metaMsg, 'tidak ditemukan') !== false
                );

                $success = ($result['success'] ?? false) || $isAcceptable;

                if ($reg->stts !== 'Batal') {
                    $reg->update(['stts' => 'Batal']);
                }

                if ($ref) {
                    $validasiVal = ($ref->validasi && strpos((string) $ref->validasi, '0000-') === false && (string) $ref->validasi !== '-0001-11-30 00:00:00')
                        ? $ref->validasi
                        : now();
                    $ref->update([
                        'status'      => 'Batal',
                        'statuskirim' => 'Sudah',
                        'validasi'    => $validasiVal,
                    ]);
                }

                ReferensiMobilejknBpjsBatal::where('nobooking', $kodebooking)
                    ->orWhere('no_rawat_batal', $noRawat)
                    ->update(['statuskirim' => 'Sudah']);

                // Resync local cache from BPJS
                $syncRes = $this->syncSinglePatient($kodebooking, $noRawat);

                return [
                    'success'    => $success,
                    'is_batal'   => true,
                    'message'    => $success ? "Task 99 (Batal) berhasil dikirim untuk {$kodebooking}" : ($result['error'] ?? $metaMsg ?? 'Gagal mengirim Task 99'),
                    'tasks_sent' => [99],
                    'patient'    => $syncRes['patient'] ?? null,
                ];
            }

            // Normal / active patient -> Evaluate existing tasks from BPJS
            $listTaskResult = $mobileJknService->getListTask($kodebooking);
            $existingTaskIds = [];

            if (! empty($listTaskResult['data']) && is_array($listTaskResult['data'])) {
                foreach ($listTaskResult['data'] as $item) {
                    if (isset($item['taskid'])) {
                        $existingTaskIds[] = (int) $item['taskid'];
                    }
                }
            }

            $tasksSent = [];
            $errors = [];

            // ── TASK 3 ──
            if (! in_array(3, $existingTaskIds)) {
                $ts3 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 3);
                if ($ts3 === null || (int) $ts3 < 1609459200000) {
                    $ts4 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 4);
                    if ($ts4 && (int) $ts4 >= 1609459200000) {
                        $offset = random_int(5, 10);
                        $ts3    = (string) ((int) $ts4 - ($offset * 60 * 1000));
                    } elseif ($reg->tgl_registrasi && $reg->jam_reg) {
                        $ts3 = (string) (Carbon::parse($reg->tgl_registrasi . ' ' . $reg->jam_reg)->timestamp * 1000);
                    }
                }

                if ($ts3 && (int) $ts3 >= 1609459200000) {
                    $res3 = $mobileJknService->updateTaskId($kodebooking, 3, $ts3);
                    $metaMsg3 = $res3['data']['metadata']['message'] ?? ($res3['metadata']['message'] ?? '');
                    if (($res3['success'] ?? false) || (is_string($metaMsg3) && strpos($metaMsg3, 'sudah ada') !== false)) {
                        $tasksSent[] = 3;
                    } else {
                        $errors[] = "Task 3: " . ($res3['error'] ?? $metaMsg3 ?? 'Gagal');
                    }
                } else {
                    $errors[] = "Task 3: Tidak menemukan timestamp registrasi yang valid";
                }
            }

            // ── TASK 4 ──
            if (! in_array(4, $existingTaskIds)) {
                $ts4 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 4);
                if ($ts4 && (int) $ts4 >= 1609459200000) {
                    $res4 = $mobileJknService->updateTaskId($kodebooking, 4, $ts4);
                    $metaMsg4 = $res4['data']['metadata']['message'] ?? ($res4['metadata']['message'] ?? '');
                    if (($res4['success'] ?? false) || (is_string($metaMsg4) && strpos($metaMsg4, 'sudah ada') !== false)) {
                        $tasksSent[] = 4;
                    } else {
                        $errors[] = "Task 4: " . ($res4['error'] ?? $metaMsg4 ?? 'Gagal');
                    }
                } else {
                    $errors[] = "Task 4: Belum ada data pemeriksaan dokter di SIMRS";
                }
            }

            // ── TASK 5 ──
            if (! in_array(5, $existingTaskIds)) {
                $ts5 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 5);
                if ($ts5 === null || (int) $ts5 < 1609459200000) {
                    $ts4Val = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 4);
                    if ($ts4Val && (int) $ts4Val >= 1609459200000) {
                        $offset = random_int(5, 10);
                        $ts5    = (string) ((int) $ts4Val + ($offset * 60 * 1000));
                    }
                }

                if ($ts5 && (int) $ts5 >= 1609459200000) {
                    $res5 = $mobileJknService->updateTaskId($kodebooking, 5, $ts5);
                    $metaMsg5 = $res5['data']['metadata']['message'] ?? ($res5['metadata']['message'] ?? '');
                    if (($res5['success'] ?? false) || (is_string($metaMsg5) && strpos($metaMsg5, 'sudah ada') !== false)) {
                        $tasksSent[] = 5;
                    } else {
                        $errors[] = "Task 5: " . ($res5['error'] ?? $metaMsg5 ?? 'Gagal');
                    }
                }
            }

            // ── TASK 6 ──
            $ts6 = null;
            if (! in_array(6, $existingTaskIds)) {
                $ts6 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 6);
                if ($ts6 && (int) $ts6 >= 1609459200000) {
                    $res6 = $mobileJknService->updateTaskId($kodebooking, 6, $ts6);
                    $metaMsg6 = $res6['data']['metadata']['message'] ?? ($res6['metadata']['message'] ?? '');
                    if (($res6['success'] ?? false) || (is_string($metaMsg6) && strpos($metaMsg6, 'sudah ada') !== false)) {
                        $tasksSent[] = 6;
                    } else {
                        $errors[] = "Task 6: " . ($res6['error'] ?? $metaMsg6 ?? 'Gagal');
                    }
                }
            } else {
                $ts6 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 6);
            }

            // ── TASK 7 ──
            if (! in_array(7, $existingTaskIds)) {
                $ts7 = $mobileJknService->getTaskTimestampFromDatabase($kodebooking, 7);
                if ($ts7 === null || (int) $ts7 < 1609459200000) {
                    if ($ts6 && (int) $ts6 >= 1609459200000) {
                        $offset = random_int(5, 10);
                        $ts7    = (string) ((int) $ts6 + ($offset * 60 * 1000));
                    }
                }

                if ($ts7 && (int) $ts7 >= 1609459200000) {
                    $res7 = $mobileJknService->updateTaskId($kodebooking, 7, $ts7);
                    $metaMsg7 = $res7['data']['metadata']['message'] ?? ($res7['metadata']['message'] ?? '');
                    if (($res7['success'] ?? false) || (is_string($metaMsg7) && strpos($metaMsg7, 'sudah ada') !== false)) {
                        $tasksSent[] = 7;
                    } else {
                        $errors[] = "Task 7: " . ($res7['error'] ?? $metaMsg7 ?? 'Gagal');
                    }
                }
            }

            // Refresh cache
            $syncRes = $this->syncSinglePatient($kodebooking, $noRawat);

            $msg = ! empty($tasksSent)
                ? "Berhasil mengirim Task ID: " . implode(', ', $tasksSent)
                : (empty($errors) ? "Semua Task ID sudah sesuai dan tercatat di BPJS" : implode('; ', $errors));

            return [
                'success'    => ! empty($tasksSent) || empty($errors),
                'is_batal'   => false,
                'message'    => $msg,
                'tasks_sent' => $tasksSent,
                'errors'     => $errors,
                'patient'    => $syncRes['patient'] ?? null,
            ];
        } catch (\Throwable $e) {
            return [
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Get cancelled patients for a given date with their Task 99 status
     */
    public function getCancelledPatientsByDate(string $tanggalFrom, ?string $tanggalTo = null): array
    {
        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $isRange = ! empty($tanggalTo) && $tanggalTo !== $tanggalFrom;
        $endDate = $isRange ? $tanggalTo : $tanggalFrom;

        // Query RegPeriksa cancelled patients
        $query = RegPeriksa::with([
            'referensiMobilejknBpjs',
            'pasien',
            'poliklinik',
            'dokter',
        ])
        ->where('kd_pj', $kdPj)
        ->where(function ($q) {
            $q->where('stts', 'Batal')
              ->orWhereHas('referensiMobilejknBpjs', function ($refQ) {
                  $refQ->where('status', 'Batal');
              });
        });

        if ($isRange) {
            $query->whereBetween('tgl_registrasi', [$tanggalFrom, $endDate]);
        } else {
            $query->where('tgl_registrasi', $tanggalFrom);
        }

        $regs = $query->orderBy('tgl_registrasi')->get();

        // Also check ReferensiMobilejknBpjsBatal
        $batalQuery = ReferensiMobilejknBpjsBatal::query();
        if ($isRange) {
            $batalQuery->whereBetween(\Illuminate\Support\Facades\DB::raw('DATE(tanggalbatal)'), [$tanggalFrom, $endDate]);
        } else {
            $batalQuery->whereDate('tanggalbatal', $tanggalFrom);
        }
        $batalTable = $batalQuery->get();
        $batalRawats = $batalTable->pluck('no_rawat_batal')->filter()->toArray();

        if (! empty($batalRawats)) {
            $additionalRegs = RegPeriksa::with([
                'referensiMobilejknBpjs',
                'pasien',
                'poliklinik',
                'dokter',
            ])
            ->whereIn('no_rawat', $batalRawats)
            ->get();

            $regs = $regs->merge($additionalRegs)->unique('no_rawat');
        }

        // Get visits cache for Task 99 check
        $kodeBookings = $regs->map(function ($r) {
            return $r->referensiMobilejknBpjs?->nobooking ?? $r->no_rawat;
        })->filter()->toArray();

        $visits = BpjsPatientVisit::whereIn('kodebooking', $kodeBookings)->get()->keyBy('kodebooking');

        $resultList = [];
        $sudahKirimCount = 0;
        $belumKirimCount = 0;

        foreach ($regs as $r) {
            $kb = $r->referensiMobilejknBpjs?->nobooking ?? $r->no_rawat;
            $visit = $visits->get($kb);
            $hasTask99 = false;

            if ($visit && ! empty($visit->task_data) && is_array($visit->task_data)) {
                foreach ($visit->task_data as $td) {
                    if (isset($td['taskid']) && (int) $td['taskid'] === 99) {
                        $hasTask99 = true;
                        break;
                    }
                }
            }

            $isSudahKirim = $hasTask99 || ($r->referensiMobilejknBpjs?->statuskirim === 'Sudah');

            if ($isSudahKirim) {
                $sudahKirimCount++;
            } else {
                $belumKirimCount++;
            }

            $resultList[] = [
                'no_rawat'        => $r->no_rawat,
                'kodebooking'     => $kb,
                'no_rkm_medis'    => $r->no_rkm_medis,
                'nm_pasien'       => $r->pasien?->nm_pasien ?? '-',
                'nm_poli'         => $r->poliklinik?->nm_poli ?? '-',
                'nm_dokter'       => $r->dokter?->nm_dokter ?? '-',
                'stts'            => $r->stts,
                'tgl_registrasi'  => $r->tgl_registrasi ? Carbon::parse($r->tgl_registrasi)->format('Y-m-d') : '-',
                'status_kirim_99' => $isSudahKirim ? 'Sudah' : 'Belum',
                'is_sent_99'      => $isSudahKirim,
            ];
        }

        $labelTanggal = $isRange ? "{$tanggalFrom} s/d {$endDate}" : $tanggalFrom;

        return [
            'tanggal'     => $labelTanggal,
            'date_from'   => $tanggalFrom,
            'date_to'     => $endDate,
            'total'       => count($resultList),
            'sudah_kirim' => $sudahKirimCount,
            'belum_kirim' => $belumKirimCount,
            'data'        => $resultList,
        ];
    }

    /**
     * Send Task 99 in batch for all cancelled patients in a given date or date range
     */
    public function sendCancelledPatientsByDate(string $tanggalFrom, ?string $tanggalTo = null, bool $force = false): array
    {
        $preview = $this->getCancelledPatientsByDate($tanggalFrom, $tanggalTo);
        $candidates = $preview['data'] ?? [];

        $targets = array_filter($candidates, function ($item) use ($force) {
            return $force || ! $item['is_sent_99'];
        });

        $mobileJknService = app(MobileJknService::class);
        $successCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($targets as $target) {
            $kb = $target['kodebooking'];
            $noRawat = $target['no_rawat'];
            $nowStr = (string) (now()->timestamp * 1000);

            try {
                $res = $mobileJknService->updateTaskId($kb, 99, $nowStr);
                $metaMsg = $res['data']['metadata']['message'] ?? ($res['metadata']['message'] ?? ($res['message'] ?? ''));

                $isAcceptable = is_string($metaMsg) && (
                    strpos($metaMsg, 'Ok') !== false ||
                    strpos($metaMsg, 'sudah ada') !== false ||
                    strpos(strtolower($metaMsg), 'batal') !== false ||
                    strpos($metaMsg, 'tidak ditemukan') !== false
                );

                $isSuccess = ($res['success'] ?? false) || $isAcceptable;

                if ($isSuccess) {
                    $successCount++;

                    // Update DB
                    RegPeriksa::where('no_rawat', $noRawat)->where('stts', '!=', 'Batal')->update(['stts' => 'Batal']);

                    ReferensiMobilejknBpjs::where('nobooking', $kb)
                        ->orWhere('no_rawat', $noRawat)
                        ->update([
                            'status'      => 'Batal',
                            'statuskirim' => 'Sudah',
                        ]);

                    ReferensiMobilejknBpjsBatal::where('nobooking', $kb)
                        ->orWhere('no_rawat_batal', $noRawat)
                        ->update(['statuskirim' => 'Sudah']);

                    // Sync cache
                    try {
                        $mobileJknService->getListTask($kb);
                    } catch (\Throwable $e) {}
                } else {
                    $failedCount++;
                }

                $results[] = [
                    'kodebooking' => $kb,
                    'no_rawat'    => $noRawat,
                    'nm_pasien'   => $target['nm_pasien'],
                    'success'     => $isSuccess,
                    'message'     => $isSuccess ? 'Task 99 berhasil' : ($res['error'] ?? $metaMsg ?? 'Gagal kirim'),
                ];

                usleep(100000); // 100ms safe delay between BPJS hits
            } catch (\Throwable $e) {
                $failedCount++;
                $results[] = [
                    'kodebooking' => $kb,
                    'no_rawat'    => $noRawat,
                    'nm_pasien'   => $target['nm_pasien'],
                    'success'     => false,
                    'message'     => $e->getMessage(),
                ];
            }
        }

        return [
            'success'         => true,
            'tanggal'         => $preview['tanggal'],
            'date_from'       => $tanggalFrom,
            'date_to'         => $tanggalTo ?: $tanggalFrom,
            'total_processed' => count($targets),
            'success_count'   => $successCount,
            'failed_count'    => $failedCount,
            'details'         => $results,
        ];
    }

    /**
     * Get normal (non-cancelled) patients for a date range with their task completeness
     */
    public function getNormalPatientsForBatch(string $dateFrom, ?string $dateTo = null): array
    {
        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        $isRange = ! empty($dateTo) && $dateTo !== $dateFrom;
        $endDate = $isRange ? $dateTo : $dateFrom;

        $query = RegPeriksa::with([
            'referensiMobilejknBpjs',
            'pasien',
            'poliklinik',
            'dokter',
            'resepObat',
        ])
        ->where('kd_pj', $kdPj)
        ->where('stts', '!=', 'Batal')
        ->whereDoesntHave('referensiMobilejknBpjs', function ($q) {
            $q->where('status', 'Batal');
        });

        if (! empty($excludePoliArray)) {
            $query->whereNotIn('kd_poli', $excludePoliArray);
        }

        if ($isRange) {
            $query->whereBetween('tgl_registrasi', [$dateFrom, $endDate]);
        } else {
            $query->where('tgl_registrasi', $dateFrom);
        }

        $regs = $query->orderBy('tgl_registrasi')->orderBy('jam_reg')->get();

        $kodeBookings = $regs->map(function ($r) {
            return $r->referensiMobilejknBpjs?->nobooking ?? $r->no_rawat;
        })->filter()->toArray();

        $visits = BpjsPatientVisit::whereIn('kodebooking', $kodeBookings)->get()->keyBy('kodebooking');

        $resultList = [];
        $sudahLengkapCount = 0;
        $belumLengkapCount = 0;

        foreach ($regs as $r) {
            $kb = $r->referensiMobilejknBpjs?->nobooking ?? $r->no_rawat;
            $visit = $visits->get($kb);
            $existingTaskIds = [];

            if ($visit && ! empty($visit->task_data) && is_array($visit->task_data)) {
                foreach ($visit->task_data as $td) {
                    if (isset($td['taskid'])) {
                        $existingTaskIds[] = (int) $td['taskid'];
                    }
                }
            }

            $hasPrescription = ($r->resepObat !== null && $r->resepObat->isNotEmpty());
            $isComplete = false;
            $statusTask = 'Belum Terkirim';

            if (! empty($existingTaskIds)) {
                if (in_array(3, $existingTaskIds) && in_array(4, $existingTaskIds) && in_array(5, $existingTaskIds)) {
                    if ($hasPrescription) {
                        if (in_array(6, $existingTaskIds) && in_array(7, $existingTaskIds)) {
                            $isComplete = true;
                            $statusTask = 'Lengkap (3-7)';
                        } else {
                            $statusTask = 'Lengkap (3-5), Farmasi Belum';
                        }
                    } else {
                        $isComplete = true;
                        $statusTask = 'Lengkap (3-5, Non-Resep)';
                    }
                } else {
                    $statusTask = 'Task ' . implode(',', $existingTaskIds);
                }
            }

            if ($isComplete) {
                $sudahLengkapCount++;
            } else {
                $belumLengkapCount++;
            }

            $resultList[] = [
                'no_rawat'        => $r->no_rawat,
                'kodebooking'     => $kb,
                'no_rkm_medis'    => $r->no_rkm_medis,
                'nm_pasien'       => $r->pasien?->nm_pasien ?? '-',
                'nm_poli'         => $r->poliklinik?->nm_poli ?? '-',
                'nm_dokter'       => $r->dokter?->nm_dokter ?? '-',
                'stts'            => $r->stts,
                'tgl_registrasi'  => $r->tgl_registrasi ? Carbon::parse($r->tgl_registrasi)->format('Y-m-d') : '-',
                'status_task'     => $statusTask,
                'is_complete'     => $isComplete,
                'has_resep'       => $hasPrescription,
                'existing_tasks'  => $existingTaskIds,
            ];
        }

        $labelTanggal = $isRange ? "{$dateFrom} s/d {$endDate}" : $dateFrom;

        return [
            'tanggal'        => $labelTanggal,
            'date_from'      => $dateFrom,
            'date_to'        => $endDate,
            'total'          => count($resultList),
            'sudah_lengkap'  => $sudahLengkapCount,
            'belum_lengkap'  => $belumLengkapCount,
            'data'           => $resultList,
        ];
    }

    /**
     * Send Task IDs in batch for a given list of normal patients
     */
    public function sendNormalPatientsBatchChunk(array $patients): array
    {
        $successCount = 0;
        $failedCount = 0;
        $results = [];

        foreach ($patients as $item) {
            $kb = $item['kodebooking'] ?? '';
            $noRawat = $item['no_rawat'] ?? '';

            if (empty($kb) || empty($noRawat)) {
                continue;
            }

            try {
                $res = $this->sendPatientTasksManually($kb, $noRawat);
                if ($res['success']) {
                    $successCount++;
                } else {
                    $failedCount++;
                }

                $results[] = [
                    'kodebooking' => $kb,
                    'no_rawat'    => $noRawat,
                    'success'     => $res['success'],
                    'message'     => $res['message'] ?? '',
                    'tasks_sent'  => $res['tasks_sent'] ?? [],
                ];

                usleep(100000); // 100ms delay between BPJS calls
            } catch (\Throwable $e) {
                $failedCount++;
                $results[] = [
                    'kodebooking' => $kb,
                    'no_rawat'    => $noRawat,
                    'success'     => false,
                    'message'     => $e->getMessage(),
                    'tasks_sent'  => [],
                ];
            }
        }

        return [
            'success'       => true,
            'processed'     => count($patients),
            'success_count' => $successCount,
            'failed_count'  => $failedCount,
            'details'       => $results,
        ];
    }

}
