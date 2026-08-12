<?php
namespace App\Console\Commands;

use App\Models\ReferensiMobilejknBpjs;
use App\Models\RegPeriksa;
use App\Services\MobileJknService;
use Carbon\Carbon;
use Illuminate\Console\Command;

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
        $kdPj             = config('mobilejkn.kd_pj', 'A65');
        $excludePoli      = config('mobilejkn.exclude_poli', 'HD,IGD,IGDK');
        $excludePoliArray = array_filter(explode(',', $excludePoli));

        // Get date range
        $dateFrom = $this->option('date-from') ?: date('Y-m-d');
        $dateTo   = $this->option('date-to') ?: date('Y-m-d');

        $this->info("Processing patients from {$dateFrom} to {$dateTo}");
        $this->info("BPJS Payer Code: {$kdPj}");
        if (! empty($excludePoliArray)) {
            $this->info("Excluding Poli: " . implode(', ', $excludePoliArray));
        }
        if ($this->option('all')) {
            $this->info("Force Resend Mode: Sending all patients again");
        }
        $this->newLine();

        $stats = [
            'processed'      => 0,
            'task_success'   => 0,
            'task_failed'    => 0,
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
            'pemeriksaanRalan',
            'resepObat',
        ])
            ->where('kd_pj', $kdPj)
            ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
            ->whereHas('referensiMobilejknBpjsAll');

        if (! empty($excludePoliArray)) {
            $mjknQuery->whereNotIn('kd_poli', $excludePoliArray);
        }

        if (! $this->option('all')) {
            // Filter: hanya pasien yang referensinya BELUM sukses dibatalkan
            // atau masih berstatus Belum (belum selesai diproses)
            $mjknQuery->where(function ($q) {
                $q->whereHas('referensiMobilejknBpjsAll', function ($subQ) {
                    $subQ->where(function ($sq) {
                        // Referensi masih Belum / Checkin (belum selesai)
                        $sq->where('status', '!=', 'Batal')
                            ->where(function ($kirQ) {
                                $kirQ->where('statuskirim', '!=', 'Sudah')
                                    ->orWhereNull('statuskirim');
                            });
                    })->orWhere(function ($sq) {
                        // Referensi Batal tapi belum berhasil dikirim
                        $sq->where('status', 'Batal')
                            ->where(function ($kirQ) {
                                $kirQ->where('statuskirim', '!=', 'Sudah')
                                    ->orWhereNull('statuskirim');
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
        if (! $this->option('mjkn')) {
            $this->info('--- FASE 2: Memproses Pasien Onsite (Non-MJKN) ---');

            $onsiteQuery = RegPeriksa::with([
                'poliklinik',
                'dokter',
                'pasien',
                'bridgingSep',
                'pemeriksaanRalan',
                'resepObat',
            ])
                ->where('kd_pj', $kdPj)
                ->whereBetween('tgl_registrasi', [$dateFrom, $dateTo])
                ->doesntHave('referensiMobilejknBpjsAll');

            if (! empty($excludePoliArray)) {
                $onsiteQuery->whereNotIn('kd_poli', $excludePoliArray);
            }

            // Tanpa filter skip - semua pasien onsite diproses, BPJS 'sudah ada' menangani duplikasi

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

        $service        = app(MobileJknService::class);
        $hasExamination = $patient->pemeriksaanRalan && $patient->pemeriksaanRalan->isNotEmpty();
        $regStts        = strtolower(trim($patient->stts ?? ''));
        $isBatalInSimrs = $regStts === 'batal';

        $referensiList = $patient->referensiMobilejknBpjsAll;

        if (! $referensiList || $referensiList->isEmpty()) {
            return;
        }

        foreach ($referensiList as $ref) {
            $kodebooking = $ref->nobooking;
            if (! $kodebooking) {
                continue;
            }

            $isRefBatal       = ($ref->status === 'Batal');
            $alreadyCancelled = ($ref->status === 'Batal' && $ref->statuskirim === 'Sudah');

            // CASE 1: SIMRS status is BATAL OR referensi status is BATAL
            if ($isBatalInSimrs || $isRefBatal) {
                if ($alreadyCancelled) {
                    $this->line("Booking {$kodebooking} ({$patient->no_rawat}) is Batal and already sent Task 99 (skipped)");
                    continue;
                }

                $this->sendCancelTask($patient, $kodebooking, $stats, $ref);
                continue;
            }

            // CASE 2: SIMRS and referensi NOT Batal, AND patient HAS examination data -> Send Task 3-7 Normal
            if ($hasExamination) {
                $validasiKosong = (
                    empty($ref->validasi) ||
                    (string) $ref->validasi === '0000-00-00 00:00:00' ||
                    (string) $ref->validasi === '-0001-11-30 00:00:00' ||
                    strpos((string) $ref->validasi, '0000-') !== false
                );

                // Auto-checkin if not checked in yet
                if ($validasiKosong) {
                    $ts4 = $service->getTaskTimestampFromDatabase($kodebooking, 4);
                    if ($ts4 && (int) $ts4 > 0) {
                        $offset    = random_int(5, 10);
                        $ts3       = (string) ((int) $ts4 - ($offset * 60 * 1000));
                        $checkinDt = Carbon::createFromTimestampMs((int) $ts3)->setTimezone('Asia/Jakarta');

                        if (! $this->option('dry-run')) {
                            $ref->update([
                                'status'   => 'Checkin',
                                'validasi' => $checkinDt->format('Y-m-d H:i:s'),
                            ]);
                        }
                        $this->info("Auto-checkin MJKN: {$patient->no_rawat} ({$kodebooking}) → validasi={$checkinDt->format('Y-m-d H:i:s')}");
                    }
                }

                // Send normal task IDs (3-7) for THIS specific kodebooking
                $this->sendTaskIds($kodebooking, $patient, $stats);
                continue;
            }

            // CASE 3: Active referensi but NO examination data & validasi is empty -> Pasien tidak datang -> Task 99
            $validasiKosong = (
                empty($ref->validasi) ||
                (string) $ref->validasi === '0000-00-00 00:00:00' ||
                (string) $ref->validasi === '-0001-11-30 00:00:00' ||
                strpos((string) $ref->validasi, '0000-') !== false
            );

            if ($validasiKosong) {
                if ($alreadyCancelled) {
                    $this->line("Booking {$kodebooking} ({$patient->no_rawat}) no checkin/exam and already cancelled (skipped)");
                    continue;
                }
                $this->sendCancelTask($patient, $kodebooking, $stats, $ref);
                continue;
            }

            // CASE 4: Checked in but no exam yet -> waiting for examination
            $this->line("Booking {$kodebooking} ({$patient->no_rawat}) checked in, waiting for examination (skipped)");
        }
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
            ? $patient->referensiMobilejknBpjsTaskid->pluck('taskid')->map(fn($id) => (int) $id)->toArray()
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
                if (! $this->option('dry-run')) {
                    $patient->update(['stts' => 'Sudah']);
                }
                $this->info("Auto-update reg_periksa.stts: {$patient->no_rawat} Belum → Sudah");
            }

            // B2: Cek kelengkapan task ID
            $expectedTasks = $hasResep ? [3, 4, 5, 6, 7] : [3, 4, 5];
            $allComplete   = count(array_diff($expectedTasks, $existingTaskIds)) === 0;

            if (! $this->option('all') && $allComplete) {
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
            if (! $this->option('dry-run')) {
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
        if ($this->option('all') || ! in_array(3, $existingTaskIds)) {
            $ts3 = $service->getTaskTimestampFromDatabase($kodebooking, 3);
            if ($ts3 === null || (int) $ts3 < 1609459200000) {
                // FALLBACK: Ambil waktu Task 4 - random 5-10 menit
                $ts4 = $service->getTaskTimestampFromDatabase($kodebooking, 4);
                if ($ts4 && (int) $ts4 >= 1609459200000) {
                    $offset = random_int(5, 10);
                    $ts3    = (string) ((int) $ts4 - ($offset * 60 * 1000));
                    $this->info("Task 3 fallback generated: {$kodebooking} → T4 - {$offset}m");
                }
            }

            if ($ts3 === null || (int) $ts3 < 1609459200000) {
                $this->warn("Task 3: no valid timestamp for {$kodebooking} → STOPPING task send");
                return;
            }

            if (! $this->sendSingleTaskId($kodebooking, 3, $ts3, $stats, $dryRun)) {
                if (! $dryRun) {
                    return;
                }
                // jika gagal kirim Task 3, stop
            }
        }

        // ── TASK 4 ──
        if ($this->option('all') || ! in_array(4, $existingTaskIds)) {
            $ts4 = $service->getTaskTimestampFromDatabase($kodebooking, 4);
            if ($ts4 === null || (int) $ts4 < 1609459200000) {
                $this->warn("Task 4: no examination timestamp for {$kodebooking} → sending Task 99");
                $this->sendCancelTask($patient, $kodebooking, $stats);
                return;
            }

            if (! $this->sendSingleTaskId($kodebooking, 4, $ts4, $stats, $dryRun)) {
                if (! $dryRun) {
                    return;
                }

            }
        }

        // ── TASK 5 ──
        if ($this->option('all') || ! in_array(5, $existingTaskIds)) {
            $ts5 = $service->getTaskTimestampFromDatabase($kodebooking, 5);
            if ($ts5 === null || (int) $ts5 < 1609459200000) {
                // FALLBACK: Ambil waktu Task 4 + random 5-10 menit
                $ts4Sent = $service->getTaskTimestampFromDatabase($kodebooking, 4);
                if ($ts4Sent && (int) $ts4Sent > 0) {
                    $offset = random_int(5, 10);
                    $ts5    = (string) ((int) $ts4Sent + ($offset * 60 * 1000));
                    $this->info("Task 5 fallback generated: {$kodebooking} → T4 + {$offset}m");
                }
            }

            if ($ts5 && (int) $ts5 >= 1609459200000) {
                $this->sendSingleTaskId($kodebooking, 5, $ts5, $stats, $dryRun);
            }
        }

        // ── TASK 6 ──
        $ts6 = null;
        if ($this->option('all') || ! in_array(6, $existingTaskIds)) {
            $ts6 = $service->getTaskTimestampFromDatabase($kodebooking, 6);
            if ($ts6 === null || (int) $ts6 < 1609459200000) {
                // TIDAK ADA RESEP → STOP DI SINI (tidak boleh fallback Task 6)
                $this->line("Task 6: no prescription for {$kodebooking} → stopping at Task 5 (normal)");
                return;
            }

            if (! $this->sendSingleTaskId($kodebooking, 6, $ts6, $stats, $dryRun)) {
                if (! $dryRun) {
                    return;
                }

            }
        } else {
            // Task 6 sudah terkirim sebelumnya, ambil waktu dari DB untuk fallback Task 7
            $ts6 = $service->getTaskTimestampFromDatabase($kodebooking, 6);
        }

        // ── TASK 7 ──
        if ($this->option('all') || ! in_array(7, $existingTaskIds)) {
            $ts7 = $service->getTaskTimestampFromDatabase($kodebooking, 7);
            if ($ts7 === null || (int) $ts7 < 1609459200000) {
                                     // FALLBACK: Gunakan waktu T6 (in-memory) + random 5-10 menit
                $ts6Fallback = $ts6; // $ts6 dari blok Task 6 di atas

                if (! $ts6Fallback || (int) $ts6Fallback < 1609459200000) {
                    // Jika $ts6 juga null, coba ambil ulang dari DB
                    $ts6Fallback = $service->getTaskTimestampFromDatabase($kodebooking, 6);
                }

                if ($ts6Fallback && (int) $ts6Fallback >= 1609459200000) {
                    $offset = random_int(5, 10);
                    $ts7    = (string) ((int) $ts6Fallback + ($offset * 60 * 1000));
                    $this->info("Task 7 fallback generated: {$kodebooking} → T6 + {$offset}m");
                } else {
                    $this->line("Task 7: no T6 timestamp available for {$kodebooking} → skipping Task 7");
                }
            }

            if ($ts7 && (int) $ts7 >= 1609459200000) {
                $this->sendSingleTaskId($kodebooking, 7, $ts7, $stats, $dryRun);
            }
        }

        // Refresh task_data cache from BPJS for this patient after sending task IDs
        if (! $dryRun) {
            try {
                $service->getListTask($kodebooking);
            } catch (\Throwable $e) {
                // Ignore cache refresh errors during command execution
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
        $result  = $service->updateTaskId($kodebooking, $taskId, $waktu);

        $metaMsg = $result['data']['metadata']['message'] ?? ($result['metadata']['message'] ?? '');

        // "sudah ada" = Task sudah pernah terkirim ke BPJS → anggap sukses
        $isSudahAda = is_string($metaMsg) && strpos($metaMsg, 'sudah ada') !== false;

        if ($result['success'] || $isSudahAda) {
            $stats['task_success']++;
            $label = $isSudahAda ? '(already exists)' : 'Ok';
            $this->line("Task ID {$taskId} sent for: {$kodebooking} - {$label}");
            return true;
        } else {
            $stats['task_failed']++;
            $msg = $result['error'] ?? ($metaMsg ?: 'Unknown error');
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
        $nowStr  = (string) (now()->timestamp * 1000);

        // 1. Send Task 99 to BPJS API (WITHOUT calling batalAntrean)
        $result = $service->updateTaskId($kodebooking, 99, $nowStr);

        $metaMsg      = $result['data']['metadata']['message'] ?? ($result['metadata']['message'] ?? '');
        $isAcceptable = is_string($metaMsg) && (
            strpos($metaMsg, 'Ok') !== false ||
            strpos($metaMsg, 'sudah ada') !== false ||
            strpos(strtolower($metaMsg), 'batal') !== false ||
            strpos($metaMsg, 'tidak ditemukan') !== false
        );

        $success = $result['success'] || $isAcceptable;

        $ref = $referensi ?: $patient->referensiMobilejknBpjs;

        // Auto update reg_periksa status to Batal if currently Belum
        if (strtolower(trim($patient->stts ?? '')) === 'belum') {
            $patient->update(['stts' => 'Batal']);
            $this->info("Auto-update reg_periksa.stts: {$patient->no_rawat} Belum → Batal");
        }

        if ($success) {
            $stats['task_cancelled']++;
            $this->line("Task 99 (BATAL) sent successfully for: {$kodebooking}");

            if ($ref) {
                $validasiVal = ($ref->validasi && strpos((string) $ref->validasi, '0000-') === false && (string) $ref->validasi !== '-0001-11-30 00:00:00')
                    ? $ref->validasi
                    : now();

                $ref->update([
                    'status'      => 'Batal',
                    'validasi'    => $validasiVal,
                    'statuskirim' => 'Sudah',
                ]);
            }
        } else {
            $stats['task_failed']++;
            $errorMsg = $result['error'] ?? ($metaMsg ?: 'Unknown error');
            $this->line("Failed to send Task 99 for: {$kodebooking} - {$errorMsg}");

            if ($ref) {
                $validasiVal = ($ref->validasi && strpos((string) $ref->validasi, '0000-') === false && (string) $ref->validasi !== '-0001-11-30 00:00:00')
                    ? $ref->validasi
                    : now();

                $ref->update([
                    'status'      => 'Batal',
                    'validasi'    => $validasiVal,
                    'statuskirim' => 'Belum',
                ]);
            }
        }

        try {
            $service->getListTask($kodebooking);
        } catch (\Throwable $e) {
            // Ignore cache refresh errors
        }
    }

    /**
     * Check if referensi_mobilejkn_bpjs already has status=Batal + statuskirim=Sudah
     * (meaning Task 99 was already successfully sent and referensi was updated)
     */
    protected function isReferensiAlreadyCancelled($patient): bool
    {
        $refs = $patient->referensiMobilejknBpjsAll;
        if (! $refs || $refs->isEmpty()) {
            return false;
        }

        foreach ($refs as $ref) {
            if ($ref->status === 'Batal' && $ref->statuskirim === 'Sudah') {
                return true;
            }
        }

        return false;
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
