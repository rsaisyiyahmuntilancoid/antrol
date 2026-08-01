<?php

namespace App\Console\Commands;

use App\Models\ReferensiMobilejknBpjs;
use App\Models\ReferensiMobilejknBpjsTaskid;
use App\Models\RegPeriksa;
use App\Services\MobileJknService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

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
                            {--all : Send all patients again, even if task IDs were already sent}
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
        $kdPj = config('mobilejkn.kd_pj', 'A65');
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
        if ($this->option('all')) {
            $this->info("Force Resend Mode: Sending all patients again");
        }
        $this->newLine();

        $stats = [
            'processed' => 0,
            'task_success' => 0,
            'task_failed' => 0,
            'task_cancelled' => 0,
        ];

        // ═════════════════════════════════════════════════════════════════
        // FASE 1: PROSES PASIEN MOBILE JKN
        // ═════════════════════════════════════════════════════════════════
        $this->info('--- FASE 1: Memproses Pasien Mobile JKN ---');

        $mjknQuery = RegPeriksa::with([
            'referensiMobilejknBpjs',
            'referensiMobilejknBpjsBatal',
            'referensiMobilejknBpjsAll',
            'poliklinik',
            'dokter',
            'pasien',
            'bridgingSep',
            'referensiMobilejknBpjsTaskid',
            'pemeriksaanRalan',
            'resepObat',
        ])
        ->where('kd_pj', $kdPj)
        ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
        ->whereHas('referensiMobilejknBpjsAll');

        if (!empty($excludePoliArray)) {
            $mjknQuery->whereNotIn('kd_poli', $excludePoliArray);
        }

        if (!$this->option('all')) {
            $mjknQuery->where(function ($q) {
                $q->whereDoesntHave('referensiMobilejknBpjsTaskid')
                  ->orWhereHas('referensiMobilejknBpjsTaskid', function ($subQ) {
                      $subQ->whereIn('taskid', ['3', '4', '5', '6', '7']);
                  }, '<', 5)
                  ->orWhere(function ($q2) {
                      $q2->whereHas('referensiMobilejknBpjs', function ($subQ) {
                          $subQ->where('status', 'Belum')
                               ->where(function ($vq) {
                                   $vq->whereNull('validasi')
                                      ->orWhere('validasi', '0000-00-00 00:00:00')
                                      ->orWhere('validasi', '-0001-11-30 00:00:00');
                               });
                      });
                  });
            });
        }

        $mjknPatients = $mjknQuery->get();
        $this->info("Ditemukan {$mjknPatients->count()} pasien Mobile JKN untuk diproses");

        if ($mjknPatients->isNotEmpty()) {
            $progressBarMjkn = $this->output->createProgressBar($mjknPatients->count());
            $progressBarMjkn->start();

            foreach ($mjknPatients as $patient) {
                $this->processMjknPatient($patient, $stats);
                $progressBarMjkn->advance();
            }

            $progressBarMjkn->finish();
            $this->newLine(2);
        }

        // ═════════════════════════════════════════════════════════════════
        // FASE 2: PROSES PASIEN ONSITE (NON-MJKN)
        // ═════════════════════════════════════════════════════════════════
        if (!$this->option('mjkn')) {
            $this->info('--- FASE 2: Memproses Pasien Onsite (Non-MJKN) ---');

            $onsiteQuery = RegPeriksa::with([
                'poliklinik',
                'dokter',
                'pasien',
                'bridgingSep',
                'referensiMobilejknBpjsTaskid',
                'pemeriksaanRalan',
                'resepObat',
            ])
            ->where('kd_pj', $kdPj)
            ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
            ->doesntHave('referensiMobilejknBpjsAll');

            if (!empty($excludePoliArray)) {
                $onsiteQuery->whereNotIn('kd_poli', $excludePoliArray);
            }

            if (!$this->option('all')) {
                $onsiteQuery->where(function ($q) {
                    $q->whereDoesntHave('referensiMobilejknBpjsTaskid')
                      ->orWhereHas('referensiMobilejknBpjsTaskid', function ($subQ) {
                          $subQ->whereIn('taskid', ['3', '4', '5', '6', '7']);
                      }, '<', 5);
                });
            }

            $onsitePatients = $onsiteQuery->get();
            $this->info("Ditemukan {$onsitePatients->count()} pasien Onsite untuk diproses");

            if ($onsitePatients->isNotEmpty()) {
                $progressBarOnsite = $this->output->createProgressBar($onsitePatients->count());
                $progressBarOnsite->start();

                foreach ($onsitePatients as $patient) {
                    $this->processOnsitePatient($patient, $stats);
                    $progressBarOnsite->advance();
                }

                $progressBarOnsite->finish();
                $this->newLine(2);
            }
        }

        // Display final statistics
        $this->displayStatistics($stats);
        $this->info('BPJS Task ID processing completed!');
    }

    /**
     * Process a single Mobile JKN patient
     */
    protected function processMjknPatient($patient, &$stats)
    {
        $stats['processed']++;

        $service = app(MobileJknService::class);

        // 1. Ambil referensi berdasarkan status
        $refActive = $patient->referensiMobilejknBpjs;       // status != Batal (bisa Belum / Checkin)
        $refBatal  = $patient->referensiMobilejknBpjsBatal;  // status = Batal
        $refAll    = $patient->referensiMobilejknBpjsAll;    // semua

        // 2. Cek fakta medis dan registrasi
        $hasExamination = $patient->pemeriksaanRalan && $patient->pemeriksaanRalan->isNotEmpty();
        $regStts        = strtolower(trim($patient->stts ?? ''));
        $isBatalInSimrs = $regStts === 'batal';

        $validasiKosong = $refActive && (
            empty($refActive->validasi) ||
            (string)$refActive->validasi === '0000-00-00 00:00:00' ||
            (string)$refActive->validasi === '-0001-11-30 00:00:00' ||
            strpos((string)$refActive->validasi, '0000-') !== false
        );

        // 3. Tentukan kode booking
        $kodebookingActive = $refActive?->nobooking ?? $patient->no_rawat;
        $kodebookingBatal  = $refBatal?->nobooking
                             ?? $refAll->first()?->nobooking
                             ?? $patient->no_rawat;

        // ──── KEPUTUSAN ────

        // CASE A: SIMRS mencatat status BATAL → Kirim Task 99
        if ($isBatalInSimrs) {
            $kb99 = $refActive?->nobooking ?? $kodebookingBatal;
            if ($this->hasTask99Sent($patient->no_rawat)) {
                $this->line("Patient {$patient->no_rawat} is Batal and Task 99 already sent (skipped)");
                return;
            }
            $this->sendCancelTask($patient, $kb99, $stats, $refActive ?? $refBatal);
            return;
        }

        // CASE B: Ada pemeriksaan → Kirim Task 3-7 Normal
        if ($hasExamination) {

            // ── CASE B1: Belum checkin (ref:Belum + validasi kosong) TAPI sudah periksa ──
            // Auto-checkin: hitung T3 = T4 - random(5,10)m, update referensi status+validasi
            if ($refActive && $validasiKosong) {
                $ts4 = $service->getTaskTimestampFromDatabase($kodebookingActive, 4);
                if ($ts4 && (int)$ts4 > 0) {
                    $offset = random_int(5, 10);
                    $ts3 = (string)((int)$ts4 - ($offset * 60 * 1000));
                    $checkinDt = Carbon::createFromTimestampMs((int)$ts3);

                    if (!$this->option('dry-run')) {
                        $refActive->update([
                            'status' => 'Checkin',
                            'validasi' => $checkinDt->format('Y-m-d H:i:s'),
                        ]);
                    }
                    $this->info("Auto-checkin MJKN: {$patient->no_rawat} → validasi={$checkinDt->format('Y-m-d H:i:s')}");
                }
            }

            // ── CASE B2: Kirim Task 3-7 ──
            $existingTaskIds = $patient->referensiMobilejknBpjsTaskid
                ? $patient->referensiMobilejknBpjsTaskid->pluck('taskid')->map(fn($id) => (int)$id)->toArray()
                : [];

            $this->sendTaskIds($kodebookingActive, $patient, $stats, $existingTaskIds);
            return;
        }

        // CASE C: Tidak ada pemeriksaan dan TIDAK ada refActive (semua ref Batal) → Task 99
        if (!$refActive) {
            if ($this->hasTask99Sent($patient->no_rawat)) {
                $this->line("Patient {$patient->no_rawat} all ref Batal and Task 99 already sent (skipped)");
                return;
            }
            $this->sendCancelTask($patient, $kodebookingBatal, $stats, $refBatal);
            return;
        }

        // CASE D: ref:Belum + validasi kosong + TANPA pemeriksaan → Pasien tidak datang → Task 99
        if ($refActive && $validasiKosong) {
            if ($this->hasTask99Sent($patient->no_rawat)) {
                $this->line("Patient {$patient->no_rawat} no checkin/exam and Task 99 already sent (skipped)");
                return;
            }
            $this->sendCancelTask($patient, $kodebookingActive, $stats, $refActive);
            return;
        }

        // CASE E: Ada validasi / Checkin tapi belum ada pemeriksaan → SKIP (tunggu pemeriksaan)
        $this->line("Patient {$patient->no_rawat} (Booking: {$kodebookingActive}) checked in, waiting for examination (skipped)");
    }

    /**
     * Process a single Onsite (non-MJKN) patient
     */
    protected function processOnsitePatient($patient, &$stats)
    {
        $stats['processed']++;

        $kodebooking    = $patient->no_rawat;
        $hasExamination = $patient->pemeriksaanRalan && $patient->pemeriksaanRalan->isNotEmpty();
        $hasResep       = $patient->resepObat && $patient->resepObat->isNotEmpty();
        $regStts        = strtolower(trim($patient->stts ?? ''));
        $isBatalInSimrs = $regStts === 'batal';

        $existingTaskIds = $patient->referensiMobilejknBpjsTaskid
            ? $patient->referensiMobilejknBpjsTaskid->pluck('taskid')->map(fn($id) => (int)$id)->toArray()
            : [];

        // ──── KEPUTUSAN ────

        // CASE A: stts = Batal di SIMRS → Task 99
        if ($isBatalInSimrs) {
            if (in_array(99, $existingTaskIds)) {
                $this->line("Onsite patient {$kodebooking} is Batal and Task 99 already sent (skipped)");
                return;
            }
            $this->sendCancelTask($patient, $kodebooking, $stats);
            return;
        }

        // CASE B: Ada data pemeriksaan → Kirim Task 3-7 Normal
        if ($hasExamination) {

            // B1: stts = Belum padahal sudah periksa → Auto-update stts = Sudah
            if ($regStts === 'belum') {
                if (!$this->option('dry-run')) {
                    $patient->update(['stts' => 'Sudah']);
                }
                $this->info("Auto-update reg_periksa.stts: {$patient->no_rawat} Belum → Sudah");
            }

            // B2: Cek kelengkapan task ID
            $expectedTasks = $hasResep ? [3, 4, 5, 6, 7] : [3, 4, 5];
            $allComplete   = count(array_diff($expectedTasks, $existingTaskIds)) === 0;

            if (!$this->option('all') && $allComplete) {
                $this->line("Onsite patient {$kodebooking} tasks already complete (skipped)");
                return;
            }

            // B3: Kirim task yang kurang (Task 3 mengambil jam_reg secara otomatis)
            $this->sendTaskIds($kodebooking, $patient, $stats, $existingTaskIds);
            return;
        }

        // CASE C: Tidak ada pemeriksaan → Pasien tidak periksa → Task 99
        if (in_array(99, $existingTaskIds)) {
            $this->line("Onsite patient {$kodebooking} has no exam and Task 99 already sent (skipped)");
            return;
        }

        // Auto-update reg_periksa.stts = Batal if it is currently 'Belum'
        if ($regStts === 'belum') {
            if (!$this->option('dry-run')) {
                $patient->update(['stts' => 'Batal']);
            }
            $this->info("Auto-update reg_periksa.stts: {$patient->no_rawat} Belum → Batal");
        }

        $this->sendCancelTask($patient, $kodebooking, $stats);
    }

    /**
     * Send task IDs for a patient with tiered fallback rules
     */
    protected function sendTaskIds($kodebooking, $patient, &$stats, $existingTaskIds = [])
    {
        // Safety check: jika kodebooking MJKN (bukan format no_rawat YYYY/MM/DD/XXXX),
        // pastikan tidak berstatus Batal di referensi
        if (strpos($kodebooking, '/') === false) {
            $isCancelled = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)
                ->where('status', 'Batal')
                ->exists();

            if ($isCancelled) {
                $this->warn("Booking {$kodebooking} is CANCELLED (Batal) in referensi. Skipping Tasks 3-7.");
                return;
            }
        }

        $service = app(MobileJknService::class);
        $dryRun  = $this->option('dry-run');

        // ── TASK 3 ──
        if ($this->option('all') || !in_array(3, $existingTaskIds)) {
            $ts3 = $service->getTaskTimestampFromDatabase($kodebooking, 3);
            if ($ts3 === null || (int)$ts3 < 1609459200000) {
                // FALLBACK: Ambil waktu Task 4 - random 5-10 menit
                $ts4 = $service->getTaskTimestampFromDatabase($kodebooking, 4);
                if ($ts4 && (int)$ts4 >= 1609459200000) {
                    $offset = random_int(5, 10);
                    $ts3 = (string)((int)$ts4 - ($offset * 60 * 1000));
                    $this->info("Task 3 fallback generated: {$kodebooking} → T4 - {$offset}m");
                }
            }

            if ($ts3 === null || (int)$ts3 < 1609459200000) {
                $this->warn("Task 3: no valid timestamp for {$kodebooking} → STOPPING task send");
                return;
            }

            if (!$this->sendSingleTaskId($kodebooking, 3, $ts3, $stats, $dryRun)) {
                if (!$dryRun) return; // jika gagal kirim Task 3, stop
            }
        }

        // ── TASK 4 ──
        if ($this->option('all') || !in_array(4, $existingTaskIds)) {
            $ts4 = $service->getTaskTimestampFromDatabase($kodebooking, 4);
            if ($ts4 === null || (int)$ts4 < 1609459200000) {
                $this->warn("Task 4: no examination timestamp for {$kodebooking} → sending Task 99");
                $this->sendCancelTask($patient, $kodebooking, $stats);
                return;
            }

            if (!$this->sendSingleTaskId($kodebooking, 4, $ts4, $stats, $dryRun)) {
                if (!$dryRun) return;
            }
        }

        // ── TASK 5 ──
        if ($this->option('all') || !in_array(5, $existingTaskIds)) {
            $ts5 = $service->getTaskTimestampFromDatabase($kodebooking, 5);
            if ($ts5 === null || (int)$ts5 < 1609459200000) {
                // FALLBACK: Ambil waktu Task 4 + random 5-10 menit
                $ts4Sent = $service->getTaskTimestampFromDatabase($kodebooking, 4);
                if ($ts4Sent && (int)$ts4Sent > 0) {
                    $offset = random_int(5, 10);
                    $ts5 = (string)((int)$ts4Sent + ($offset * 60 * 1000));
                    $this->info("Task 5 fallback generated: {$kodebooking} → T4 + {$offset}m");
                }
            }

            if ($ts5 && (int)$ts5 >= 1609459200000) {
                $this->sendSingleTaskId($kodebooking, 5, $ts5, $stats, $dryRun);
            }
        }

        // ── TASK 6 ──
        if ($this->option('all') || !in_array(6, $existingTaskIds)) {
            $ts6 = $service->getTaskTimestampFromDatabase($kodebooking, 6);
            if ($ts6 === null || (int)$ts6 < 1609459200000) {
                // TIDAK ADA RESEP → STOP DI SINI (tidak boleh fallback Task 6)
                $this->line("Task 6: no prescription for {$kodebooking} → stopping at Task 5 (normal)");
                return;
            }

            if (!$this->sendSingleTaskId($kodebooking, 6, $ts6, $stats, $dryRun)) {
                if (!$dryRun) return;
            }
        }

        // ── TASK 7 ──
        if ($this->option('all') || !in_array(7, $existingTaskIds)) {
            $ts7 = $service->getTaskTimestampFromDatabase($kodebooking, 7);
            if ($ts7 === null || (int)$ts7 < 1609459200000) {
                // FALLBACK: Ambil waktu Task 6 + random 5-10 menit
                $ts6Sent = $service->getTaskTimestampFromDatabase($kodebooking, 6);
                if ($ts6Sent && (int)$ts6Sent > 0) {
                    $offset = random_int(5, 10);
                    $ts7 = (string)((int)$ts6Sent + ($offset * 60 * 1000));
                    $this->info("Task 7 fallback generated: {$kodebooking} → T6 + {$offset}m");
                }
            }

            if ($ts7 && (int)$ts7 >= 1609459200000) {
                $this->sendSingleTaskId($kodebooking, 7, $ts7, $stats, $dryRun);
            }
        }
    }

    /**
     * Send a single task ID to BPJS API
     */
    protected function sendSingleTaskId(string $kodebooking, int $taskId, string $waktu, array &$stats, bool $dryRun = false): bool
    {
        if ($dryRun) {
            $this->line("DRY RUN: Would send Task ID {$taskId} for: {$kodebooking} (Waktu: {$waktu})");
            return true;
        }

        $service = app(MobileJknService::class);
        $result = $service->updateTaskId($kodebooking, $taskId, $waktu);

        if ($result['success']) {
            $stats['task_success']++;
            $msg = $result['data']['metadata']['message'] ?? ($result['metadata']['message'] ?? 'Ok');
            $this->line("Task ID {$taskId} sent successfully for: {$kodebooking} - {$msg}");
            return true;
        } else {
            $stats['task_failed']++;
            $msg = $result['error'] ?? ($result['data']['metadata']['message'] ?? 'Unknown error');
            $this->line("Failed to send Task ID {$taskId} for: {$kodebooking} - {$msg}");
            return false;
        }
    }

    /**
     * Send Task 99 (Cancel) and update local DB table
     */
    protected function sendCancelTask($patient, string $kodebooking, array &$stats, $referensi = null)
    {
        if ($this->option('dry-run')) {
            $this->line("DRY RUN: Task 99 (BATAL) for: {$kodebooking}");
            return;
        }

        $service = app(MobileJknService::class);
        $nowStr = (string)(now()->timestamp * 1000);

        // 1. Send Task 99 to BPJS API (WITHOUT calling batalAntrean)
        $result = $service->updateTaskId($kodebooking, 99, $nowStr);

        $success = $result['success'] || (
            isset($result['data']['metadata']['message']) &&
            strpos($result['data']['metadata']['message'], 'Ok') !== false
        );

        $ref = $referensi ?: $patient->referensiMobilejknBpjs;

        if ($success) {
            $stats['task_cancelled']++;
            $this->line("Task 99 (BATAL) sent successfully for: {$kodebooking}");

            if ($ref) {
                $ref->update([
                    'status' => 'Batal',
                    'validasi' => now(),
                    'statuskirim' => 'Sudah',
                ]);
            }
        } else {
            $stats['task_failed']++;
            $errorMsg = $result['error'] ?? ($result['data']['metadata']['message'] ?? 'Unknown error');
            $this->line("Failed to send Task 99 for: {$kodebooking} - {$errorMsg}");

            if ($ref) {
                $ref->update([
                    'status' => 'Batal',
                    'statuskirim' => 'Belum',
                ]);
            }
        }
    }

    /**
     * Helper to check if Task 99 has already been sent for a no_rawat
     */
    protected function hasTask99Sent(string $noRawat): bool
    {
        return ReferensiMobilejknBpjsTaskid::where('no_rawat', $noRawat)
            ->where('taskid', '99')
            ->exists();
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
                ['Task ID Success', $stats['task_success']],
                ['Task ID Failed', $stats['task_failed']],
                ['Task 99 Cancelled', $stats['task_cancelled'] ?? 0],
            ]
        );
    }
}
