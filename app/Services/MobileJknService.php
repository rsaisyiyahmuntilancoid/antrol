<?php

namespace App\Services;

use App\Models\BpjsDashboardWaktutunggu;
use App\Models\BpjsPatientVisit;
use App\Models\Jadwal;
use App\Models\MapingDokterDpjpvclaim;
use App\Models\MapingPoliBpjs;
use App\Models\Pasien;
use App\Models\PemeriksaanRalan;
use App\Models\ReferensiMobilejknBpjs;
use App\Models\ReferensiMobilejknBpjsTaskid;
use App\Models\RegPeriksa;
use App\Models\ResepObat;
use App\Services\BpjsLogService;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class MobileJknService
{
    protected $baseUrl;
    protected $baseUrlVclaim = 'https://apijkn.bpjs-kesehatan.go.id/vclaim-rest';
    protected $consId;
    protected $userKey;
    protected $secretKey;
    protected $bpjsLogService;

    public function __construct(BpjsLogService $bpjsLogService)
    {
        // You should configure these in your .env file or config
        $this->baseUrl = config('mobilejkn.base_url', 'https://apijkn.bpjs-kesehatan.go.id/antreanrs');
        $this->consId = config('mobilejkn.cons_id');
        $this->userKey = config('mobilejkn.user_key');
        $this->secretKey = config('mobilejkn.secret_key');
        $this->bpjsLogService = $bpjsLogService;
    }

    /**
     * Decrypt string using AES-256-CBC
     *
     * @param string $key The key to use for decryption
     * @param string $string The string to decrypt
     * @return string Decrypted string
     */
    protected function stringDecrypt($key, $string)
    {
        $encrypt_method = 'AES-256-CBC';

        // hash
        $key_hash = hex2bin(hash('sha256', $key));

        // iv - encrypt method AES-256-CBC expects 16 bytes - else you will get a warning
        $iv = substr(hex2bin(hash('sha256', $key)), 0, 16);

        $output = openssl_decrypt(base64_decode($string), $encrypt_method, $key_hash, OPENSSL_RAW_DATA, $iv);

        return $output;
    }

    /**
     * Generate decryption key for BPJS responses
     *
     * @param string $timestamp Timestamp from the request
     * @return string Decryption key
     */
    protected function generateDecryptionKey(string $timestamp): string
    {
        // Key is consid + secretkey + timestamp
        return $this->consId . $this->secretKey . $timestamp;
    }

    /**
     * Decompress string using LZString library
     *
     * @param string $string The string to decompress
     * @return string Decompressed string
     */
    protected function decompress($string)
    {
        return \LZCompressor\LZString::decompressFromEncodedURIComponent($string);
    }

    /**
     * Generate signature for authentication
     *
     * @param string $timestamp
     * @return string
     */
    protected function generateSignature(string $timestamp): string
    {
        $data = $this->consId . '&' . $timestamp;
        return hash_hmac('sha256', $data, $this->secretKey, true);
    }

    /**
     * Get the proper kode booking for a patient registration.
     * If patient is on referensi pendaftaran mobile jkn, use nobooking.
     * If patient is only on reg periksa, use no_rawat.
     *
     * @param string $noRawat The registration number
     * @return string The booking code (nobooking or no_rawat)
     */
    public function getKodeBooking(string $noRawat): string
    {
        // Try to find if patient has a referensi (referensi pendaftaran mobile jkn)
        $referensi = ReferensiMobilejknBpjs::where('no_rawat', $noRawat)->first();

        // If referensi exists, use nobooking, otherwise use no_rawat
        return $referensi ? $referensi->nobooking : $noRawat;
    }


    /**
     * Get timestamp for a specific task ID from database
     * Ensures the returned time is within the service date
     *
     * @param string $kodebooking
     * @param int $taskid
     * @return string|null Timestamp in milliseconds or null if not found
     */
    public function getTaskTimestampFromDatabase(string $kodebooking, int $taskid): ?string
    {
        try {
            $rawTimestamp = null;
            switch ($taskid) {
                case 3:
                    $rawTimestamp = $this->getTask3Timestamp($kodebooking);
                    break;
                case 4:
                    $rawTimestamp = $this->getTask4Timestamp($kodebooking);
                    break;
                case 5:
                    $rawTimestamp = $this->getTask5Timestamp($kodebooking);
                    break;
                case 6:
                    $rawTimestamp = $this->getTask6Timestamp($kodebooking);
                    break;
                case 7:
                    $rawTimestamp = $this->getTask7Timestamp($kodebooking);
                    break;
                case 99:
                    $rawTimestamp = (string) ((int) (now()->timestamp * 1000));
                    break;
                default:
                    return null;
            }

            // If we got a timestamp, ensure it's within the service date
            if ($rawTimestamp !== null) {
                $serviceDate = $this->getServiceDate($kodebooking);
                if ($serviceDate) {
                    $adjustedTimestamp = $this->ensureTimeWithinServiceDate((int)$rawTimestamp, $serviceDate);
                    Log::debug('Task timestamp validated and adjusted if needed', [
                        'kodebooking' => $kodebooking,
                        'taskid' => $taskid,
                        'original' => $rawTimestamp,
                        'adjusted' => $adjustedTimestamp
                    ]);
                    return (string)$adjustedTimestamp;
                }
            }

            return $rawTimestamp;
        } catch (Exception $e) {
            Log::error('Error getting task timestamp from database', [
                'kodebooking' => $kodebooking,
                'taskid' => $taskid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Get task 3 timestamp - from referensi_mobilejkn_bpjs validasi or reg_periksa jam_reg
     */
    protected function getTask3Timestamp(string $kodebooking): ?string
    {
        // First try to get from referensi_mobilejkn_bpjs
        $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();

        if ($referensi && $referensi->validasi && (int)$referensi->validasi->format('Y') > 1970) {
            return $referensi->validasi->timestamp * 1000;
        }

        // If not found, get from reg_periksa jam_reg
        $regPeriksa = RegPeriksa::where('no_rawat', $referensi ? $referensi->no_rawat : $kodebooking)->first();

        if ($regPeriksa && $regPeriksa->jam_reg) {
            $waktuReg =  Carbon::parse(str_replace(' 00:00:00', '', $regPeriksa->tgl_registrasi) . ' ' . $regPeriksa->jam_reg->toTimeString(), 'Asia/Jakarta');
            return $waktuReg->timestamp * 1000;
        }

        return null;
    }

    /**
     * Get task 4 timestamp - from pemeriksaan_ralan where nip is in petugas
     */
    protected function getTask4Timestamp(string $kodebooking): ?string
    {
        $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        if ($referensi) $kodebooking = $referensi->no_rawat;

        // Get service date to ensure times are on the correct date
        $serviceDate = $this->getServiceDate($kodebooking);

        $pemeriksaan = PemeriksaanRalan::where('no_rawat', $kodebooking)
        ->whereHas('petugas') // nip exists in petugas table
        ->orderBy('jam_rawat', 'asc')
        ->first();

        if ($pemeriksaan && $pemeriksaan->jam_rawat) {
            // Use service date for the date part, just the time from jam_rawat
            $datePart = $serviceDate ? $serviceDate->toDateString() : (str_replace(' 00:00:00', '', $pemeriksaan->tgl_perawatan));
            $waktu = Carbon::parse($datePart . ' ' . $pemeriksaan->jam_rawat->toTimeString(), 'Asia/Jakarta');
            return (string) ($waktu->timestamp * 1000);
        } else {
            $pemeriksaan = PemeriksaanRalan::where('no_rawat', $kodebooking)
                ->orderBy('jam_rawat', 'asc')
                ->first();

            if ($pemeriksaan && $pemeriksaan->jam_rawat) {
                $datePart = $serviceDate ? $serviceDate->toDateString() : (str_replace(' 00:00:00', '', $pemeriksaan->tgl_perawatan));
                $waktu = Carbon::parse($datePart . ' ' . $pemeriksaan->jam_rawat->toTimeString(), 'Asia/Jakarta');
                try {
                    $offsetMinutes = random_int(5, 10);
                } catch (Throwable $e) {
                    $offsetMinutes = rand(5, 10);
                }
                $waktu = $waktu->copy()->addMinutes($offsetMinutes);
                return (string) ($waktu->timestamp * 1000);
            }
        }

        // Fallback: use task 3 time + 5-10 minutes if task 4 not available
        $task3Time = $this->getTask3Timestamp($kodebooking);
        if ($task3Time) {
            try {
                $offsetMinutes = random_int(5, 10);
            } catch (Throwable $e) {
                $offsetMinutes = rand(5, 10);
            }
            $waktu = Carbon::createFromTimestamp((int)($task3Time / 1000));
            $waktu = $waktu->copy()->addMinutes($offsetMinutes);
            return (string) ($waktu->timestamp * 1000);
        }

        return null;
    }

    /**
     * Get task 5 timestamp - from pemeriksaan_ralan where nip is in dokter
     * If not available or on wrong date, fallback to task 4 time + 5-10 minutes
     */
    protected function getTask5Timestamp(string $kodebooking): ?string
    {
        $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        if ($referensi) $kodebooking = $referensi->no_rawat;

        // Get service date to ensure times are on the correct date
        $serviceDate = $this->getServiceDate($kodebooking);

        $pemeriksaan = PemeriksaanRalan::where('no_rawat', $kodebooking)
        ->whereHas('dokter') // nip exists in dokter table
        ->orderBy('jam_rawat', 'desc')
        ->first();

        if ($pemeriksaan && $pemeriksaan->jam_rawat) {
            // Use service date for the date part, just the time from jam_rawat
            $datePart = $serviceDate ? $serviceDate->toDateString() : (str_replace(' 00:00:00', '', $pemeriksaan->tgl_perawatan));
            $waktu = Carbon::parse($datePart . ' ' . $pemeriksaan->jam_rawat->toTimeString(), 'Asia/Jakarta');

            // Validate that the time is on the correct service date
            if ($serviceDate && $waktu->toDateString() !== $serviceDate->toDateString()) {
                Log::warning('Task 5: Timestamp date mismatch, using fallback', [
                    'kodebooking' => $kodebooking,
                    'calculated_date' => $waktu->toDateString(),
                    'service_date' => $serviceDate->toDateString()
                ]);
                // Fall through to use task 4 + offset
            } else {
                return (string) ($waktu->timestamp * 1000);
            }
        } else {
            $pemeriksaan = PemeriksaanRalan::where('no_rawat', $kodebooking)
                ->orderBy('jam_rawat', 'desc')
                ->first();

            if ($pemeriksaan && $pemeriksaan->jam_rawat) {
                $datePart = $serviceDate ? $serviceDate->toDateString() : (str_replace(' 00:00:00', '', $pemeriksaan->tgl_perawatan));
                $waktu = Carbon::parse($datePart . ' ' . $pemeriksaan->jam_rawat->toTimeString(), 'Asia/Jakarta');

                // Validate that the time is on the correct service date
                if ($serviceDate && $waktu->toDateString() !== $serviceDate->toDateString()) {
                    Log::warning('Task 5 (alt): Timestamp date mismatch, using fallback', [
                        'kodebooking' => $kodebooking,
                        'calculated_date' => $waktu->toDateString(),
                        'service_date' => $serviceDate->toDateString()
                    ]);
                    // Fall through to use task 4 + offset
                } else {
                    try {
                        $offsetMinutes = random_int(5, 10);
                    } catch (Throwable $e) {
                        $offsetMinutes = rand(5, 10);
                    }
                    $waktu = $waktu->copy()->addMinutes($offsetMinutes);
                    return (string) ($waktu->timestamp * 1000);
                }
            }
        }

        // Fallback: use task 4 time + 5-10 minutes if task 5 not available or date mismatch
        $task4Time = $this->getTask4Timestamp($kodebooking);
        if ($task4Time) {
            try {
                $offsetMinutes = random_int(5, 10);
            } catch (Throwable $e) {
                $offsetMinutes = rand(5, 10);
            }
            $waktu = Carbon::createFromTimestamp((int)($task4Time / 1000));
            $waktu = $waktu->copy()->addMinutes($offsetMinutes);
            return (string) ($waktu->timestamp * 1000);
        }

        return null;
    }

    /**
     * Get task 6 timestamp - from resep_obat jam
     * If not available or on wrong date, fallback to task 4 time + 5-10 minutes
     */
    protected function getTask6Timestamp(string $kodebooking): ?string
    {
        $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        if ($referensi) $kodebooking = $referensi->no_rawat;

        // Get service date to ensure times are on the correct date
        $serviceDate = $this->getServiceDate($kodebooking);

        $resep = ResepObat::where('no_rawat', $kodebooking)
            ->orderBy('jam', 'desc')
            ->first();

        if ($resep && $resep->jam) {
            // Use service date for the date part, just the time from jam
            $datePart = $serviceDate ? $serviceDate->toDateString() : (str_replace(' 00:00:00', '', $resep->tgl_perawatan));
            $waktu = Carbon::parse($datePart . ' ' . $resep->jam->toTimeString(), 'Asia/Jakarta');

            // Validate that the time is on the correct service date
            if ($serviceDate && $waktu->toDateString() !== $serviceDate->toDateString()) {
                Log::warning('Task 6: Timestamp date mismatch, using fallback', [
                    'kodebooking' => $kodebooking,
                    'calculated_date' => $waktu->toDateString(),
                    'service_date' => $serviceDate->toDateString()
                ]);
                // Fall through to use task 4 + offset
            } else {
                return (string) ($waktu->timestamp * 1000);
            }
        }

        // Fallback: use task 4 time + 5-10 minutes if task 6 not available or date mismatch
        $task4Time = $this->getTask4Timestamp($kodebooking);
        if ($task4Time) {
            try {
                $offsetMinutes = random_int(5, 10);
            } catch (Throwable $e) {
                $offsetMinutes = rand(5, 10);
            }
            $waktu = Carbon::createFromTimestamp((int)($task4Time / 1000));
            $waktu = $waktu->copy()->addMinutes($offsetMinutes);
            return (string) ($waktu->timestamp * 1000);
        }

        return null;
    }

    /**
     * Get task 7 timestamp - from resep_obat jam_penyerahan
     */
    protected function getTask7Timestamp(string $kodebooking): ?string
    {
        $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        if ($referensi) $kodebooking = $referensi->no_rawat;

        // Get service date to ensure times are on the correct date
        $serviceDate = $this->getServiceDate($kodebooking);

        $resep = ResepObat::where('no_rawat', $kodebooking)
        ->orderBy('jam_penyerahan', 'desc')
        ->first();

        if ($resep && $resep->jam_penyerahan) {
            // Use service date for the date part, just the time from jam_penyerahan
            $datePart = $serviceDate ? $serviceDate->toDateString() : (str_replace(' 00:00:00', '', $resep->tgl_penyerahan));
            $waktu = Carbon::parse($datePart . ' ' . $resep->jam_penyerahan->toTimeString(), 'Asia/Jakarta');
            return (string) ($waktu->timestamp * 1000);
        }

        return null;
    }

    /**
     * Get time for previous task ID if exists
     *
     * @param string $kodebooking
     * @param int $taskid
     * @return int|null Previous task time in milliseconds
     */
    private function getPreviousTaskTime(string $kodebooking, int $taskid): ?int
    {
        if ($taskid <= 1) return null;

        $previousTaskId = $taskid - 1;
        $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
        $noRawat = $referensi ? $referensi->no_rawat : $kodebooking;

        $previousTask = ReferensiMobilejknBpjsTaskid::where('no_rawat', $noRawat)
            ->where('taskid', (string) $previousTaskId)
            ->first();

        if ($previousTask && $previousTask->waktu) {
            $previousTime = Carbon::parse($previousTask->waktu, 'Asia/Jakarta');
            return (int)($previousTime->timestamp * 1000);
        }

        return null;
    }

    /**
     * Get service date for a booking
     *
     * @param string $kodebooking
     * @return Carbon|null Service date
     */
    /**
     * Get service date for a booking
     * Extracts from kodebooking format (YYYY/MM/DD/XXXX) or database
     *
     * @param string $kodebooking
     * @return Carbon|null Service date
     */
    private function getServiceDate(string $kodebooking): ?Carbon
    {
        try {
            // First priority: Try to get from database (registration date)
            $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();

            if ($referensi && $referensi->no_rawat) {
                $regPeriksa = RegPeriksa::where('no_rawat', $referensi->no_rawat)->first();

                if ($regPeriksa && $regPeriksa->tgl_registrasi) {
                    $serviceDate = Carbon::parse($regPeriksa->tgl_registrasi, 'Asia/Jakarta');
                    Log::debug('Service date from reg_periksa (by nobooking)', [
                        'kodebooking' => $kodebooking,
                        'no_rawat' => $referensi->no_rawat,
                        'service_date' => $serviceDate
                    ]);
                    return $serviceDate->startOfDay();
                }
            }

            // Second priority: Try to get from reg_periksa directly using kodebooking as no_rawat
            $regPeriksa = RegPeriksa::where('no_rawat', $kodebooking)->first();
            if ($regPeriksa && $regPeriksa->tgl_registrasi) {
                $serviceDate = Carbon::parse($regPeriksa->tgl_registrasi, 'Asia/Jakarta');
                Log::debug('Service date from reg_periksa (direct)', [
                    'kodebooking' => $kodebooking,
                    'service_date' => $serviceDate
                ]);
                return $serviceDate->startOfDay();
            }
        } catch (Exception $e) {
            Log::debug('Failed to get service date from database', ['error' => $e->getMessage()]);
        }

        // Fallback: Extract date from kodebooking format (YYYY/MM/DD/...)
        try {
            $parts = explode('/', $kodebooking);
            if (count($parts) >= 3) {
                $year = $parts[0];
                $month = $parts[1];
                $day = $parts[2];

                if (is_numeric($year) && is_numeric($month) && is_numeric($day)) {
                    $serviceDate = Carbon::createFromDate($year, $month, $day, 'Asia/Jakarta');
                    Log::debug('Service date extracted from kodebooking (fallback)', [
                        'kodebooking' => $kodebooking,
                        'service_date' => $serviceDate
                    ]);
                    return $serviceDate->startOfDay();
                }
            }
        } catch (Exception $e) {
            Log::debug('Failed to extract service date from kodebooking', ['error' => $e->getMessage()]);
        }

        return null;
    }

    /**
     * Ensure time stays within the service date (same day)
     *
     * @param int $waktuMs Waktu in milliseconds
     * @param Carbon $serviceDate The service date
     * @return int Adjusted waktu (capped to end of service date if needed)
     */
    private function ensureTimeWithinServiceDate(int $waktuMs, Carbon $serviceDate): int
    {
        // Use proper timezone handling on Carbon instances instead of changing global state
        $localTz = config('app.timezone', 'Asia/Jakarta');

        $waktuTime = Carbon::createFromTimestampMs($waktuMs)->setTimezone($localTz);
        $serviceDateStart = $serviceDate->copy()->startOfDay()->setTimezone($localTz);
        $serviceDateEnd = $serviceDate->copy()->endOfDay()->setTimezone($localTz);

        Log::debug('Time validation check', [
            'waktu_time' => $waktuTime->toDateTimeString(),
            'service_date_start' => $serviceDateStart->toDateTimeString(),
            'service_date_end' => $serviceDateEnd->toDateTimeString()
        ]);

        // If time is before service date, set to start of service date
        if ($waktuTime->isBefore($serviceDateStart)) {
            Log::warning('Time is before service date, adjusting to service date start', [
                'original_time' => $waktuTime->toDateTimeString(),
                'service_date' => $serviceDateStart->toDateTimeString(),
                'adjusted_to' => $serviceDateStart->toDateTimeString()
            ]);
            return (int)($serviceDateStart->timestamp * 1000);
        }

        // If time is after service date, set to 23:59:59 of service date (not quite end of day)
        if ($waktuTime->isAfter($serviceDateEnd)) {
            $cappedTime = $serviceDate->copy()->setTime(23, 59, 59)->setTimezone($localTz);
            Log::warning('Time is after service date, adjusting to end of service date', [
                'original_time' => $waktuTime->toDateTimeString(),
                'service_date' => $serviceDateEnd->toDateTimeString(),
                'adjusted_to' => $cappedTime->toDateTimeString()
            ]);
            return (int)($cappedTime->timestamp * 1000);
        }

        return $waktuMs;
    }

    /**
     * Adjust waktu to be at least 10 minutes after previous task time if needed
     *
     * @param int $currentWaktu Current waktu in milliseconds
     * @param int $previousWaktu Previous task waktu in milliseconds
     * @param Carbon|null $serviceDate Service date limit (for boundary checking)
     * @return int Adjusted waktu (at least 10 minutes after previous, but within service date)
     */
    private function ensureTimeAfterPrevious(int $currentWaktu, int $previousWaktu, ?Carbon $serviceDate = null): int
    {
        $localTz = config('app.timezone', 'Asia/Jakarta');

        $minTimeAfterPrevious = $previousWaktu + (10 * 60 * 1000); // 10 minutes in milliseconds

        // Check if the time is within service date if provided
        if ($serviceDate) {
            $serviceDateEnd = $serviceDate->copy()->setTime(23, 59, 59)->setTimezone($localTz);
            $serviceDateEndMs = (int)($serviceDateEnd->timestamp * 1000);

            // If adding 10 minutes would exceed service date, cap it to end of day minus 1 minute
            if ($minTimeAfterPrevious > $serviceDateEndMs) {
                $cappedTime = $serviceDateEnd->copy()->subMinute();
                $cappedMs = (int)($cappedTime->timestamp * 1000);

                Log::warning('Adding 10 minutes exceeds service date, capping to 23:58:59', [
                    'previous_waktu' => $previousWaktu,
                    'would_be' => $minTimeAfterPrevious,
                    'service_date_end' => $serviceDateEndMs,
                    'adjusted_to' => $cappedMs
                ]);
                return $cappedMs;
            }
        }

        if ($currentWaktu <= $previousWaktu) {
            Log::info('Time adjustment needed', [
                'current_waktu' => $currentWaktu,
                'previous_waktu' => $previousWaktu,
                'adjusted_to' => $minTimeAfterPrevious
            ]);
            return $minTimeAfterPrevious;
        }

        return $currentWaktu;
    }

    /**
     * Adjust waktu to be at least 10 minutes after previous task time if needed
     *
     * @param int $currentWaktu Current waktu in milliseconds
     * @param int $previousWaktu Previous task waktu in milliseconds
     * @return int Adjusted waktu (at least 10 minutes after previous)
     */
    private function ensureTimeAfterPreviousSimple(int $currentWaktu, int $previousWaktu): int
    {
        $minTimeAfterPrevious = $previousWaktu + (10 * 60 * 1000); // 10 minutes in milliseconds

        if ($currentWaktu <= $previousWaktu) {
            Log::info('Time adjustment needed', [
                'current_waktu' => $currentWaktu,
                'previous_waktu' => $previousWaktu,
                'adjusted_to' => $minTimeAfterPrevious
            ]);
            return $minTimeAfterPrevious;
        }

        return $currentWaktu;
    }

    /**
     * Send task ID update to Mobile JKN API
     *
     * @param string $kodebooking
     * @param int $taskid (1,2,3,4,5,6,7,99)
     * @param string|null $waktu Timestamp in milliseconds, if null will get from database
     * @return array
     */
    public function updateTaskId(string $kodebooking, int $taskid, ?string $waktu = null, int $retryCount = 0, ?string $lastBpjsError = null): array
    {
        // Prevent infinite recursion
        if ($retryCount > 3) {
            Log::error('Max retry count exceeded', [
                'kodebooking' => $kodebooking,
                'taskid' => $taskid,
                'retry_count' => $retryCount,
                'last_bpjs_error' => $lastBpjsError
            ]);
            return [
                'success' => false,
                'error' => $lastBpjsError ?? 'Max retry attempts exceeded',
                'status_code' => 500
            ];
        }

        try {
            // Validate taskid
            if (!in_array($taskid, [1, 2, 3, 4, 5, 6, 7, 99])) {
                throw new InvalidArgumentException('Invalid taskid. Must be one of: 1,2,3,4,5,6,7,99');
            }

            // If waktu is not provided, get from database
            if ($waktu === null) {
                $waktu = $this->getTaskTimestampFromDatabase($kodebooking, $taskid);

                if ($waktu === null) {
                    throw new Exception("Could not find timestamp for task ID {$taskid} in database");
                }
            }

            // Get service date for boundary validation
            $serviceDate = $this->getServiceDate($kodebooking);

            // Convert waktu to int for processing
            $waktuInt = (int)$waktu;

            // Validate waktu is reasonable (not corrupted negative values or extremely old times)
            if ($waktuInt < 0 || $waktuInt < 1609459200000) { // Before Jan 1, 2021
                Log::warning('Invalid waktu value detected, using current time within service date', [
                    'kodebooking' => $kodebooking,
                    'taskid' => $taskid,
                    'invalid_waktu' => $waktuInt
                ]);

                // Use current time if within service date, otherwise use end of service date
                if ($serviceDate) {
                    $now = Carbon::now('Asia/Jakarta')->timestamp * 1000;
                    $serviceDateEnd = $serviceDate->copy()->setTime(23, 59, 59)->timestamp * 1000;
                    $waktuInt = min($now, $serviceDateEnd);
                } else {
                    $waktuInt = (int)(Carbon::now('Asia/Jakarta')->timestamp * 1000);
                }
            }

            // Ensure time is within service date first
            if ($serviceDate) {
                $waktuInt = $this->ensureTimeWithinServiceDate($waktuInt, $serviceDate);
            }

            // Check if we need to adjust the time to be after the previous task
            $previousWaktu = $this->getPreviousTaskTime($kodebooking, $taskid);
            if ($previousWaktu !== null && $previousWaktu > 0) {
                $waktuInt = $this->ensureTimeAfterPrevious($waktuInt, $previousWaktu, $serviceDate);
                $waktu = (string)$waktuInt;
            } else {
                $waktu = (string)$waktuInt;
            }

            // Generate timestamp and signature
            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            Log::info($waktu);
            // Prepare request data
            $requestData = [
                'kodebooking' => $kodebooking,
                'taskid' => (string) $taskid,
                'waktu' => (int) $waktu
            ];

            // Log the request
            Log::info('Mobile JKN Task Update Request', [
                'kodebooking' => $kodebooking,
                'taskid' => $taskid,
                'waktu' => (int) $waktu,
                'timestamp' => (int) $timestamp
            ]);

            $batal = '';
            // if ($taskid == 99) {
            //     $refBatal = ReferensiMobilejknBpjsBatal::where('nobooking', $kodebooking)->first();
            //     $batal = $this->batalAntrean($kodebooking, $refBatal ? $refBatal->keterangan : 'Batal.');
            // }
            if ($taskid == 99) {
                $batal = $this->batalAntrean($kodebooking, 'Dibatalkan Oleh Admin');
            }

            // Make HTTP request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-cons-id' => $this->consId,
                'X-timestamp' => $timestamp,
                'X-signature' => $signature,
                'user_key' => $this->userKey,
            ])->post($this->baseUrl . '/antrean/updatewaktu', $requestData);

            // Parse response
            $responseData = $response->json();

            // Log to BPJS log database
            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode($requestData),
                json_encode($responseData),
                $this->baseUrl . '/antrean/updatewaktu',
                'POST'
            );

            // Log the response
            Log::info('Mobile JKN Task Update Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            $metaMessage = $responseData['metadata']['message'] ?? ($responseData['metadata'] ?? null);

            // Handle "time cannot be less than or equal to previous time" error or "time is greater than current task" error
            if (is_string($metaMessage) && (
                strpos($metaMessage, 'tidak boleh kurang atau sama dengan waktu sebelumnya') !== false ||
                strpos($metaMessage, 'lebih besar dari pada TaskId') !== false ||
                strpos($metaMessage, 'lebih besar daripada TaskId') !== false
            )) {
                Log::warning('Time validation error, attempting automatic retry with adjusted time', [
                    'kodebooking' => $kodebooking,
                    'taskid' => $taskid,
                    'current_waktu' => $waktuInt,
                    'error' => $metaMessage
                ]);

                // Hit list task API to get the exact time registered in BPJS server
                $listTaskResult = $this->getListTask($kodebooking);
                $bestWaktuObj = null;

                if ($listTaskResult['success'] && is_array($listTaskResult['data'])) {
                    // Find actual previous task time registered in BPJS
                    $bpjsTasks = $listTaskResult['data'];
                    foreach ($bpjsTasks as $bt) {
                        if (isset($bt['taskid']) && $bt['taskid'] < $taskid) {
                            if ($bestWaktuObj === null || $bt['taskid'] > $bestWaktuObj['taskid']) {
                                $bestWaktuObj = $bt;
                            }
                        }
                    }
                }

                $previousWaktu = null;

                if ($bestWaktuObj !== null && isset($bestWaktuObj['waktu'])) {
                    // Extracting the timestamp string correctly
                    // Format returned by BPJS is "DD-MM-YYYY HH:mm:ss WIB" for time
                    try {
                        $timeString = str_replace(' WIB', '', $bestWaktuObj['waktu']);
                        $time = Carbon::createFromFormat('d-m-Y H:i:s', $timeString, 'Asia/Jakarta');
                        $previousWaktu = $time->timestamp * 1000;
                        Log::info('Found previous task time natively via BPJS getListTask', [
                            'kodebooking' => $kodebooking,
                            'prev_task_id' => $bestWaktuObj['taskid'],
                            'bpjs_waktu_str' => $bestWaktuObj['waktu'],
                            'parsed_timestamp_ms' => $previousWaktu
                        ]);
                    } catch (Exception $e) {
                         Log::error("Failed to parse BPJS returned time", ['error' => $e->getMessage()]);
                    }
                }

                // If message contains the time, try to extract it from the message itself as well
                if (preg_match('/TaskId=\d+ \((.*?)\) lebih besar dari ?pada TaskId/', $metaMessage, $matches)) {
                    try {
                        $timeString = str_replace([' WIB', ' WITA', ' WIT'], '', $matches[1]);
                        // Example: 2026-03-17 15:39:34
                        $time = Carbon::parse($timeString, 'Asia/Jakarta');
                        $msgWaktu = $time->timestamp * 1000;
                        if ($previousWaktu === null || $msgWaktu > $previousWaktu) {
                            $previousWaktu = $msgWaktu;
                            Log::info('Extracted previous task time from error message', [
                                'extracted_time' => $timeString,
                                'new_previous_waktu' => $previousWaktu
                            ]);
                        }
                    } catch (Exception $e) {
                        Log::debug('Failed to parse time from error message', ['error' => $e->getMessage()]);
                    }
                }

                // Fallback to internal tracker if API request/parsing failed
                if ($previousWaktu === null) {
                    $previousWaktu = $this->getPreviousTaskTime($kodebooking, $taskid);
                }

                // Fallback if previous task not in local DB
                if ($previousWaktu === null) {
                    $prevDbTime = $this->getTaskTimestampFromDatabase($kodebooking, $taskid - 1);
                    if ($prevDbTime) {
                        $previousWaktu = (int)$prevDbTime;
                    }
                }

                if ($previousWaktu !== null) {
                    try {
                        $offsetMinutes = random_int(5, 10);
                    } catch (Throwable $e) {
                        $offsetMinutes = rand(5, 10);
                    }
                    $adjustedWaktu = $previousWaktu + ($offsetMinutes * 60 * 1000);

                    Log::info('Retrying with adjusted time', [
                        'previous_waktu' => $previousWaktu,
                        'adjusted_waktu' => $adjustedWaktu
                    ]);

                    // Recursive call with adjusted time, passing the BPJS error message
                    $retryResult = $this->updateTaskId($kodebooking, $taskid, (string)$adjustedWaktu, $retryCount + 1, $metaMessage);

                    // If retry succeeds, save the adjusted time to database
                    if ($retryResult['success'] || (isset($retryResult['data']['metadata']['message']) && strpos($retryResult['data']['metadata']['message'], 'sudah ada') !== false)) {
                        try {
                            $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
                            $noRawat = $referensi ? $referensi->no_rawat : $kodebooking;

                            ReferensiMobilejknBpjsTaskid::updateOrCreate(
                                [
                                    'no_rawat' => $noRawat,
                                    'taskid' => (string) $taskid
                                ],
                                [
                                    'waktu' => Carbon::createFromTimestampMs($adjustedWaktu)->setTimezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString()
                                ]
                            );

                            Log::info('Updated task ID with adjusted time in database', [
                                'no_rawat' => $noRawat,
                                'taskid' => $taskid,
                                'new_waktu' => $adjustedWaktu
                            ]);
                        } catch (Throwable $e) {
                            Log::error('Failed to save adjusted waktu', [
                                'error' => $e->getMessage(),
                                'taskid' => $taskid
                            ]);
                        }
                    }

                    return $retryResult;
                }
            }

            // Handle "Waktu tidak valid" error (time outside service date)
            if (is_string($metaMessage) && strpos($metaMessage, 'Waktu tidak valid') !== false) {
                Log::warning('Service date validation error, attempting to adjust time based on BPJS error', [
                    'kodebooking' => $kodebooking,
                    'taskid' => $taskid,
                    'current_waktu' => $waktuInt,
                    'error' => $metaMessage
                ]);

                // Try to extract expected service date from error message
                $expectedDateStr = null;
                if (preg_match('/Tanggal pelayanan untuk Kode Booking tersebut adalah \((.*?)\)/', $metaMessage, $matches)) {
                    $expectedDateStr = $matches[1];
                }

                $adjustedWaktu = null;

                if ($expectedDateStr) {
                    try {
                        $expectedDate = Carbon::parse($expectedDateStr, 'Asia/Jakarta');

                        // For any task, try to get previous task's time on this correct date
                        $previousWaktu = $this->getPreviousTaskTime($kodebooking, $taskid);

                        // Fallback if previous task not in local DB
                        if ($previousWaktu === null) {
                            $prevDbTime = $this->getTaskTimestampFromDatabase($kodebooking, $taskid - 1);
                            if ($prevDbTime) {
                                $previousWaktu = (int)$prevDbTime;
                            }
                        }

                        if ($previousWaktu !== null) {
                            // If previous task exists, just add 5-10 minutes to it
                            try {
                                $offsetMinutes = random_int(5, 10);
                            } catch (Throwable $e) {
                                $offsetMinutes = rand(5, 10);
                            }
                            $prevTime = Carbon::createFromTimestampMs($previousWaktu)->setTimezone(config('app.timezone', 'Asia/Jakarta'));

                            // Only use previous time if it's on the expected date
                            if ($prevTime->toDateString() === $expectedDate->toDateString()) {
                                $adjustedWaktu = (int)($prevTime->copy()->addMinutes($offsetMinutes)->timestamp * 1000);
                            } else {
                                // Previous time is on wrong date, construct new time on expected date
                                $adjustedWaktu = (int)($expectedDate->copy()->setTime($prevTime->hour, $prevTime->minute, $prevTime->second)->addMinutes($offsetMinutes)->timestamp * 1000);
                            }
                        } else {
                            // No previous task, just guess a reasonable time on the expected date
                            // e.g. 08:00 AM + random offset based on task id
                            $adjustedWaktu = (int)($expectedDate->copy()->setTime(8 + $taskid, rand(0, 59), rand(0, 59))->timestamp * 1000);
                        }

                        Log::info('Adjusting waktu based on exact BPJS expected date', [
                            'expected_date' => $expectedDateStr,
                            'new_waktu' => $adjustedWaktu,
                            'new_time' => Carbon::createFromTimestampMs($adjustedWaktu)->setTimezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString()
                        ]);
                    } catch (Exception $e) {
                        Log::error('Failed to parse date from BPJS error message', ['error' => $e->getMessage()]);
                    }
                }

                // If we couldn't adjust based on message date, fallback to capping to our known service date
                if ($adjustedWaktu === null && $serviceDate) {
                    $localTz = config('app.timezone', 'Asia/Jakarta');
                    $cappedTime = $serviceDate->copy()->setTime(23, 59, 59)->setTimezone($localTz);
                    $adjustedWaktu = (int)($cappedTime->timestamp * 1000);
                    Log::info('Retrying with time capped to 23:59:59 of service date', [
                        'current_waktu' => $waktuInt,
                        'capped_waktu' => $adjustedWaktu,
                        'capped_time' => $cappedTime->toDateTimeString()
                    ]);
                }

                if ($adjustedWaktu !== null) {
                    // Recursive call with adjusted time, passing the BPJS error message
                    $retryResult = $this->updateTaskId($kodebooking, $taskid, (string)$adjustedWaktu, $retryCount + 1, $metaMessage);

                    // If retry succeeds, save the adjusted time to database
                    if ($retryResult['success'] || (isset($retryResult['data']['metadata']['message']) && strpos($retryResult['data']['metadata']['message'], 'sudah ada') !== false)) {
                        try {
                            $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
                            $noRawat = $referensi ? $referensi->no_rawat : $kodebooking;

                            ReferensiMobilejknBpjsTaskid::updateOrCreate(
                                [
                                    'no_rawat' => $noRawat,
                                    'taskid' => (string) $taskid
                                ],
                                [
                                    'waktu' => Carbon::createFromTimestampMs($adjustedWaktu)->setTimezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString()
                                ]
                            );

                            Log::info('Updated task ID with adjusted time in database after BPJS date correction', [
                                'no_rawat' => $noRawat,
                                'taskid' => $taskid,
                                'new_waktu' => $adjustedWaktu
                            ]);
                        } catch (Throwable $e) {
                            Log::error('Failed to save adjusted waktu', [
                                'error' => $e->getMessage(),
                                'taskid' => $taskid
                            ]);
                        }
                    }

                    return $retryResult;
                }
            }

            // Handle "Kode Booking tidak ditemukan" by attempting to recreate antrean
            if (is_string($metaMessage) && strpos($metaMessage, 'Kode Booking tidak ditemukan') !== false) {
                Log::warning('BPJS reports Kode Booking not found, attempting to recreate antrean', [
                    'kodebooking' => $kodebooking,
                    'taskid' => $taskid,
                    'waktu' => $waktu
                ]);

                try {
                    $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
                    $noRawat = $referensi ? $referensi->no_rawat : $kodebooking;

                    // Try to send antrean using rujukan/fallback logic in sendAddAntreanByNoRawat
                    $antreanResult = $this->sendAddAntreanByNoRawat($noRawat);

                    Log::info('Antrean creation attempt result', ['result' => $antreanResult]);

                    // If antrean was created or a duplicate/acceptable response, retry update
                    $accepted = isset($antreanResult['status']) && $antreanResult['status'] === true;
                    $metaMsgAntrean = $antreanResult['bpjs']['metadata']['message'] ?? $antreanResult['message'] ?? null;
                    if ($accepted || (is_string($metaMsgAntrean) && (strpos($metaMsgAntrean, 'Ok') !== false || strpos($metaMsgAntrean, 'Terdapat duplikasi') !== false))) {
                        Log::info('Antrean created/acceptable, retrying updateTaskId', ['kodebooking' => $kodebooking, 'taskid' => $taskid]);
                        return $this->updateTaskId($kodebooking, $taskid, $waktu, $retryCount + 1, $metaMessage);
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to recreate antrean after kode booking not found', ['error' => $e->getMessage()]);
                }
            }

            $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
            if (is_string($metaMessage) && (strpos($metaMessage, "TaskId={$taskid} sudah ada") !== false || strpos($metaMessage, "Ok") !== false)) {
                try {
                    $cekRecord = ReferensiMobilejknBpjsTaskid::where('no_rawat', $referensi ? $referensi->no_rawat : $kodebooking)
                        ->where('taskid', (string) $taskid)
                        ->first();
                    if (!$cekRecord) {
                        $taskRecord = ReferensiMobilejknBpjsTaskid::firstOrNew([
                            'no_rawat' => $referensi ? $referensi->no_rawat : $kodebooking,
                            'taskid' => (string) $taskid,
                            'waktu' =>  Carbon::createFromTimestampMs((int)$waktu)->setTimezone(config('app.timezone', 'Asia/Jakarta'))->toDateTimeString()
                        ]);

                        $taskRecord->save();
                    }
                } catch (Throwable $e) {
                    Log::error('Failed to save ReferensiMobilejknBpjsTaskid', ['error' => $e->getMessage(), 'kodebooking' => $kodebooking, 'taskid' => $taskid]);
                }
            }

            $deleteTaskId = $taskid-1;
            if (is_string($metaMessage) && strpos($metaMessage, "TaskId={$deleteTaskId} belum ada") !== false) {
                ReferensiMobilejknBpjsTaskid::where('no_rawat', $kodebooking)
                    ->where('taskid', $deleteTaskId)
                    ->delete();

                return [
                    'success' => true,
                    'message' => "TaskId {$taskid} belum ada. Hapus secara lokal.",
                    'status_code' => $response->status(),
                    'data' => $responseData,
                    'metadata' => $responseData['metadata'] ?? null,
                    'batal' => $batal
                ];
            }

            $metaCode = $responseData['metadata']['code'] ?? null;
            $isBpjsSuccess = ($metaCode == 200 || $metaCode == 201 || (is_string($metaMessage) && strpos($metaMessage, 'Ok') !== false));

            return [
                'success' => $response->successful() && $isBpjsSuccess,
                'status_code' => $response->status(),
                'data' => $responseData,
                'metadata' => $responseData['metadata'] ?? null,
                'batal' => $batal
            ];

        } catch (Exception $e) {
            // Log error to BPJS log database
            $this->bpjsLogService->logRequest(
                500,
                json_encode(['kodebooking' => $kodebooking, 'taskid' => $taskid, 'waktu' => $waktu]),
                $e->getMessage(),
                $this->baseUrl . '/antrean/updatewaktu',
                'POST'
            );

            Log::error('Mobile JKN Task Update Error', [
                'kodebooking' => $kodebooking,
                'taskid' => $taskid,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Update task ID with timestamp from database
     *
     * @param string $kodebooking
     * @param int $taskid
     * @return array
     */
    public function updateTaskIdFromDatabase(string $kodebooking, int $taskid): array
    {
        return $this->updateTaskId($kodebooking, $taskid, null);
    }

    /**
     * Update task ID with current timestamp
     *
     * @param string $kodebooking
     * @param int $taskid
     * @return array
     */
    public function updateTaskIdNow(string $kodebooking, int $taskid): array
    {
        $currentTime = (string) now()->timestamp * 1000; // Current time in milliseconds
        return $this->updateTaskId($kodebooking, $taskid, $currentTime);
    }

    /**
     * Batch update multiple task IDs
     *
     * @param array $updates Array of ['kodebooking', 'taskid', 'waktu'] arrays
     * @return array
     */
    public function batchUpdateTaskIds(array $updates): array
    {
        $results = [];

        foreach ($updates as $update) {
            $result = $this->updateTaskId(
                $update['kodebooking'],
                $update['taskid'],
                $update['waktu'] ?? null
            );
            $results[] = $result;
        }

        return $results;
    }

    /**
     * Add antrean (queue) to Mobile JKN API
     *
     * @param array $patientData Patient data from database
     * @return array
     */
    public function addAntrean(array $patientData): array
    {
        try {
            // Extract the no_rawat/kodebooking from the payload
            $noRawat = $patientData['nobooking'] ?? $patientData['kodebooking'] ?? null;

            if (empty($noRawat)) {
                throw new InvalidArgumentException('No rawat/kodebooking is required');
            }

            // Call sendAddAntreanByNoRawat instead of direct API call
            $result = $this->sendAddAntreanByNoRawat($noRawat);

            // Map the response structure to match the original addAntrean format
            return [
                'success' => $result['status'] ?? false,
                'status_code' => $result['bpjs']['metadata']['code'] ?? 500,
                'data' => $result['bpjs']['data'] ?? [],
                'metadata' => $result['bpjs']['metadata'] ?? null
            ];

        } catch (Exception $e) {
            Log::error('Mobile JKN Add Antrean Error', [
                'kodebooking' => $patientData['nobooking'] ?? $patientData['kodebooking'] ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Get UTC timestamp as string
     *
     * @return string
     */
    protected function getUtcTimestamp(): string
    {
        // Return current Unix timestamp in seconds as string (used for X-timestamp header)
        return strval(time());
    }

    /**
     * Get patient data needed for task ID updates
     *
     * @param string $regNo
     * @return array|null
     */
    public function getPatientDataForTaskId(string $regNo): ?array
    {
        try {
            // Get registration data
            $regPeriksa = RegPeriksa::where('no_rawat', $regNo)->first();

            if (!$regPeriksa) {
                Log::error('Registration not found: ' . $regNo);
                // return null;
            }

            // Get doctor information
            $dokter = PemeriksaanRalan::where('no_rawat', $regNo)->where('nip', $regPeriksa->kd_dokter)->first();

            // Get referral data from BPJS
            $referral = ReferensiMobilejknBpjs::where('no_rawat', $regNo)->first();

            if (!$referral) {
                Log::error('BPJS referral not found for: ' . $regNo);
                // return null;
            }

            // Get examination data
            $pemeriksaan = PemeriksaanRalan::where('no_rawat', $regNo)->join('petugas', 'petugas.nip', '=', 'pemeriksaan_ralan.nip')->first();

            // Get prescription data
            $resepObat = ResepObat::where('no_rawat', $regNo)->first();

            $kode = $referral != null ? $referral->nobooking : $regPeriksa->no_rawat;

            return [
                'registration' => $regPeriksa,
                'doctor' => $dokter,
                'referral' => $referral,
                'examination' => $pemeriksaan,
                'prescription' => $resepObat,
                'task_timestamps' => [
                    '3' => $this->getTask3Timestamp($kode),
                    '4' => $this->getTask4Timestamp($kode),
                    '5' => $this->getTask5Timestamp($kode),
                    '6' => $this->getTask6Timestamp($kode),
                    '7' => $this->getTask7Timestamp($kode)
                ],
                'kodebooking' => $kode
            ];

        } catch (Exception $e) {
            Log::error('Error retrieving patient data for task ID: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Get saved task ID record from referensi_mobilejkn_bpjs_taskid by no_rawat and taskid
     *
     * @param string $kodebooking
     * @return array|null
     */
    public function getListTask(string $kodebooking): array
    {
        try {
            $cached = BpjsPatientVisit::where('kodebooking', $kodebooking)->first();

            $shouldUseCache = false;
            if ($cached && $cached->task_data !== null) {
                $reg = null;
                if (strpos($kodebooking, '/') !== false) {
                    $reg = RegPeriksa::where('no_rawat', $kodebooking)->first();
                } else {
                    $ref = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
                    if ($ref) {
                        $reg = RegPeriksa::where('no_rawat', $ref->no_rawat)->first();
                    }
                }

                $hasTask7 = false;
                if (is_array($cached->task_data)) {
                    foreach ($cached->task_data as $task) {
                        if (isset($task['taskid']) && $task['taskid'] == 7) {
                            $hasTask7 = true;
                            break;
                        }
                    }
                }

                if ($hasTask7 || ($cached->last_sync && $cached->last_sync->gt(now()->subMinutes(15)))) {
                    $shouldUseCache = true;
                }
            }

            if ($shouldUseCache) {
                return [
                    'success' => true,
                    'data' => $cached->task_data ?? [],
                    'metadata' => [
                        'code' => 200,
                        'message' => 'Success (Loaded from DB Cache)'
                    ],
                    'status_code' => 200
                ];
            }

            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            $requestData = [
                'kodebooking' => $kodebooking
            ];

            Log::info('Mobile JKN Get List Task Request', [
                'kodebooking' => $kodebooking,
                'timestamp' => (int) $timestamp
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-cons-id' => $this->consId,
                'X-timestamp' => $timestamp,
                'X-signature' => $signature,
                'user_key' => $this->userKey,
            ])->timeout(5)->connectTimeout(3)->post($this->baseUrl . '/antrean/getlisttask', $requestData);

            $responseData = $response->json();

            // Log to BPJS log database (Temporarily disabled for monitoring to prevent flooding bpjs_ws_rs_log)
            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode($requestData),
                json_encode($responseData),
                $this->baseUrl . '/antrean/getlisttask',
                'POST'
            );

            Log::info('Mobile JKN Get List Task Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            $data = [];
            $metaCode = 200;
            $metaMsg = 'OK';

            if ($response->status() == 200 && isset($responseData['metadata']['code'])) {
                $metaCode = (int)$responseData['metadata']['code'];
                $metaMsg = $responseData['metadata']['message'] ?? 'OK';

                if ($metaCode == 200 && isset($responseData['response'])) {
                    if (is_string($responseData['response'])) {
                        $key = $this->generateDecryptionKey((string) $timestamp);
                        $decrypted = $this->stringDecrypt($key, $responseData['response']);
                        $decompressed = $this->decompress($decrypted);
                        $data = json_decode($decompressed, true) ?? [];
                    } else {
                        $data = $responseData['response'];
                    }
                }
            } else {
                $metaCode = $response->status();
                $metaMsg = $response->reason() ?: 'API Error';
            }

            $success = in_array($metaCode, [200, 201, 204]);
            if ($success) {
                if ($cached) {
                    $cached->update([
                        'task_data' => $data,
                        'last_sync' => now()
                    ]);
                } else {
                    $reg = null;
                    if (strpos($kodebooking, '/') !== false) {
                        $reg = RegPeriksa::where('no_rawat', $kodebooking)->first();
                    } else {
                        $ref = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
                        if ($ref) {
                            $reg = RegPeriksa::where('no_rawat', $ref->no_rawat)->first();
                        }
                    }
                    if ($reg) {
                        BpjsPatientVisit::create([
                            'kodebooking' => $kodebooking,
                            'no_rawat' => $reg->no_rawat,
                            'tanggalperiksa' => $reg->tgl_registrasi,
                            'task_data' => $data,
                            'last_sync' => now()
                        ]);
                    }
                }
            }

            return [
                'success' => $success,
                'data' => $data,
                'metadata' => [
                    'code' => $metaCode,
                    'message' => $metaMsg
                ],
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('Error getting list task from Mobile JKN', [
                'kodebooking' => $kodebooking,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Get listtask directly from BPJS API by kodebooking (bypassing DB cache check)
     *
     * @param string $kodebooking
     * @return array
     */
    public function getListTaskDirect(string $kodebooking): array
    {
        try {
            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            $requestData = [
                'kodebooking' => $kodebooking
            ];

            Log::info('Mobile JKN Get List Task Direct Request', [
                'kodebooking' => $kodebooking,
                'timestamp' => (int) $timestamp
            ]);

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'X-cons-id' => $this->consId,
                'X-timestamp' => $timestamp,
                'X-signature' => $signature,
                'user_key' => $this->userKey,
            ])->timeout(5)->connectTimeout(3)->post($this->baseUrl . '/antrean/getlisttask', $requestData);

            $responseData = $response->json();

            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode($requestData),
                json_encode($responseData),
                $this->baseUrl . '/antrean/getlisttask',
                'POST'
            );

            Log::info('Mobile JKN Get List Task Direct Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            $data = [];
            $metaCode = 200;
            $metaMsg = 'OK';

            if ($response->status() == 200 && isset($responseData['metadata']['code'])) {
                $metaCode = (int)$responseData['metadata']['code'];
                $metaMsg = $responseData['metadata']['message'] ?? 'OK';

                if ($metaCode == 200 && isset($responseData['response'])) {
                    if (is_string($responseData['response'])) {
                        $key = $this->generateDecryptionKey((string) $timestamp);
                        $decrypted = $this->stringDecrypt($key, $responseData['response']);
                        $decompressed = $this->decompress($decrypted);
                        $data = json_decode($decompressed, true) ?? [];
                    } else {
                        $data = $responseData['response'];
                    }
                }
            } else {
                $metaCode = $response->status();
                $metaMsg = $response->reason() ?: 'API Error';
            }

            $success = in_array($metaCode, [200, 201, 204]);

            // Update cache record in DB upon successful direct fetch
            if ($success) {
                $cached = BpjsPatientVisit::where('kodebooking', $kodebooking)->first();
                if ($cached) {
                    $cached->update([
                        'task_data' => $data,
                        'last_sync' => now()
                    ]);
                } else {
                    $reg = null;
                    if (strpos($kodebooking, '/') !== false) {
                        $reg = RegPeriksa::where('no_rawat', $kodebooking)->first();
                    } else {
                        $ref = ReferensiMobilejknBpjs::where('nobooking', $kodebooking)->first();
                        if ($ref) {
                            $reg = RegPeriksa::where('no_rawat', $ref->no_rawat)->first();
                        }
                    }
                    if ($reg) {
                        BpjsPatientVisit::create([
                            'kodebooking' => $kodebooking,
                            'no_rawat' => $reg->no_rawat,
                            'tanggalperiksa' => $reg->tgl_registrasi,
                            'task_data' => $data,
                            'last_sync' => now()
                        ]);
                    }
                }
            }

            return [
                'success' => $success,
                'data' => $data,
                'metadata' => [
                    'code' => $metaCode,
                    'message' => $metaMsg
                ],
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('Error getting list task direct from Mobile JKN', [
                'kodebooking' => $kodebooking,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'metadata' => [
                    'code' => 500,
                    'message' => $e->getMessage()
                ],
                'status_code' => 500
            ];
        }
    }

    public function getTaskIdRecord(string $noRawat, ?int $taskid = null): ?array
    {
        try {
            $record = ReferensiMobilejknBpjsTaskid::where('no_rawat', $noRawat);

            if ($taskid !== null) {
                $record = $record->where('taskid', $taskid)->first();
            } else {
                $record = $record->get();
            }

            if (!$record) {
                return null;
            }

            // Return array representation; waktu will be cast to ISO string by model casting
            return $record->toArray();
        } catch (Throwable $e) {
            Log::error('Error fetching ReferensiMobilejknBpjsTaskid', [
                'no_rawat' => $noRawat,
                'taskid' => $taskid,
                'error' => $e->getMessage()
            ]);
            return null;
        }
    }

    /**
     * Wrapper to call sendAddAntreanByNoRawat (public API)
     */
    public function sendAddAntreanByNoRawat(string $noRawat): array
    {
        return $this->sendAddAntreanByNoRawatInternal($noRawat);
    }

    /**
     * Internal implementation (kept separate to avoid duplicate name issues)
     */
    protected function sendAddAntreanByNoRawatInternal(string $noRawat): array
    {
        $payload = [];
        try {
            $reg = RegPeriksa::where('no_rawat', $noRawat)->first();
            if (!$reg) {
                $reg = ReferensiMobilejknBpjs::select('reg_periksa.*')
                        ->where('no_rawat', $noRawat)
                        ->join('reg_periksa', 'reg_periksa.no_rawat', '=', 'referensi_mobilejkn_bpjs.no_rawat')
                        ->first();
            }

            if (!$reg) {
                return [
                    'status' => false,
                    'message' => 'Registration not found',
                    'data' => [],
                    'payload' => null,
                    'bpjs' => null
                ];
            }

            $pasien = Pasien::where('no_rkm_medis', $reg->no_rkm_medis)->first();

            $mapPoli = MapingPoliBpjs::where('kd_poli_rs', $reg->kd_poli)->first();
            $mapDok = MapingDokterDpjpvclaim::where('kd_dokter', $reg->kd_dokter)->first();

            $jadwal = Jadwal::where('kd_dokter', $reg->kd_dokter)
                ->where('kd_poli', $reg->kd_poli)
                ->orderBy('jam_mulai')
                ->first();

            $kodepoli = $mapPoli ? $mapPoli->kd_poli_bpjs : $reg->kd_poli;
            $namapoli = $reg->poliklinik->nm_poli ?? ($mapPoli->nm_poli_bpjs ?? null);
            $kodedokter = $mapDok ? $mapDok->kd_dokter_bpjs : $reg->kd_dokter;
            $namadokter = $mapDok ? ($mapDok->nm_dokter_bpjs ?? $reg->dokter->nm_dokter ?? null) : ($reg->dokter->nm_dokter ?? null);

            Log::info("Mapping Poli: {$reg->kd_poli} => {$kodepoli}, Dokter: {$reg->kd_dokter} => {$kodedokter}");

            if ($jadwal) {
                $jamMulai = substr($jadwal->jam_mulai ?? '00:00:00', 0, 5);
                $jamSelesai = substr($jadwal->jam_selesai ?? $jadwal->jam_mulai ?? '00:00:00', 0, 5);
                $jampraktek = $jamMulai . '-' . $jamSelesai;
            } else {
                $jampraktek = '08:00-16:00';
            }

            $noRegInt = intval($reg->no_reg);
            $baseDatetime = Carbon::parse(explode(' ', $reg->tgl_registrasi)[0] . ' ' . ($jadwal->jam_mulai ?? '00:00:00'), 'Asia/Jakarta');
            $estimasidilayani = $baseDatetime->copy()->addMinutes($noRegInt * 2);

            $pasienbaru = 0;
            if (stripos($reg->stts_daftar ?? '', 'Baru') !== false) {
                $pasienbaru = 1;
            }

            $jenisKunjungan = 1;
            $nomorreferensi = $this->fetchRujukan($pasien->no_peserta ?? '', $kodepoli);
            // if (empty($nomorreferensi)) {
                // $jenisKunjungan = 4; // Rujukan RS
                // $nomorreferensi = $this->fetchRujukanRS($pasien->no_peserta ?? '', $kodepoli);
            // }

            // if (empty($nomorreferensi)) {
                // $jenisKunjungan = 3; // Kontrol
                // $nomorreferensi = $this->fetchKontrol($pasien->no_peserta ?? '', $kodepoli, null, null);
            // }

            Log::info("Nomor Referensi: {$nomorreferensi}, Jenis Kunjungan: {$jenisKunjungan}");

            $angkaAntrean = str_pad((string) intval($reg->no_reg), 3, '0', STR_PAD_LEFT);
            $nomorAntrean = ($kodepoli ? $kodepoli : $reg->kd_poli) . '-' . $angkaAntrean;

            // Use getKodeBooking to determine the proper booking code
            $kodeBooking = $this->getKodeBooking($reg->no_rawat);

            $payload = [
                'kodebooking' => $kodeBooking,
                'jenispasien' => 'JKN',
                'nomorkartu' => $pasien->no_peserta ?? '',
                'nik' => $pasien->no_ktp ?? '',
                'nohp' => explode('/', $pasien->no_tlp)[0] ?? '00000000',
                'norm' => $reg->no_rkm_medis,
                'kodepoli' => $kodepoli,
                'namapoli' => $namapoli,
                'pasienbaru' => $pasienbaru,
                'tanggalperiksa' => explode(' ', $reg->tgl_registrasi)[0],
                'kodedokter' => $kodedokter,
                'namadokter' => $namadokter,
                'jampraktek' => $jampraktek,
                'jeniskunjungan' => $jenisKunjungan,
                'nomorreferensi' => $nomorreferensi ?: '-',
                'nomorantrean' => $nomorAntrean,
                'angkaantrean' => $angkaAntrean,
                'estimasidilayani' => (int) ($estimasidilayani->timestamp * 1000),
                'sisakuotajkn' => $jadwal ? max(0, intval($jadwal->kuota) - intval($reg->no_reg)) : 0,
                'kuotajkn' => $jadwal ? intval($jadwal->kuota) : 0,
                'sisakuotanonjkn' => $jadwal ? max(0, intval($jadwal->kuota) - intval($reg->no_reg)) : 0,
                'kuotanonjkn' => $jadwal ? intval($jadwal->kuota) : 0,
                'keterangan' => 'Peserta harap 30 menit lebih awal guna pencatatan administrasi.'
            ];

            // Make the actual BPJS API call
            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));


            // Log the request
            Log::info('Mobile JKN Add Antrean Request', [
                'kodebooking' => $payload['kodebooking'],
                'timestamp' => $timestamp,
                'request_data' => $payload
            ]);

            // Make HTTP request
            $response = Http::withHeaders(headers: [
                'Content-Type' => 'application/json',
                'x-cons-id' => $this->consId,
                'x-timestamp' => $timestamp,
                'x-signature' => $signature,
                'user_key' => $this->userKey,
            ])->post($this->baseUrl . '/antrean/add', $payload);

            // Parse response
            $responseData = $response->json();

            // Log to BPJS log database
            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode($payload),
                json_encode($responseData),
                $this->baseUrl . '/antrean/add',
                'POST'
            );

            // Log the response
            Log::info('Mobile JKN Add Antrean Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            $status = $response->successful();
            $metaMessage = $responseData['metadata']['message'] ?? ($responseData['metadata'] ?? '');

            // If response is not OK or duplicate, retry with other jenis kunjungan values once each: 1,3,4
            $acceptable = false;
            if ($status) $acceptable = true;
            if (is_string($metaMessage) && (strpos($metaMessage, 'Ok') !== false || strpos($metaMessage, 'Terdapat duplikasi') !== false)) {
                $acceptable = true;
            }

            if (!$acceptable) {
                $retryJenises = [1, 3, 4];
                foreach ($retryJenises as $altJenis) {
                    // skip the same jenis we already tried
                    if ($altJenis == $jenisKunjungan) continue;

                    // prepare payload with alternate jenis kunjungan and recompute nomorreferensi if needed
                    $payload['jeniskunjungan'] = $altJenis;
                    if ($altJenis === 1) {
                        $payload['nomorreferensi'] = $this->fetchRujukan($pasien->no_peserta ?? '', $kodepoli) ?: '-';
                    } elseif ($altJenis === 4) {
                        $payload['nomorreferensi'] = $this->fetchRujukanRS($pasien->no_peserta ?? '', $kodepoli) ?: '-';
                    } elseif ($altJenis === 3) {
                        $payload['nomorreferensi'] = $this->fetchKontrol($pasien->no_peserta ?? '', $kodepoli) ?: '-';
                    }

                    // regenerate headers/signature for each retry
                    $timestamp = $this->getUtcTimestamp();
                    $signature = base64_encode($this->generateSignature($timestamp));

                    // Log the retry attempt
                    Log::info('Retry Mobile JKN Add Antrean Attempt', [
                        'kodebooking' => $payload['kodebooking'],
                        'jeniskunjungan' => $altJenis,
                        'timestamp' => $timestamp,
                        'request_data' => $payload
                    ]);

                    // perform the retry request
                    $response = Http::withHeaders(headers: [
                        'Content-Type' => 'application/json',
                        'x-cons-id' => $this->consId,
                        'x-timestamp' => $timestamp,
                        'x-signature' => $signature,
                        'user_key' => $this->userKey,
                    ])->post($this->baseUrl . '/antrean/add', $payload);

                    $responseData = $response->json();

                    // Log to BPJS log database for retry
                    $this->bpjsLogService->logRequest(
                        $response->status(),
                        json_encode($payload),
                        json_encode($responseData),
                        $this->baseUrl . '/antrean/add',
                        'POST'
                    );

                    Log::info('Mobile JKN Add Antrean Response (retry)', [
                        'status' => $response->status(),
                        'response' => $responseData
                    ]);

                    $status = $response->successful();
                    $metaMessage = $responseData['metadata']['message'] ?? ($responseData['metadata'] ?? '');

                    if ($status || (is_string($metaMessage) && (strpos($metaMessage, 'Ok') !== false || strpos($metaMessage, 'Terdapat duplikasi') !== false))) {
                        // successful or acceptable duplicate; stop retrying
                        break;
                    }
                }
            }

            $message = $status ? ($responseData['metadata']['message'] ?? 'Antrean sent') : ($responseData['metadata']['message'] ?? 'Mohon Maaf Gagal Mengirim Antrean, Silahkan Coba Lagi!');

            return [
                'status' => $status,
                'message' => $message,
                'data' => [],
                'payload' => $payload,
                'bpjs' => [
                    'metadata' => $responseData['metadata'] ?? null,
                    'data' => $responseData['data'] ?? null
                ]
            ];

        } catch (Exception $e) {
            Log::error('Error in sendAddAntreanByNoRawat: ' . $e->getMessage());
            return [
                'status' => false,
                'message' => $e->getMessage(),
                'data' => [],
                'payload' => $payload,
                'bpjs' => null
            ];
        }
    }

    /**
     * Placeholder: fetch referral by participant and poli (implement BPJS lookup if available)
     */
    protected function fetchRujukan(string $noPeserta, string $kdPoliBpjs): string
    {
        $noRujukan = '';

        try {
            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            $url = rtrim($this->baseUrlVclaim, '/') . '/Rujukan/List/Peserta/' . $noPeserta;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-cons-id' => $this->consId,
                'x-timestamp' => $timestamp,
                'x-signature' => $signature,
                'user_key' => $this->userKey,
            ])->get($url);

            $responseData = $response->json();
            Log::info('Rujukan ', ['response' => $response]);

            // Log request/response to BPJS log
            try {
                $this->bpjsLogService->logRequest(
                    $response->status(),
                    json_encode([]),
                    json_encode($responseData),
                    $url,
                    'GET'
                );
            } catch (Throwable $e) {
                Log::error('Failed to log BPJS rujukan request', ['error' => $e->getMessage()]);
            }

            $meta = $responseData['metaData'] ?? $responseData['metadata'] ?? null;
            $code = $meta['code'] ?? $meta['status'] ?? null;

            if ($code === '200' || $code === 200) {
                $rujukanList = null;

                // Response may contain 'response' as an object/array or a JSON string
                if (isset($responseData['response'])) {
                    $respContent = $responseData['response'];

                    // Handle encrypted response
                    if (is_string($respContent)) {
                        try {
                            // Generate decryption key using consid + secretkey + timestamp
                            $decryptionKey = $this->generateDecryptionKey($timestamp);

                            // Decrypt response using our generated key
                            $decryptedResponse = $this->stringDecrypt($decryptionKey, $respContent);

                            // Decompress if needed
                            if ($decryptedResponse) {
                                $decompressedResponse = $this->decompress($decryptedResponse);
                                $decoded = json_decode($decompressedResponse, true);

                                Log::info('Decrypted rujukan response', [
                                    'decrypted' => substr($decryptedResponse, 0, 100) . '...',
                                    'decompressed' => substr($decompressedResponse, 0, 100) . '...'
                                ]);

                                if (is_array($decoded) && isset($decoded['rujukan'])) {
                                    $rujukanList = $decoded['rujukan'];
                                }
                            }
                        } catch (Throwable $decryptError) {
                            Log::error('Error decrypting BPJS rujukan response', [
                                'error' => $decryptError->getMessage()
                            ]);
                        }
                    } elseif (is_string($respContent)) {
                        $decoded = json_decode($respContent, true);
                        if (is_array($decoded) && isset($decoded['rujukan'])) {
                            $rujukanList = $decoded['rujukan'];
                        }
                    } elseif (is_array($respContent) && isset($respContent['rujukan'])) {
                        $rujukanList = $respContent['rujukan'];
                    }
                }

                if (is_array($rujukanList)) {
                    foreach ($rujukanList as $item) {
                        $kodePoli = $item['poliRujukan']['kode'] ?? ($item['poliRujukan']['kode'] ?? '');
                        if ($kodePoli === $kdPoliBpjs) {
                            $noRujukan = $item['noKunjungan'] ?? '';
                            break;
                        }
                    }
                }
            }

            return $noRujukan;
        } catch (Throwable $e) {
            Log::error('Error fetching BPJS rujukan', ['noPeserta' => $noPeserta, 'error' => $e->getMessage()]);
            return $noRujukan;
        }
    }

    /**
     * Placeholder: fetch RS referral fallback
     */
    protected function fetchRujukanRS(string $noPeserta, string $kdPoliBpjs): string
    {
        $noRujukan = '';

        try {
            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            $url = rtrim($this->baseUrlVclaim, '/') . '/Rujukan/RS/List/Peserta/' . $noPeserta;

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-cons-id' => $this->consId,
                'x-timestamp' => $timestamp,
                'x-signature' => $signature,
                'user_key' => $this->userKey,
            ])->get($url);

            $responseData = $response->json();
            Log::info('Rujukan RS ', ['response' => $response]);

            // Log request/response to BPJS log
            try {
                $this->bpjsLogService->logRequest(
                    $response->status(),
                    json_encode([]),
                    json_encode($responseData),
                    $url,
                    'GET'
                );
            } catch (Throwable $e) {
                Log::error('Failed to log BPJS rujukan RS request', ['error' => $e->getMessage()]);
            }

            $meta = $responseData['metaData'] ?? $responseData['metadata'] ?? null;
            $code = $meta['code'] ?? $meta['status'] ?? null;

            if ($code === '200' || $code === 200) {
                $rujukanList = null;

                if (isset($responseData['response'])) {
                    $respContent = $responseData['response'];

                    // Handle encrypted response
                    if (is_string($respContent)) {
                        try {
                            // Generate decryption key using consid + secretkey + timestamp
                            $decryptionKey = $this->generateDecryptionKey($timestamp);

                            // Decrypt response using our generated key
                            $decryptedResponse = $this->stringDecrypt($decryptionKey, $respContent);

                            // Decompress if needed
                            if ($decryptedResponse) {
                                $decompressedResponse = $this->decompress($decryptedResponse);
                                $decoded = json_decode($decompressedResponse, true);

                                Log::info('Decrypted rujukan RS response', [
                                    'decrypted' => substr($decryptedResponse, 0, 100) . '...',
                                    'decompressed' => substr($decompressedResponse, 0, 100) . '...'
                                ]);

                                if (is_array($decoded) && isset($decoded['rujukan'])) {
                                    $rujukanList = $decoded['rujukan'];
                                }
                            }
                        } catch (Throwable $decryptError) {
                            Log::error('Error decrypting BPJS rujukan RS response', [
                                'error' => $decryptError->getMessage()
                            ]);
                        }
                    } elseif (is_string($respContent)) {
                        $decoded = json_decode($respContent, true);
                        if (is_array($decoded) && isset($decoded['rujukan'])) {
                            $rujukanList = $decoded['rujukan'];
                        }
                    } elseif (is_array($respContent) && isset($respContent['rujukan'])) {
                        $rujukanList = $respContent['rujukan'];
                    }
                }

                if (is_array($rujukanList)) {
                    foreach ($rujukanList as $item) {
                        $kodePoli = $item['poliRujukan']['kode'] ?? ($item['poliRujukan']['kode'] ?? '');
                        if ($kodePoli === $kdPoliBpjs) {
                            $noRujukan = $item['noKunjungan'] ?? '';
                            break;
                        }
                    }
                }
            }

            return $noRujukan;
        } catch (Throwable $e) {
            Log::error('Error fetching BPJS rujukan RS', ['noPeserta' => $noPeserta, 'error' => $e->getMessage()]);
            return $noRujukan;
        }
    }

    /**
     * Placeholder: fetch kontrol rujukan by peserta and poli
     */
    protected function fetchKontrol(string $noPeserta, string $kdPoliBpjs, ?string $bulan = null, ?string $tahun = null, int $filter = 2): string
    {
        $noSuratKontrol = '';

        try {
            // default to current month/year if not provided
            $now = Carbon::now('Asia/Jakarta');
            $bulan = $bulan ?: $now->format('m');
            $tahun = $tahun ?: $now->format('Y');

            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            $url = rtrim($this->baseUrlVclaim, '/') . "/RencanaKontrol/ListRencanaKontrol/Bulan/{$bulan}/Tahun/{$tahun}/Nokartu/{$noPeserta}/filter/{$filter}";

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-cons-id' => $this->consId,
                'x-timestamp' => $timestamp,
                'x-signature' => $signature,
                'user_key' => $this->userKey,
            ])->get($url);

            $responseData = $response->json();
            Log::info('Kontrol ', ['response' => $response]);
            // Log request/response
            try {
                $this->bpjsLogService->logRequest(
                    $response->status(),
                    json_encode([]),
                    json_encode($responseData),
                    $url,
                    'GET'
                );
            } catch (Throwable $e) {
                Log::error('Failed to log BPJS kontrol request', ['error' => $e->getMessage()]);
            }

            $meta = $responseData['metaData'] ?? $responseData['metadata'] ?? null;
            $code = $meta['code'] ?? $meta['status'] ?? null;

            if ($code === '200' || $code === 200) {
                $respContent = $responseData['response'] ?? null;
                $list = null;

                // Handle encrypted response
                if (is_string($respContent)) {
                    try {
                        // Generate decryption key using consid + secretkey + timestamp
                        $decryptionKey = $this->generateDecryptionKey($timestamp);

                        // Decrypt response using our generated key
                        $decryptedResponse = $this->stringDecrypt($decryptionKey, $respContent);

                        // Decompress if needed
                        if ($decryptedResponse) {
                            $decompressedResponse = $this->decompress($decryptedResponse);
                            $decoded = json_decode($decompressedResponse, true);

                            Log::info('Decrypted kontrol response', [
                                'decrypted' => substr($decryptedResponse, 0, 100) . '...',
                                'decompressed' => substr($decompressedResponse, 0, 100) . '...'
                            ]);

                            if (is_array($decoded) && isset($decoded['list'])) {
                                $list = $decoded['list'];
                            }
                        }
                    } catch (Throwable $decryptError) {
                        Log::error('Error decrypting BPJS kontrol response', [
                            'error' => $decryptError->getMessage()
                        ]);
                    }
                } else {
                    $list = $responseData['response']['list'] ?? null;
                }

                if (is_array($list)) {
                    foreach ($list as $item) {
                        // match poli by kode (poliTujuan) or namaPoliTujuan if needed
                        $poliTujuan = $item['poliTujuan'] ?? ($item['poliTujuan'] ?? '');
                        if ($poliTujuan === $kdPoliBpjs) {
                            $noSuratKontrol = $item['noSuratKontrol'] ?? '';
                            break;
                        }
                    }
                }
            }

            return $noSuratKontrol;
        } catch (Throwable $e) {
            Log::error('Error fetching BPJS rencana kontrol', ['noPeserta' => $noPeserta, 'error' => $e->getMessage()]);
            return $noSuratKontrol;
        }
    }

    /**
     * Cancel an appointment in Mobile JKN API
     *
     * @param string $kodeBooking Registration code / booking code
     * @param string $keterangan Reason for cancellation
     * @return array
     */
    public function batalAntrean(string $kodeBooking, string $keterangan = ''): array
    {
        try {
            if (empty($kodeBooking)) {
                throw new InvalidArgumentException('Kode booking is required');
            }

            // Default reason if not provided
            if (empty($keterangan)) {
                $keterangan = 'Pembatalan antrean oleh pasien/RS';
            }

            // Generate timestamp and signature
            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));

            // Prepare request data
            $requestData = [
                'kodebooking' => $kodeBooking,
                'keterangan' => $keterangan
            ];

            // Log the request
            Log::info('Mobile JKN Batal Antrean Request', [
                'kodebooking' => $kodeBooking,
                'timestamp' => $timestamp,
                'request_data' => $requestData
            ]);

            // Make HTTP request
            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'x-cons-id' => $this->consId,
                'x-timestamp' => $timestamp,
                'x-signature' => $signature,
                'user_key' => $this->userKey,
            ])->post($this->baseUrl . '/antrean/batal', $requestData);

            // Parse response
            $responseData = $response->json();

            // Log to BPJS log database
            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode($requestData),
                json_encode($responseData),
                $this->baseUrl . '/antrean/batal',
                'POST'
            );

            // Log the response
            Log::info('Mobile JKN Batal Antrean Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            // If successful, save cancellation to local DB
            // if ($response->successful()) {
            //     try {
            //         // Get registration data
            //         $regPeriksa = RegPeriksa::where('no_rawat', $kodeBooking)->first();

            //         // If not found as no_rawat, try finding via BPJS referral table
            //         if (!$regPeriksa) {
            //             $referensi = ReferensiMobilejknBpjs::where('nobooking', $kodeBooking)->first();
            //             if ($referensi) {
            //                 $regPeriksa = RegPeriksa::where('no_rawat', $referensi->no_rawat)->first();
            //             }
            //         }

            //         // Save cancellation record if reg data found
            //         if ($regPeriksa) {
            //             $batalRecord = new ReferensiMobilejknBpjsBatal([
            //                 'no_rawat' => $regPeriksa->no_rawat,
            //                 'nobooking' => $kodeBooking,
            //                 'status' => 'Batal',
            //                 'keterangan' => $keterangan,
            //                 'response' => json_encode($responseData),
            //                 'tanggal' => now()
            //             ]);
            //             $batalRecord->save();
            //         }
            //     } catch (Throwable $e) {
            //         Log::error('Failed to save ReferensiMobilejknBpjsBatal', ['error' => $e->getMessage(), 'kodebooking' => $kodeBooking]);
            //     }
            // }

            return [
                'success' => $response->successful(),
                'status_code' => $response->status(),
                'data' => $responseData,
                'metadata' => $responseData['metadata'] ?? null
            ];

        } catch (Exception $e) {
            // Log error to BPJS log database
            $this->bpjsLogService->logRequest(
                500,
                json_encode(['kodebooking' => $kodeBooking, 'keterangan' => $keterangan]),
                $e->getMessage(),
                $this->baseUrl . '/antrean/batal',
                'POST'
            );

            Log::error('Mobile JKN Batal Antrean Error', [
                'kodebooking' => $kodeBooking,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Wrapper method to cancel an appointment by No. Rawat
     * Handles finding the correct BPJS booking code
     *
     * @param string $noRawat Hospital registration number
     * @param string $keterangan Reason for cancellation
     * @return array
     */
    public function batalAntreanByNoRawat(string $noRawat, string $keterangan = ''): array
    {
        try {
            // Find BPJS booking code
            $referensi = ReferensiMobilejknBpjs::where('no_rawat', $noRawat)->first();

            // If found in referensi table, use nobooking
            if ($referensi && $referensi->nobooking) {
                return $this->batalAntrean($referensi->nobooking, $keterangan);
            }

            // Otherwise use no_rawat as the booking code
            return $this->batalAntrean($noRawat, $keterangan);

        } catch (Exception $e) {
            Log::error('Error canceling appointment by no_rawat', [
                'no_rawat' => $noRawat,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Get official dashboard queue performance from BPJS by date
     *
     * @param string $tanggal Format YYYY-MM-DD
     * @param string $waktu Waktu server ('rs' or 'server')
     * @return array
     */
    public function getDashboardPerTanggal(string $tanggal, string $waktu = 'rs'): array
    {
        try {
            $cached = BpjsDashboardWaktutunggu::where('tanggal', $tanggal)
                ->where('waktu', $waktu)
                ->get();

            $isToday = $tanggal === date('Y-m-d');
            $isCacheValid = false;

            if ($cached->isNotEmpty()) {
                if (!$isToday) {
                    $isCacheValid = true;
                } else {
                    $lastUpdated = $cached->max('updated_at');
                    if ($lastUpdated && Carbon::parse($lastUpdated)->gt(now()->subMinutes(10))) {
                        $isCacheValid = true;
                    }
                }
            }

            if ($isCacheValid) {
                $list = [];
                foreach ($cached as $item) {
                    $list[] = [
                        'tanggal' => $item->tanggal,
                        'kodepoli' => $item->kodepoli,
                        'namapoli' => $item->namapoli,
                        'jumlah_antrean' => $item->jumlah_antrean,
                        'waktu_task1' => $item->waktu_task1,
                        'waktu_task2' => $item->waktu_task2,
                        'waktu_task3' => $item->waktu_task3,
                        'waktu_task4' => $item->waktu_task4,
                        'waktu_task5' => $item->waktu_task5,
                        'waktu_task6' => $item->waktu_task6,
                        'avg_waktu_task1' => $item->avg_waktu_task1,
                        'avg_waktu_task2' => $item->avg_waktu_task2,
                        'avg_waktu_task3' => $item->avg_waktu_task3,
                        'avg_waktu_task4' => $item->avg_waktu_task4,
                        'avg_waktu_task5' => $item->avg_waktu_task5,
                        'avg_waktu_task6' => $item->avg_waktu_task6,
                        'insertdate' => $item->insertdate,
                    ];
                }
                return [
                    'success' => true,
                    'data' => ['list' => $list],
                    'metadata' => [
                        'code' => 200,
                        'message' => 'Success (Loaded from DB Cache)'
                    ],
                    'status_code' => 200
                ];
            }

            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));
            $url = $this->baseUrl . "/dashboard/waktutunggu/tanggal/{$tanggal}/waktu/{$waktu}";

            Log::info('Mobile JKN Get Dashboard Per Tanggal Request', [
                'tanggal' => $tanggal,
                'waktu' => $waktu,
                'timestamp' => (int) $timestamp
            ]);

            $response = Http::withHeaders([
                'X-cons-id' => $this->consId,
                'X-timestamp' => $timestamp,
                'X-signature' => $signature,
                'user_key' => $this->userKey,
            ])->get($url);

            $responseData = $response->json();

            // Log to BPJS log database
            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode(['tanggal' => $tanggal, 'waktu' => $waktu]),
                json_encode($responseData),
                $url,
                'GET'
            );

            Log::info('Mobile JKN Get Dashboard Per Tanggal Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            $data = [];
            if ($response->status() == 200 && isset($responseData['metadata']['code']) && $responseData['metadata']['code'] == 200 && isset($responseData['response'])) {
                if (is_string($responseData['response'])) {
                    $key = $this->generateDecryptionKey((string) $timestamp);
                    $decrypted = $this->stringDecrypt($key, $responseData['response']);
                    $decompressed = $this->decompress($decrypted);
                    $data = json_decode($decompressed, true) ?? [];
                } else {
                    $data = $responseData['response'];
                }

                if (isset($data['list']) && is_array($data['list'])) {
                    foreach ($data['list'] as $item) {
                        BpjsDashboardWaktutunggu::updateOrCreate(
                            [
                                'tanggal' => $tanggal,
                                'waktu' => $waktu,
                                'kodepoli' => $item['kodepoli'],
                            ],
                            [
                                'namapoli' => $item['namapoli'] ?? null,
                                'jumlah_antrean' => $item['jumlah_antrean'] ?? 0,
                                'waktu_task1' => $item['waktu_task1'] ?? 0,
                                'waktu_task2' => $item['waktu_task2'] ?? 0,
                                'waktu_task3' => $item['waktu_task3'] ?? 0,
                                'waktu_task4' => $item['waktu_task4'] ?? 0,
                                'waktu_task5' => $item['waktu_task5'] ?? 0,
                                'waktu_task6' => $item['waktu_task6'] ?? 0,
                                'avg_waktu_task1' => $item['avg_waktu_task1'] ?? 0,
                                'avg_waktu_task2' => $item['avg_waktu_task2'] ?? 0,
                                'avg_waktu_task3' => $item['avg_waktu_task3'] ?? 0,
                                'avg_waktu_task4' => $item['avg_waktu_task4'] ?? 0,
                                'avg_waktu_task5' => $item['avg_waktu_task5'] ?? 0,
                                'avg_waktu_task6' => $item['avg_waktu_task6'] ?? 0,
                                'insertdate' => $item['insertdate'] ?? null,
                            ]
                        );
                    }
                }
            }

            return [
                'success' => $response->status() == 200 && isset($responseData['metadata']['code']) && $responseData['metadata']['code'] == 200,
                'data' => $data,
                'metadata' => $responseData['metadata'] ?? [],
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('Error getting dashboard per tanggal from Mobile JKN', [
                'tanggal' => $tanggal,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }

    /**
     * Get official dashboard queue performance from BPJS by month and year
     *
     * @param string $bulan Format MM (e.g. 01-12)
     * @param string $tahun Format YYYY
     * @param string $waktu Waktu server ('rs' or 'server')
     * @return array
     */
    public function getDashboardPerBulan(string $bulan, string $tahun, string $waktu = 'rs'): array
    {
        try {
            $startOfMonth = "{$tahun}-{$bulan}-01";
            $endOfMonth = date('Y-m-t', strtotime($startOfMonth));

            $cached = BpjsDashboardWaktutunggu::whereBetween('tanggal', [$startOfMonth, $endOfMonth])
                ->where('waktu', $waktu)
                ->get();

            $isCurrentMonth = ($bulan === date('m') && $tahun === date('Y'));
            $isCacheValid = false;

            if ($cached->isNotEmpty()) {
                if (!$isCurrentMonth) {
                    $isCacheValid = true;
                } else {
                    $lastUpdated = $cached->max('updated_at');
                    if ($lastUpdated && Carbon::parse($lastUpdated)->gt(now()->subHour())) {
                        $isCacheValid = true;
                    }
                }
            }

            if ($isCacheValid) {
                $list = [];
                foreach ($cached as $item) {
                    $list[] = [
                        'tanggal' => $item->tanggal,
                        'kodepoli' => $item->kodepoli,
                        'namapoli' => $item->namapoli,
                        'jumlah_antrean' => $item->jumlah_antrean,
                        'waktu_task1' => $item->waktu_task1,
                        'waktu_task2' => $item->waktu_task2,
                        'waktu_task3' => $item->waktu_task3,
                        'waktu_task4' => $item->waktu_task4,
                        'waktu_task5' => $item->waktu_task5,
                        'waktu_task6' => $item->waktu_task6,
                        'avg_waktu_task1' => $item->avg_waktu_task1,
                        'avg_waktu_task2' => $item->avg_waktu_task2,
                        'avg_waktu_task3' => $item->avg_waktu_task3,
                        'avg_waktu_task4' => $item->avg_waktu_task4,
                        'avg_waktu_task5' => $item->avg_waktu_task5,
                        'avg_waktu_task6' => $item->avg_waktu_task6,
                        'insertdate' => $item->insertdate,
                    ];
                }
                return [
                    'success' => true,
                    'data' => ['list' => $list],
                    'metadata' => [
                        'code' => 200,
                        'message' => 'Success (Loaded from DB Cache)'
                    ],
                    'status_code' => 200
                ];
            }

            $timestamp = $this->getUtcTimestamp();
            $signature = base64_encode($this->generateSignature($timestamp));
            $url = $this->baseUrl . "/dashboard/waktutunggu/bulan/{$bulan}/tahun/{$tahun}/waktu/{$waktu}";

            Log::info('Mobile JKN Get Dashboard Per Bulan Request', [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'waktu' => $waktu,
                'timestamp' => (int) $timestamp
            ]);

            $response = Http::withHeaders([
                'X-cons-id' => $this->consId,
                'X-timestamp' => $timestamp,
                'X-signature' => $signature,
                'user_key' => $this->userKey,
            ])->get($url);

            $responseData = $response->json();

            // Log to BPJS log database
            $this->bpjsLogService->logRequest(
                $response->status(),
                json_encode(['bulan' => $bulan, 'tahun' => $tahun, 'waktu' => $waktu]),
                json_encode($responseData),
                $url,
                'GET'
            );

            Log::info('Mobile JKN Get Dashboard Per Bulan Response', [
                'status' => $response->status(),
                'response' => $responseData
            ]);

            $data = [];
            if ($response->status() == 200 && isset($responseData['metadata']['code']) && $responseData['metadata']['code'] == 200 && isset($responseData['response'])) {
                if (is_string($responseData['response'])) {
                    $key = $this->generateDecryptionKey((string) $timestamp);
                    $decrypted = $this->stringDecrypt($key, $responseData['response']);
                    $decompressed = $this->decompress($decrypted);
                    $data = json_decode($decompressed, true) ?? [];
                } else {
                    $data = $responseData['response'];
                }

                if (isset($data['list']) && is_array($data['list'])) {
                    foreach ($data['list'] as $item) {
                        $itemTanggal = $item['tanggal'] ?? null;
                        if ($itemTanggal) {
                            BpjsDashboardWaktutunggu::updateOrCreate(
                                [
                                    'tanggal' => $itemTanggal,
                                    'waktu' => $waktu,
                                    'kodepoli' => $item['kodepoli'],
                                ],
                                [
                                    'namapoli' => $item['namapoli'] ?? null,
                                    'jumlah_antrean' => $item['jumlah_antrean'] ?? 0,
                                    'waktu_task1' => $item['waktu_task1'] ?? 0,
                                    'waktu_task2' => $item['waktu_task2'] ?? 0,
                                    'waktu_task3' => $item['waktu_task3'] ?? 0,
                                    'waktu_task4' => $item['waktu_task4'] ?? 0,
                                    'waktu_task5' => $item['waktu_task5'] ?? 0,
                                    'waktu_task6' => $item['waktu_task6'] ?? 0,
                                    'avg_waktu_task1' => $item['avg_waktu_task1'] ?? 0,
                                    'avg_waktu_task2' => $item['avg_waktu_task2'] ?? 0,
                                    'avg_waktu_task3' => $item['avg_waktu_task3'] ?? 0,
                                    'avg_waktu_task4' => $item['avg_waktu_task4'] ?? 0,
                                    'avg_waktu_task5' => $item['avg_waktu_task5'] ?? 0,
                                    'avg_waktu_task6' => $item['avg_waktu_task6'] ?? 0,
                                    'insertdate' => $item['insertdate'] ?? null,
                                ]
                            );
                        }
                    }
                }
            }

            return [
                'success' => $response->status() == 200 && isset($responseData['metadata']['code']) && $responseData['metadata']['code'] == 200,
                'data' => $data,
                'metadata' => $responseData['metadata'] ?? [],
                'status_code' => $response->status()
            ];

        } catch (Exception $e) {
            Log::error('Error getting dashboard per bulan from Mobile JKN', [
                'bulan' => $bulan,
                'tahun' => $tahun,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status_code' => 500
            ];
        }
    }
}
