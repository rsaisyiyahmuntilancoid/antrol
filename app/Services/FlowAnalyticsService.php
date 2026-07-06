<?php
namespace App\Services;

use App\Models\BpjsPatientVisit;
use App\Models\RegPeriksa;
use App\Models\MapingDokterDpjpvclaim;
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
        8  => 'Batal',
        99 => 'Tidak Terdaftar',
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
        if (!$t1 || !$t2) {
            return null;
        }
        return (float) $t2->diffInMinutes($t1, false);
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
            $tid = (int)$t['taskid'];
            if (isset($t['waktu']) && isset($tasks[$tid])) {
                $tasks[$tid] = $this->parseTaskWaktu($t['waktu']);
            }
        }

        $durations = [
            'checkin_to_nurse'   => $this->diffMinutes($tasks[3], $tasks[4]),
            'nurse_to_doctor'    => $this->diffMinutes($tasks[4], $tasks[5]),
            'doctor_to_pharmacy' => $this->diffMinutes($tasks[5], $tasks[6]),
            'pharmacy_to_done'   => $this->diffMinutes($tasks[6], $tasks[7]),
            'total_time'         => $this->diffMinutes($tasks[3], $tasks[7] ?? $tasks[5] ?? null),
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
        if (!$taskData) {
            return 'Belum Terkirim';
        }
        
        $taskIds = array_column($taskData, 'taskid');
        $taskIds = array_map('intval', $taskIds);
        $taskIds = array_unique($taskIds);
        sort($taskIds);
        
        if (in_array(8, $taskIds)) {
            return 'Tidak Hadir / Batal';
        }
        if (in_array(99, $taskIds)) {
            return 'Tidak Terdaftar';
        }
        
        $monitoredTasks = array_values(array_intersect($taskIds, [3, 4, 5, 6, 7]));
        
        if (empty($monitoredTasks)) {
            $otherTasks = array_values(array_intersect($taskIds, [1, 2]));
            if (!empty($otherTasks)) {
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
        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        $query = RegPeriksa::with(['referensiMobilejknBpjs'])
            ->where('tgl_registrasi', $date)
            ->where('kd_pj', $kdPj);

        if (!empty($excludePoliArray)) {
            $query->whereNotIn('kd_poli', $excludePoliArray);
        }

        $registrations = $query->get();
        $total = $registrations->count();
        $synced = 0;

        $startTime = microtime(true);

        foreach ($registrations as $reg) {
            $kodebooking = $reg->referensiMobilejknBpjs?->nobooking ?? $reg->no_rawat;
            if (!$kodebooking) {
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
        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        $query = RegPeriksa::with(['referensiMobilejknBpjs'])
            ->where('tgl_registrasi', $date)
            ->where('kd_pj', $kdPj);

        if (!empty($excludePoliArray)) {
            $query->whereNotIn('kd_poli', $excludePoliArray);
        }

        $registrations = $query->get();
        $total = $registrations->count();
        $synced = 0;
        $failed = 0;

        foreach ($registrations as $reg) {
            $kodebooking = $reg->referensiMobilejknBpjs?->nobooking ?? $reg->no_rawat;
            if (!$kodebooking) {
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

    public function getAnalyticsData(?string $dateFrom = null, ?string $dateTo = null): array
    {
        $dateFrom = $dateFrom ?: Carbon::now()->toDateString();
        $dateTo   = $dateTo ?: Carbon::now()->toDateString();

        $dateFromObj = Carbon::parse($dateFrom);
        $dateToObj   = Carbon::parse($dateTo);
        $todayStr    = Carbon::now()->toDateString();

        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));
        $excludePoliArray = array_unique(array_merge($excludePoliArray, ['HD', 'IGD', 'IGDK']));

        // Group & count registrations by date in SIMRS (filtered by BPJ and excluding polikliniks)
        $simrsQuery = RegPeriksa::whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
            ->where('kd_pj', $kdPj);
        if (!empty($excludePoliArray)) {
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
            'regPeriksa.pemeriksaanRalan',
            'regPeriksa.resepObat'
        ])
            ->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

        if (!empty($excludePoliArray)) {
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
        if (!empty($excludePoliArray)) {
            $simrsRegsQuery->whereNotIn('kd_poli', $excludePoliArray);
        }
        $simrsRegs = $simrsRegsQuery->get();

        $missingRegs = [];
        foreach ($simrsRegs as $reg) {
            if (!$visitsByNoRawat->has($reg->no_rawat)) {
                $missingRegs[] = $reg;
            }
        }

        if (!empty($missingRegs)) {
            $syncCount = 0;
            foreach ($missingRegs as $reg) {
                $kodebooking = $reg->referensiMobilejknBpjs?->nobooking ?? $reg->no_rawat;
                if (!$kodebooking) {
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
                            'no_rawat' => $reg->no_rawat,
                            'tanggalperiksa' => $reg->tgl_registrasi,
                            'norm' => $reg->no_rkm_medis,
                            'kodepoli' => $reg->kd_poli,
                            'namapoli' => $reg->poliklinik?->nm_poli,
                            'kodedokter' => $reg->dokter?->kd_dokter,
                            'namadokter' => $reg->dokter?->nm_dokter,
                            'task_data' => [],
                            'last_sync' => null,
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
                'regPeriksa.pemeriksaanRalan',
                'regPeriksa.resepObat'
            ])
                ->whereBetween('tanggalperiksa', [$dateFrom, $dateTo]);

            if (!empty($excludePoliArray)) {
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
        if (!empty($excludePoliArray)) {
            $visitsCountQuery->whereNotIn('kodepoli', $excludePoliArray);
        }
        $visitsCountByDate = $visitsCountQuery->groupBy('tanggalperiksa')
            ->selectRaw('tanggalperiksa, count(*) as count')
            ->pluck('count', 'tanggalperiksa')
            ->toArray();

        $daysInRange = $dateFromObj->diffInDays($dateToObj) + 1;
        $missingDates = array_keys(array_diff_key($simrsRegistrationsByDate, $visitsCountByDate));

        // 4. Build flows from JKN task data
        $patientFlows = [];
        foreach ($visits as $visit) {
            /** @var BpjsPatientVisit $visit */
            $realTimestamps = $visit->regPeriksa 
                ? $this->getRealTimestamps($visit->regPeriksa) 
                : [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];

            $taskData = $visit->task_data;
            $hasBpjsData = ($taskData !== null && count($taskData) > 0);
            $syncStatus = $hasBpjsData ? 'synced' : 'pending';

            if ($hasBpjsData) {
                $durations = $this->computeDurationsFromTaskData($taskData);
                $status = $this->determineStatusFromTaskData($taskData);
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

            $bpjsTimestamps = $this->getBpjsTimestamps($visit);
            $comparison = $this->compareBpjsAndSimrs($bpjsTimestamps, $realTimestamps);
            $anomalies = $this->detectPatientAnomalies($realTimestamps, $bpjsTimestamps, $durations);

            // Resolve doctor name using SIMRS first, then mapping table, then BPJS namadokter
            $docName = 'N/A';
            if ($visit->regPeriksa && $visit->regPeriksa->dokter) {
                $docName = $visit->regPeriksa->dokter->nm_dokter;
            } elseif ($visit->kodedokter && isset($doctorMappings[$visit->kodedokter]) && $doctorMappings[$visit->kodedokter]->dokter) {
                $docName = $doctorMappings[$visit->kodedokter]->dokter->nm_dokter;
            } else {
                $docName = $visit->namadokter ?? 'N/A';
            }

            $patientFlows[] = [
                'no_rawat'        => $visit->no_rawat,
                'no_rkm_medis'    => $visit->norm ?? $visit->regPeriksa?->no_rkm_medis,
                'nm_pasien'       => $visit->regPeriksa?->pasien?->nm_pasien ?? 'N/A',
                'nm_poli'         => $visit->namapoli ?? ($visit->regPeriksa?->poliklinik?->nm_poli ?? 'N/A'),
                'nm_dokter'       => $docName,
                'jam_reg'         => $visit->regPeriksa?->jam_reg ? ($visit->regPeriksa->jam_reg instanceof \DateTimeInterface ? $visit->regPeriksa->jam_reg->format('H:i') : substr((string) $visit->regPeriksa->jam_reg, 0, 5)) : '00:00',
                'tgl_registrasi'  => $visit->tanggalperiksa ? ($visit->tanggalperiksa instanceof Carbon ? $visit->tanggalperiksa->toDateString() : (string)$visit->tanggalperiksa) : '',
                'has_booking'     => (strpos($visit->kodebooking, '/') === false),
                'kode_booking'    => $visit->kodebooking,
                'timestamps_real' => array_map(fn($c) => $c?->toDateTimeString(), $realTimestamps),
                'timestamps_sent' => array_map(fn($c) => $c?->toDateTimeString(), $bpjsTimestamps),
                'durations'       => $durations,
                'status'          => $status,
                'anomalies'       => $anomalies,
                'has_anomalies'   => count($anomalies) > 0,
                'comparison'      => $comparison,
                'is_bpjs_source'  => $syncStatus === 'synced',
                'sync_status'     => $syncStatus,
                'last_sync'       => $visit->last_sync?->toDateTimeString(),
            ];
        }

        // Aggregate statistics using the computed JKN flows
        $stats = $this->calculateStatistics($patientFlows);
        $clinicStats = $this->getClinicStatistics($patientFlows);
        $doctorStats = $this->getDoctorStatistics($patientFlows);
        $timeDist = $this->getTimeDistribution($patientFlows);
        $anomalies = $this->aggregateAnomalies($patientFlows);
        $globalStats = $this->calculateGlobalStats($patientFlows);

        // Count cancelled patients
        $batalCount = collect($patientFlows)->where('status', 'Tidak Hadir / Batal')->count();

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

    public function syncSinglePatient(string $kodebooking, string $noRawat): array
    {
        try {
            $reg = RegPeriksa::with([
                'referensiMobilejknBpjs',
                'pasien',
                'poliklinik',
                'dokter',
            ])->find($noRawat);
            if (! $reg) {
                return ['success' => false, 'message' => 'Pasien tidak ditemukan'];
            }

            $listTaskResult = app(MobileJknService::class)->getListTask($kodebooking);
            $visitData      = [
                'kodebooking'    => $kodebooking,
                'last_sync'      => now(),
                'no_rawat'       => $reg->no_rawat,
                'tanggalperiksa' => $reg->tgl_registrasi,
            ];

            if ($reg->referensiMobilejknBpjs) {
                $visitData = array_merge($visitData, [
                    'nomorkartu'       => $reg->referensiMobilejknBpjs->nomorkartu ?? null,
                    'nik'              => $reg->referensiMobilejknBpjs->nik ?? null,
                    'nohp'             => $reg->referensiMobilejknBpjs->nohp ?? null,
                    'norm'             => $reg->referensiMobilejknBpjs->norm ?? null,
                    'kodepoli'         => $reg->referensiMobilejknBpjs->kodepoli ?? null,
                    'namapoli'         => $reg->poliklinik->nm_poli ?? null,
                    'kodedokter'       => $reg->referensiMobilejknBpjs->kodedokter ?? null,
                    'namadokter'       => $reg->dokter->nm_dokter ?? null,
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
                    'validasi'         => $reg->referensiMobilejknBpjs->validasi ?? null,
                ]);
            } else {
                $visitData = array_merge($visitData, [
                    'nomorkartu'       => $reg->pasien->no_peserta ?? null,
                    'nik'              => $reg->pasien->no_ktp ?? null,
                    'nohp'             => $reg->pasien->no_tlp ?? null,
                    'norm'             => $reg->no_rkm_medis,
                    'kodepoli'         => $reg->kd_poli,
                    'namapoli'         => $reg->poliklinik->nm_poli ?? null,
                    'kodedokter'       => $reg->kd_dokter,
                    'namadokter'       => $reg->dokter->nm_dokter ?? null,
                    'nomorantrean'     => $reg->no_reg,
                    'angkaantrean'     => intval($reg->no_reg),
                ]);
            }

            if (! $listTaskResult['success']) {
                return [
                    'success' => false,
                    'message' => $listTaskResult['message'] ?? $listTaskResult['error'] ?? 'Gagal mengambil data dari BPJS'
                ];
            }

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
                    'timestamps_sent' => array_map(fn($c) => $c?->toDateTimeString(), $bpjsTimestamps),
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

    private function parseTaskWaktu($waktu): ?Carbon
    {
        if (!$waktu) {
            return null;
        }

        if (is_string($waktu)) {
            $waktu = trim(str_replace(['WIB', 'WITA', 'WIT'], '', $waktu));
        }

        if (is_numeric($waktu)) {
            $val = (int)$waktu;
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
        $timestamps = [1 => null, 2 => null, 3 => null, 4 => null, 5 => null, 6 => null, 7 => null];
        $taskData   = $visit->task_data ?? [];
        if (! is_array($taskData)) {
            $taskData = json_decode($taskData, true) ?: [];
        }
        foreach ($taskData as $task) {
            $taskId = $task['taskid'] ?? null;
            $waktu  = $task['waktu'] ?? null;
            if ($taskId && $waktu && isset($timestamps[$taskId])) {
                $timestamps[$taskId] = $this->parseTaskWaktu($waktu);
            }
        }
        return $timestamps;
    }

    public function compareBpjsAndSimrs(array $bpjsTimestamps, array $simrsTimestamps): array
    {
        $comparison = [];
        foreach (self::MONITOR_TASKS as $taskId) {
            $bpjsTime    = $bpjsTimestamps[$taskId];
            $simrsTime   = $simrsTimestamps[$taskId];
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
        ];
        $timestamps[1] = $reg->tgl_registrasi ? $this->parseTimestamp($reg->tgl_registrasi, $reg->jam_reg) : null;
        $timestamps[2] = $timestamps[1];
        
        // If registered with Mobile JKN, use check-in (validasi) time as Task 3
        if ($reg->referensiMobilejknBpjs && $reg->referensiMobilejknBpjs->validasi) {
            $timestamps[3] = Carbon::parse($reg->referensiMobilejknBpjs->validasi);
        } else {
            $timestamps[3] = $timestamps[1];
        }

        $pemeriksaan = $reg->pemeriksaanRalan;
        if ($pemeriksaan && $pemeriksaan->isNotEmpty()) {
            $first         = $pemeriksaan->sortBy('tgl_periksa')->first();
            $last          = $pemeriksaan->sortByDesc('tgl_periksa')->first();
            $timestamps[4] = $this->parseTimestamp($first->tgl_periksa, $first->jam_periksa);
            $timestamps[5] = $this->parseTimestamp($last->tgl_periksa, $last->jam_periksa);
        }

        $resep = $reg->resepObat;
        if ($resep && $resep->isNotEmpty()) {
            $first         = $resep->sortBy('tgl_periksa')->first();
            $last          = $resep->sortByDesc('tgl_periksa')->first();
            $timestamps[6] = $this->parseTimestamp($first->tgl_periksa, $first->jam);
            $timestamps[7] = $this->parseTimestamp($last->tgl_periksa, $last->jam);
        }

        if (empty($timestamps[4]) && $reg->stts == 'Sudah') {
            $timestamps[4] = $timestamps[3];
        }
        if (empty($timestamps[5]) && $reg->stts == 'Sudah') {
            $timestamps[5] = $timestamps[4];
        }
        if (empty($timestamps[6]) && $reg->stts == 'Sudah') {
            $timestamps[6] = $timestamps[5];
        }
        if (empty($timestamps[7]) && $reg->stts == 'Sudah') {
            $timestamps[7] = $timestamps[6];
        }

        return $timestamps;
    }

    private function parseTimestamp($date, $time = null): ?Carbon
    {
        if (! $date) {
            return null;
        }

        $dateStr = $date instanceof Carbon ? $date->toDateString() : (string) $date;
        $timeStr = $time ? (string) $time : '00:00:00';
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
            return 'Tidak Hadir / Batal';
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
            'total_anomalies' => 0,
            'timestamp_buatan' => [],
            'durasi_negatif' => [],
            'farmasi_10_menit' => [],
            'outlier_durasi' => [],
            'belum_terkirim' => [],
        ];

        foreach ($patients as $p) {
            if (count($p['anomalies']) > 0) {
                $counts['total_anomalies']++;
                foreach ($p['anomalies'] as $type) {
                    if (isset($counts[$type])) {
                        $counts[$type][] = [
                            'no_rawat' => $p['no_rawat'],
                            'nm_pasien' => $p['nm_pasien'],
                            'nm_poli' => $p['nm_poli'],
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
            'checkin_to_nurse'   => [],
            'nurse_to_doctor'    => [],
            'doctor_to_pharmacy' => [],
            'pharmacy_to_done'   => [],
            'total_time'         => [],
        ];

        foreach ($patientFlows as $p) {
            if ($p['status'] === 'Lengkap (3,4,5,6,7)') {
                $completed++;
            } elseif ($p['status'] === 'Belum Terkirim') {
                $waiting++;
            } elseif ($p['status'] === 'Tidak Hadir / Batal' || $p['status'] === 'Tidak Terdaftar') {
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
                'checkin_to_nurse'   => ! empty($durations['checkin_to_nurse']) ? round(array_sum($durations['checkin_to_nurse']) / count($durations['checkin_to_nurse']), 1) : null,
                'nurse_to_doctor'    => ! empty($durations['nurse_to_doctor']) ? round(array_sum($durations['nurse_to_doctor']) / count($durations['nurse_to_doctor']), 1) : null,
                'doctor_to_pharmacy' => ! empty($durations['doctor_to_pharmacy']) ? round(array_sum($durations['doctor_to_pharmacy']) / count($durations['doctor_to_pharmacy']), 1) : null,
                'pharmacy_to_done'   => ! empty($durations['pharmacy_to_done']) ? round(array_sum($durations['pharmacy_to_done']) / count($durations['pharmacy_to_done']), 1) : null,
                'total_time'         => ! empty($durations['total_time']) ? round(array_sum($durations['total_time']) / count($durations['total_time']), 1) : null,
            ],
        ];
    }

    private function computeStats(array $values): array
    {
        $count = count($values);
        if ($count === 0) {
            return ['count' => 0, 'median' => null];
        }

        sort($values);
        $mid = floor(($count - 1) / 2);
        $median = $count % 2
            ? $values[$mid]
            : (($values[$mid] + $values[$mid + 1]) / 2);

        return [
            'count'  => $count,
            'median' => round($median, 1),
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

            if ($p['status'] !== 'Tidak Hadir / Batal') {
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
                    'patient_count'    => 0,
                    'waktu_layan_poli' => [],
                    'total_waktu_rs'   => [],
                ];
            }

            if ($p['status'] !== 'Tidak Hadir / Batal') {
                $byDoctor[$doctor]['patient_count']++;
                if (isset($p['durations']['waktu_layan_poli']) && $p['durations']['waktu_layan_poli'] !== null) {
                    $byDoctor[$doctor]['waktu_layan_poli'][] = $p['durations']['waktu_layan_poli'];
                }
                if (isset($p['durations']['total_waktu_rs']) && $p['durations']['total_waktu_rs'] !== null) {
                    $byDoctor[$doctor]['total_waktu_rs'][] = $p['durations']['total_waktu_rs'];
                }
            }
        }

        $aggregated = [];
        foreach ($byDoctor as $doctor => $data) {
            $aggregated[$doctor] = [
                'patient_count'    => $data['patient_count'],
                'waktu_layan_poli' => $this->computeStats($data['waktu_layan_poli']),
                'total_waktu_rs'   => $this->computeStats($data['total_waktu_rs']),
            ];
        }

        return $aggregated;
    }

    private function getTimeDistribution(array $patientFlows): array
    {
        $dist = [
            'checkin_to_nurse'   => [],
            'nurse_to_doctor'    => [],
            'doctor_to_pharmacy' => [],
            'pharmacy_to_done'   => [],
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
}
