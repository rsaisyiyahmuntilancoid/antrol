<?php

namespace App\Services;

use App\Models\RegPeriksa;
use App\Models\PemeriksaanRalan;
use App\Models\ResepObat;
use App\Models\ReferensiMobilejknBpjs;
use App\Models\ReferensiMobilejknBpjsTaskid;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class FlowAnalyticsService
{
    /**
     * Get aggregated flow analytics data for a given date range
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    public function getAnalyticsData(string $dateFrom, string $dateTo): array
    {
        $patients = $this->buildPatientFlowData($dateFrom, $dateTo);

        // Aggregate stats
        $globalStats = $this->computeGlobalStats($patients);
        $clinicStats = $this->aggregateByClinic($patients);
        $doctorStats = $this->aggregateByDoctor($patients);
        $anomalies = $this->aggregateAnomalies($patients);

        // Compute summary counts
        $total = count($patients);
        $bpjsWithBooking = 0;
        $batal = 0;
        $statusCounts = [
            'Lengkap (3,4,5,6,7)' => 0,
            'Lengkap (3,4,5,6)' => 0,
            'Lengkap (3,4,5)' => 0,
            'Belum Lengkap' => 0,
            'Tidak Hadir / Batal' => 0,
        ];

        foreach ($patients as $p) {
            if ($p['has_booking']) {
                $bpjsWithBooking++;
            }
            if ($p['status'] === 'Tidak Hadir / Batal') {
                $batal++;
            }
            $statusCounts[$p['status']] = ($statusCounts[$p['status']] ?? 0) + 1;
        }

        return [
            'date_range' => ['from' => $dateFrom, 'to' => $dateTo],
            'summary' => [
                'total_patients' => $total,
                'bpjs_with_booking' => $bpjsWithBooking,
                'batal_patients' => $batal,
            ],
            'status_counts' => $statusCounts,
            'global_stats' => $globalStats,
            'clinic_stats' => $clinicStats,
            'doctor_stats' => $doctorStats,
            'anomalies' => $anomalies,
            'patients' => $patients,
        ];
    }

    /**
     * Query all patient registrations and compute their flow timestamps & durations
     *
     * @param string $dateFrom
     * @param string $dateTo
     * @return array
     */
    private function buildPatientFlowData(string $dateFrom, string $dateTo): array
    {
        $registrations = RegPeriksa::with([
            'pasien',
            'poliklinik',
            'dokter',
            'referensiMobilejknBpjs',
            'referensiMobilejknBpjsTaskid',
            'pemeriksaanRalan.petugas',
            'pemeriksaanRalan.dokter',
            'resepObat'
        ])
        ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
        ->where('kd_pj', 'BPJ') // Insurance code for BPJS
        ->orderBy('tgl_registrasi', 'asc')
        ->orderBy('jam_reg', 'asc')
        ->get();

        $patientFlows = [];

        foreach ($registrations as $reg) {
            $realTimestamps = $this->getRealTimestamps($reg);
            $sentTimestamps = $this->getSentTimestamps($reg);
            $durations = $this->computeDurations($realTimestamps);
            $status = $this->determineFlowStatus($realTimestamps, $reg->stts);
            $patientAnomalies = $this->detectPatientAnomalies($realTimestamps, $sentTimestamps, $durations);

            $patientFlows[] = [
                'no_rawat' => $reg->no_rawat,
                'no_rkm_medis' => $reg->no_rkm_medis,
                'nm_pasien' => $reg->pasien->nm_pasien ?? 'N/A',
                'nm_poli' => $reg->poliklinik->nm_poli ?? 'N/A',
                'nm_dokter' => $reg->dokter->nm_dokter ?? 'N/A',
                'jam_reg' => $reg->jam_reg ? ($reg->jam_reg instanceof Carbon ? $reg->jam_reg->format('H:i') : substr((string)$reg->jam_reg, 0, 5)) : '--:--',
                'tgl_registrasi' => $reg->tgl_registrasi ? ($reg->tgl_registrasi instanceof Carbon ? $reg->tgl_registrasi->toDateString() : str_replace(' 00:00:00', '', (string)$reg->tgl_registrasi)) : '',
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
                'anomalies' => $patientAnomalies,
                'has_anomalies' => count($patientAnomalies) > 0,
            ];
        }

        return $patientFlows;
    }

    private function parseDateTime($date, $time): ?Carbon
    {
        if (!$date || !$time) return null;
        
        $dateStr = $date instanceof Carbon ? $date->toDateString() : str_replace(' 00:00:00', '', (string)$date);
        $timeStr = $time instanceof Carbon ? $time->toTimeString() : (string)$time;
        
        if ($dateStr === '0000-00-00' || str_starts_with($dateStr, '0000') || str_starts_with($dateStr, '-')) {
            return null;
        }
        
        try {
            $parsed = Carbon::parse($dateStr . ' ' . $timeStr, 'Asia/Jakarta');
            if ($parsed->year < 2000) {
                return null;
            }
            return $parsed;
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get real timestamps directly from hospital tables (SIMRS database)
     *
     * @param RegPeriksa $reg
     * @return array
     */
    public function getRealTimestamps(RegPeriksa $reg): array
    {
        $tglRegistrasi = $reg->tgl_registrasi;

        // Task 3: Check-in / Admisi
        $t3 = null;
        if ($reg->referensiMobilejknBpjs && $reg->referensiMobilejknBpjs->validasi) {
            try {
                $t3 = Carbon::parse($reg->referensiMobilejknBpjs->validasi, 'Asia/Jakarta');
            } catch (\Exception $e) {}
        }
        
        if (!$t3) {
            $t3 = $this->parseDateTime($tglRegistrasi, $reg->jam_reg);
        }

        // Task 4: Perawat mulai
        $t4 = null;
        $pemeriksaanPetugas = $reg->pemeriksaanRalan
            ->filter(function ($p) {
                return $p->petugas !== null || !empty($p->nip);
            })
            ->sortBy(function ($p) {
                return (string)$p->jam_rawat;
            })
            ->first();
        if ($pemeriksaanPetugas) {
            $t4 = $this->parseDateTime($pemeriksaanPetugas->tgl_perawatan ?: $tglRegistrasi, $pemeriksaanPetugas->jam_rawat);
        }

        // Task 5: Dokter selesai
        $t5 = null;
        $pemeriksaanDokter = $reg->pemeriksaanRalan
            ->filter(function ($p) {
                return $p->dokter !== null || !empty($p->nip);
            })
            ->sortByDesc(function ($p) {
                return (string)$p->jam_rawat;
            })
            ->first();
        if ($pemeriksaanDokter) {
            $t5 = $this->parseDateTime($pemeriksaanDokter->tgl_perawatan ?: $tglRegistrasi, $pemeriksaanDokter->jam_rawat);
        }

        // Task 6: Resep obat dibuat
        $t6 = null;
        $resep = $reg->resepObat
            ->sortByDesc(function ($r) {
                return (string)$r->jam;
            })
            ->first();
        if ($resep) {
            $t6 = $this->parseDateTime($resep->tgl_perawatan ?: $tglRegistrasi, $resep->jam);
        }

        // Task 7: Selesai penyerahan obat
        $t7 = null;
        if ($resep) {
            $t7 = $this->parseDateTime($resep->tgl_penyerahan, $resep->jam_penyerahan);
        }

        return [
            3 => $t3,
            4 => $t4,
            5 => $t5,
            6 => $t6,
            7 => $t7,
        ];
    }

    /**
     * Get sent timestamps from referensi_mobilejkn_bpjs_taskid table
     *
     * @param RegPeriksa $reg
     * @return array
     */
    public function getSentTimestamps(RegPeriksa $reg): array
    {
        $sent = [
            3 => null,
            4 => null,
            5 => null,
            6 => null,
            7 => null,
        ];

        foreach ($reg->referensiMobilejknBpjsTaskid as $task) {
            $taskId = (int)$task->taskid;
            if (isset($sent[$taskId])) {
                $sent[$taskId] = $task->waktu;
            }
        }

        return $sent;
    }

    /**
     * Compute differences in minutes between steps
     *
     * @param array $timestamps
     * @return array
     */
    public function computeDurations(array $timestamps): array
    {
        return [
            'waktu_tunggu_poli'    => $this->diffMinutes($timestamps[3], $timestamps[4]),
            'waktu_layan_poli'     => $this->diffMinutes($timestamps[4], $timestamps[5]),
            'waktu_tunggu_farmasi' => $this->diffMinutes($timestamps[5], $timestamps[6]),
            'waktu_layan_farmasi'  => $this->diffMinutes($timestamps[6], $timestamps[7]),
            'total_waktu_rs'       => $this->diffMinutes($timestamps[3], $timestamps[7] ?? $timestamps[5]),
        ];
    }

    /**
     * Determine status completeness category
     *
     * @param array $timestamps
     * @param string $stts
     * @return string
     */
    public function determineFlowStatus(array $timestamps, string $stts): string
    {
        if ($stts === 'Batal') {
            return 'Tidak Hadir / Batal';
        }

        if ($timestamps[3] && $timestamps[4] && $timestamps[5] && $timestamps[6] && $timestamps[7]) {
            return 'Lengkap (3,4,5,6,7)';
        }

        if ($timestamps[3] && $timestamps[4] && $timestamps[5] && $timestamps[6]) {
            return 'Lengkap (3,4,5,6)';
        }

        if ($timestamps[3] && $timestamps[4] && $timestamps[5]) {
            return 'Lengkap (3,4,5)';
        }

        return 'Belum Lengkap';
    }

    /**
     * Calculate descriptive statistics
     *
     * @param array $values
     * @return array
     */
    public function computeStats(array $values): array
    {
        $values = array_filter($values, function ($v) {
            return $v !== null;
        });

        $count = count($values);
        if ($count === 0) {
            return [
                'count' => 0,
                'avg' => 0,
                'median' => 0,
                'min' => 0,
                'max' => 0,
                'p90' => 0,
            ];
        }

        sort($values);
        $sum = array_sum($values);
        $avg = $sum / $count;

        // Median
        $middle = floor(($count - 1) / 2);
        if ($count % 2) {
            $median = $values[$middle];
        } else {
            $low = $values[$middle];
            $high = $values[$middle + 1];
            $median = ($low + $high) / 2;
        }

        $min = min($values);
        $max = max($values);

        // Percentile 90
        $p90Index = min((int)round(0.9 * ($count - 1)), $count - 1);
        $p90 = $values[$p90Index];

        return [
            'count' => $count,
            'avg' => round($avg, 2),
            'median' => round($median, 2),
            'min' => round($min, 2),
            'max' => round($max, 2),
            'p90' => round($p90, 2),
        ];
    }

    /**
     * Aggregate statistics per clinic
     *
     * @param array $patients
     * @return array
     */
    private function aggregateByClinic(array $patients): array
    {
        $byClinic = [];

        foreach ($patients as $p) {
            $clinic = $p['nm_poli'];
            if (!isset($byClinic[$clinic])) {
                $byClinic[$clinic] = [
                    'patient_count' => 0,
                    'waktu_tunggu_poli' => [],
                    'waktu_layan_poli' => [],
                    'waktu_tunggu_farmasi' => [],
                    'waktu_layan_farmasi' => [],
                    'total_waktu_rs' => [],
                ];
            }

            if ($p['status'] !== 'Tidak Hadir / Batal') {
                $byClinic[$clinic]['patient_count']++;
                foreach (['waktu_tunggu_poli', 'waktu_layan_poli', 'waktu_tunggu_farmasi', 'waktu_layan_farmasi', 'total_waktu_rs'] as $metric) {
                    if ($p['durations'][$metric] !== null) {
                        $byClinic[$clinic][$metric][] = $p['durations'][$metric];
                    }
                }
            }
        }

        $aggregated = [];
        foreach ($byClinic as $clinic => $data) {
            $aggregated[$clinic] = [
                'patient_count' => $data['patient_count'],
                'waktu_tunggu_poli' => $this->computeStats($data['waktu_tunggu_poli']),
                'waktu_layan_poli' => $this->computeStats($data['waktu_layan_poli']),
                'waktu_tunggu_farmasi' => $this->computeStats($data['waktu_tunggu_farmasi']),
                'waktu_layan_farmasi' => $this->computeStats($data['waktu_layan_farmasi']),
                'total_waktu_rs' => $this->computeStats($data['total_waktu_rs']),
            ];
        }

        return $aggregated;
    }

    /**
     * Aggregate statistics per doctor
     *
     * @param array $patients
     * @return array
     */
    private function aggregateByDoctor(array $patients): array
    {
        $byDoctor = [];

        foreach ($patients as $p) {
            $doctor = $p['nm_dokter'];
            if (!isset($byDoctor[$doctor])) {
                $byDoctor[$doctor] = [
                    'patient_count' => 0,
                    'waktu_layan_poli' => [],
                    'total_waktu_rs' => [],
                ];
            }

            if ($p['status'] !== 'Tidak Hadir / Batal') {
                $byDoctor[$doctor]['patient_count']++;
                if ($p['durations']['waktu_layan_poli'] !== null) {
                    $byDoctor[$doctor]['waktu_layan_poli'][] = $p['durations']['waktu_layan_poli'];
                }
                if ($p['durations']['total_waktu_rs'] !== null) {
                    $byDoctor[$doctor]['total_waktu_rs'][] = $p['durations']['total_waktu_rs'];
                }
            }
        }

        $aggregated = [];
        foreach ($byDoctor as $doctor => $data) {
            $aggregated[$doctor] = [
                'patient_count' => $data['patient_count'],
                'waktu_layan_poli' => $this->computeStats($data['waktu_layan_poli']),
                'total_waktu_rs' => $this->computeStats($data['total_waktu_rs']),
            ];
        }

        return $aggregated;
    }

    /**
     * Aggregate patient anomalies
     *
     * @param array $patients
     * @return array
     */
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

    /**
     * Detect patient anomalies
     *
     * @param array $real
     * @param array $sent
     * @param array $durations
     * @return array
     */
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
        if ($durations['waktu_tunggu_farmasi'] !== null && abs($durations['waktu_tunggu_farmasi'] - 10.0) < 0.001) {
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

    /**
     * Compute global statistics across all patients
     *
     * @param array $patients
     * @return array
     */
    private function computeGlobalStats(array $patients): array
    {
        $metrics = [
            'waktu_tunggu_poli' => [],
            'waktu_layan_poli' => [],
            'waktu_tunggu_farmasi' => [],
            'waktu_layan_farmasi' => [],
            'total_waktu_rs' => [],
        ];

        foreach ($patients as $p) {
            if ($p['status'] !== 'Tidak Hadir / Batal') {
                foreach ($metrics as $key => &$arr) {
                    if ($p['durations'][$key] !== null) {
                        $arr[] = $p['durations'][$key];
                    }
                }
            }
        }

        $stats = [];
        foreach ($metrics as $key => $values) {
            $stats[$key] = $this->computeStats($values);
        }

        return $stats;
    }

    /**
     * Compute difference in minutes between two Carbon instances
     *
     * @param Carbon|null $start
     * @param Carbon|null $end
     * @return float|null
     */
    private function diffMinutes(?Carbon $start, ?Carbon $end): ?float
    {
        if (!$start || !$end) return null;
        $diffSeconds = $end->timestamp - $start->timestamp;
        return round($diffSeconds / 60, 2);
    }
}
