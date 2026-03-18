<?php

namespace App\Console\Commands;

use App\Models\ReferensiMobilejknBpjs;
use App\Models\RegPeriksa;
use App\Models\Poliklinik;
use App\Models\Dokter;
use App\Models\MapingPoliBpjs;
use App\Models\MapingDokterDpjpvclaim;
use App\Models\Jadwal;
use App\Services\MobileJknService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class SendBpjsTaskIds extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'bpjs:send-task-ids
                            {--date-from= : Start date (Y-m-d)}
                            {--date-to= : End date (Y-m-d)}
                            {--mjkn : Run only for Mobile JKN references}
                            {--dry-run : Show what would be processed without actually sending}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Send BPJS task IDs for patients based on registration data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting BPJS Task ID Sender...');
        $this->newLine();

        // Get configuration from environment
        $kdPj = config('mobilejkn.kd_pj', 'BPJ');
        $excludePoli = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        // Get date range
        $dateFrom = $this->option('date-from') ?: date('Y-m-d');
        $dateTo = $this->option('date-to') ?: date('Y-m-d');

        $this->info("Processing patients from {$dateFrom} to {$dateTo}");
        $this->info("BPJS Payer Code: {$kdPj}");
        if (!empty($excludePoliArray)) {
            $this->info("Excluding Poli: " . implode(', ', $excludePoliArray));
        }
        $this->newLine();

        // Build query for patients
        $query = RegPeriksa::with([
            'referensiMobilejknBpjs',
            'poliklinik',
            'dokter',
            'pasien',
            'bridgingSep'
        ])
        ->where('kd_pj', $kdPj)
        ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo]);

        // Exclude specific poli if configured
        if (!empty($excludePoliArray)) {
            $query->whereNotIn('kd_poli', $excludePoliArray);
        }

        // Run only for Mobile JKN references if requested
        if ($this->option('mjkn')) {
            $query->has('referensiMobilejknBpjs');
        }

        $patients = $query->get();

        $this->info("Found {$patients->count()} patients to process");
        $this->newLine();

        if ($patients->isEmpty()) {
            $this->warn('No patients found matching the criteria.');
            return;
        }

        // Progress bar
        $progressBar = $this->output->createProgressBar($patients->count());
        $progressBar->start();

        $stats = [
            'processed' => 0,
            'antrean_success' => 0,
            'antrean_failed' => 0,
            'task_success' => 0,
            'task_failed' => 0,
        ];

        foreach ($patients as $patient) {
            $this->processPatient($patient, $stats);

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->newLine(2);

        // Display final statistics
        $this->displayStatistics($stats);

        $this->info('BPJS Task ID processing completed!');
    }

    /**
     * Process a single patient
     */
    protected function processPatient($patient, &$stats)
    {
        $stats['processed']++;

        $referensi = $patient->referensiMobilejknBpjs;
        if (!$referensi) {
            $this->line("No referensi data for patient: {$patient->no_rawat}");
        }

        // Use nobooking if referensi exists, otherwise use no_rawat
        $kodebooking = $referensi ? $referensi->nobooking : $patient->no_rawat;

        // Prepare patient data for antrean
        // $patientData = $this->preparePatientData($patient, $referensi);

        // Add antrean first
        $this->line("Processing patient: {$patient->no_rawat} (Booking: {$kodebooking})");

        if (!$this->option('dry-run')) {
            // $antreanResult = app(MobileJknService::class)->addAntrean($patientData);

            // if ($antreanResult['success']) {
            //     $stats['antrean_success']++;
            //     $this->line("Antrean added successfully for: {$kodebooking}");

                // Send task IDs
            $this->sendTaskIds($kodebooking, $stats);
            // } else {
            //     $stats['antrean_failed']++;
            //     $this->line("Failed to add antrean for: {$kodebooking} - " . ($antreanResult['error'] ?? 'Unknown error'));
            // }
        } else {
            $this->line("DRY RUN: Would add antrean for: {$kodebooking}");
            $this->sendTaskIds($kodebooking, $stats, true);
        }
    }

    /**
     * Send task IDs for a patient
     */
    protected function sendTaskIds($kodebooking, &$stats, $dryRun = false)
    {
        $taskIds = [3, 4, 5, 6, 7]; // Task IDs to send

        foreach ($taskIds as $taskId) {
            if ($dryRun) {
                $this->line("DRY RUN: Would send Task ID {$taskId} for: {$kodebooking}");
                continue;
            }

            $result = app(MobileJknService::class)->updateTaskIdFromDatabase($kodebooking, $taskId);

            if ($result['success']) {
                $stats['task_success']++;
                // Try to get success message from BPJS metadata
                $successMessage = 'Ok';
                if (isset($result['data']['metadata']['message'])) {
                    $successMessage = $result['data']['metadata']['message'];
                } elseif (isset($result['metadata']['message'])) {
                    $successMessage = $result['metadata']['message'];
                }
                $this->line("Task ID {$taskId} sent successfully for: {$kodebooking} - " . $successMessage);
            } else {
                $stats['task_failed']++;
                // Try to get detailed error message from BPJS metadata
                $errorMessage = $result['error'] ?? 'Unknown error';
                if (isset($result['data']['metadata']['message'])) {
                    $errorMessage = $result['data']['metadata']['message'];
                } elseif (isset($result['metadata']['message'])) {
                    $errorMessage = $result['metadata']['message'];
                }
                $this->line("Failed to send Task ID {$taskId} for: {$kodebooking} - " . $errorMessage);
            }
        }
    }

    /**
     * Prepare patient data for antrean API
     * Uses the same logic as MobileJknService::sendAddAntreanByNoRawatInternal
     */
    protected function preparePatientData($patient, $referensi)
    {
        try {
            $pasien = $patient->pasien;
            $mapPoli = MapingPoliBpjs::where('kd_poli_rs', $patient->kd_poli)->first();
            $mapDok = MapingDokterDpjpvclaim::where('kd_dokter', $patient->kd_dokter)->first();

            $jadwal = Jadwal::where('kd_dokter', $patient->kd_dokter)
                ->where('kd_poli', $patient->kd_poli)
                ->orderBy('jam_mulai')
                ->first();

            $kodepoli = $mapPoli ? $mapPoli->kd_poli_bpjs : $patient->kd_poli;
            $namapoli = $patient->poliklinik->nm_poli ?? ($mapPoli->nm_poli_bpjs ?? null);
            $kodedokter = $mapDok ? $mapDok->kd_dokter_bpjs : $patient->kd_dokter;
            $namadokter = $mapDok ? ($mapDok->nm_dokter_bpjs ?? $patient->dokter->nm_dokter ?? null) : ($patient->dokter->nm_dokter ?? null);

            // Determine jam praktek
            if ($jadwal) {
                $jamMulai = substr($jadwal->jam_mulai ?? '00:00:00', 0, 5);
                $jamSelesai = substr($jadwal->jam_selesai ?? $jadwal->jam_mulai ?? '00:00:00', 0, 5);
                $jampraktek = $jamMulai . '-' . $jamSelesai;
            } else {
                $jampraktek = '08:00-16:00';
            }

            // Calculate estimated service time
            $noRegInt = intval($patient->no_reg);
            // Handle tgl_registrasi which might be a Carbon instance or string
            $tglRegistrasi = $patient->tgl_registrasi;
            if ($tglRegistrasi instanceof Carbon) {
                $tglRegistrasiStr = $tglRegistrasi->format('Y-m-d');
            } else {
                $tglRegistrasiStr = $tglRegistrasi ? explode(' ', $tglRegistrasi)[0] : date('Y-m-d');
            }
            $baseDatetime = Carbon::parse($tglRegistrasiStr . ' ' . ($jadwal->jam_mulai ?? '00:00:00'));
            $estimasidilayani = $baseDatetime->copy()->addMinutes($noRegInt * 2);

            // Determine if patient is new
            $pasienbaru = 0;
            if (stripos($patient->stts_daftar ?? '', 'Baru') !== false) {
                $pasienbaru = 1;
            }

            // Determine jenis kunjungan and nomor referensi
            // Try to get from bridging SEP or referensi
            $jenisKunjungan = 1; // Default: Rujukan FKTP
            $nomorreferensi = '';
            
            if ($patient->bridgingSep) {
                $nomorreferensi = $patient->bridgingSep->no_rujukan ?? '';
            } elseif ($referensi) {
                $nomorreferensi = $referensi->nomorreferensi ?? '';
            }
            
            // If no rujukan available, set default
            if (empty($nomorreferensi)) {
                $jenisKunjungan = 1; // Default to FKTP referral
                $nomorreferensi = ''; // Will be empty
            }

            // Build nomor antrean
            $angkaAntrean = str_pad((string) intval($patient->no_reg), 3, '0', STR_PAD_LEFT);
            $nomorAntrean = ($kodepoli ? $kodepoli : $patient->kd_poli) . '-' . $angkaAntrean;

            return [
                'kodebooking' => $referensi ? $referensi->nobooking : $patient->no_rawat,
                'jenispasien' => 'JKN',
                'nomorkartu' => $pasien->no_peserta ?? '',
                'nik' => $pasien->no_ktp ?? '',
                'nohp' => $pasien->no_tlp ?? '',
                'kodepoli' => $kodepoli,
                'namapoli' => $namapoli,
                'pasienbaru' => $pasienbaru,
                'norm' => $patient->no_rkm_medis,
                'tanggalperiksa' => $tglRegistrasiStr,
                'kodedokter' => $kodedokter,
                'namadokter' => $namadokter,
                'jampraktek' => $jampraktek,
                'jeniskunjungan' => $jenisKunjungan,
                'nomorreferensi' => $nomorreferensi,
                'nomorantrean' => $nomorAntrean,
                'angkaantrean' => intval($patient->no_reg),
                'estimasidilayani' => (int) ($estimasidilayani->timestamp * 1000),
                'sisakuotajkn' => $jadwal ? max(0, intval($jadwal->kuota) - intval($patient->no_reg)) : 0,
                'kuotajkn' => $jadwal ? intval($jadwal->kuota) : 0,
                'sisakuotanonjkn' => $jadwal ? max(0, intval($jadwal->kuota) - intval($patient->no_reg)) : 0,
                'kuotanonjkn' => $jadwal ? intval($jadwal->kuota) : 0,
                'keterangan' => 'Peserta harap 30 menit sebelum dilayani'
            ];
        } catch (\Exception $e) {
            Log::error('Error preparing patient data', [
                'no_rawat' => $patient->no_rawat,
                'error' => $e->getMessage()
            ]);
            
            // Return basic fallback data with same format as MobileJknService
            $pasien = $patient->pasien;
            $angkaAntrean = str_pad((string) intval($patient->no_reg), 3, '0', STR_PAD_LEFT);
            $nomorAntrean = $patient->kd_poli . '-' . $angkaAntrean;
            
            // Safe date handling for fallback
            $tglRegistrasi = $patient->tgl_registrasi;
            if ($tglRegistrasi instanceof \Carbon\Carbon) {
                $tanggalperiksa = $tglRegistrasi->format('Y-m-d');
            } elseif ($tglRegistrasi) {
                $tanggalperiksa = explode(' ', $tglRegistrasi)[0];
            } else {
                $tanggalperiksa = date('Y-m-d');
            }
            
            return [
                'kodebooking' => $referensi ? $referensi->nobooking : $patient->no_rawat,
                'jenispasien' => 'JKN',
                'nomorkartu' => $pasien->no_peserta ?? '',
                'nik' => $pasien->no_ktp ?? '',
                'nohp' => $pasien->no_tlp ?? '',
                'kodepoli' => $patient->kd_poli,
                'namapoli' => $patient->poliklinik->nm_poli ?? '',
                'pasienbaru' => 0,
                'norm' => $patient->no_rkm_medis,
                'tanggalperiksa' => $tanggalperiksa,
                'kodedokter' => $patient->kd_dokter,
                'namadokter' => $patient->dokter->nm_dokter ?? '',
                'jampraktek' => '08:00-16:00',
                'jeniskunjungan' => 1,
                'nomorreferensi' => '',
                'nomorantrean' => $nomorAntrean,
                'angkaantrean' => intval($patient->no_reg),
                'estimasidilayani' => (int) (now()->timestamp * 1000),
                'sisakuotajkn' => 0,
                'kuotajkn' => 0,
                'sisakuotanonjkn' => 0,
                'kuotanonjkn' => 0,
                'keterangan' => 'Peserta harap 30 menit sebelum dilayani'
            ];
        }
    }

    /**
     * Display processing statistics
     */
    protected function displayStatistics($stats)
    {
        $this->info('📈 Processing Statistics:');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Patients Processed', $stats['processed']],
                ['Antrean Success', $stats['antrean_success']],
                ['Antrean Failed', $stats['antrean_failed']],
                ['Task ID Success', $stats['task_success']],
                ['Task ID Failed', $stats['task_failed']],
            ]
        );
    }
}
