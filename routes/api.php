<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MobileJknController;
use App\Http\Controllers\RegPeriksaController;
use App\Http\Controllers\BpjsLogController;
use App\Http\Controllers\FlowAnalyticsController;
use App\Http\Controllers\CommandOutputController;
use App\Http\Controllers\AuthController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('v1')->group(function () {

    // ── Public Auth Endpoint ─────────────────────────────────
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle.login');

    // ── Protected API Endpoints ──────────────────────────────
    Route::middleware(['auth.external', 'throttle:120,1'])->group(function () {

        // ── Mobile JKN API ──────────────────────────────────────
        Route::prefix('mobilejkn')->group(function () {
            Route::get('/booking-details/{identifier}', [MobileJknController::class, 'getBookingDetails'])->where('identifier', '.*');
            Route::get('/antrean/pendaftaran/kodebooking/{identifier}', [MobileJknController::class, 'getBookingDetails'])->where('identifier', '.*');
            Route::post('/update-task-id', [MobileJknController::class, 'updateTaskId']);
            Route::post('/update-task-id-by-no-rawat', [MobileJknController::class, 'updateTaskIdByNoRawat']);
            Route::post('/update-task-id-from-db', [MobileJknController::class, 'updateTaskIdFromDatabase']);
            Route::post('/update-task-id-now', [MobileJknController::class, 'updateTaskIdNow']);
            Route::post('/batch-update-task-ids', [MobileJknController::class, 'batchUpdateTaskIds']);
            Route::post('/batal-antrean', [MobileJknController::class, 'batalAntrean']);
            Route::post('/reconcile-cancellations', [MobileJknController::class, 'reconcileCancellations']);
            Route::get('/task-id-logs', [MobileJknController::class, 'getTaskIdLogs']);
            Route::get('/filtered-task-id-logs', [MobileJknController::class, 'getFilteredTaskIdLogs']);
            Route::get('/get-patient-data/{reg_no}', [MobileJknController::class, 'getPatientData']);
            Route::get('/antrean-logs', [MobileJknController::class, 'getAntreanAddLogs']);
        });

        // ── Antrian API ─────────────────────────────────────────
        Route::get('/antrean/pendaftaran/kodebooking/{identifier}', [MobileJknController::class, 'getBookingDetails'])->where('identifier', '.*');
        Route::post('/antrian', [MobileJknController::class, 'sendAntrian']);

        // ── Reg Periksa API ─────────────────────────────────────
        Route::prefix('regperiksa')->group(function () {
            Route::get('/today-bpjs', [RegPeriksaController::class, 'getTodayBpjsPatients']);
            Route::get('/filtered', [RegPeriksaController::class, 'getFilteredPatients']);
            Route::get('/statistics', [RegPeriksaController::class, 'getStatistics']);
            Route::get('/patient', [RegPeriksaController::class, 'getPatient']);
            Route::get('/by-status', [RegPeriksaController::class, 'getPatientsByStatus']);
            Route::get('/by-doctor', [RegPeriksaController::class, 'getPatientsByDoctor']);
            Route::get('/by-polyclinic', [RegPeriksaController::class, 'getPatientsByPolyclinic']);
            Route::get('/date-range', [RegPeriksaController::class, 'getPatientsByDateRange']);
        });

        // ── BPJS Logs API ───────────────────────────────────────
        Route::prefix('bpjs-logs')->group(function () {
            Route::get('/', [BpjsLogController::class, 'getLogs']);
            Route::get('/by-date-range', [BpjsLogController::class, 'getLogsByDateRange']);
            Route::get('/by-code', [BpjsLogController::class, 'getLogsByCode']);
            Route::get('/by-task', [BpjsLogController::class, 'getLogsByTask']);
        });

        // ── Command Output API (Retained for potential future use) ──
        Route::prefix('command-output')->group(function () {
            Route::get('/', [CommandOutputController::class, 'getOutputs']);
            Route::get('/by-date-range', [CommandOutputController::class, 'getOutputsByDateRange']);
            Route::get('/by-code', [CommandOutputController::class, 'getOutputsByCode']);
            Route::get('/by-task', [CommandOutputController::class, 'getOutputsByTask']);
        });

        // ── Flow Monitoring API ─────────────────────────────────
        Route::prefix('monitoring')->group(function () {
            Route::get('/analytics', [FlowAnalyticsController::class, 'getAnalyticsData']);
            Route::get('/clinic/{nmPoli}', [FlowAnalyticsController::class, 'getClinicDetail']);
            Route::get('/patient/{noRawat}', [FlowAnalyticsController::class, 'getPatientDetail'])->where('noRawat', '.*');
            Route::get('/verify/{noRawat}', [FlowAnalyticsController::class, 'verifyBpjs'])->where('noRawat', '.*');
            Route::get('/list-task-booking/{kodebooking?}', [FlowAnalyticsController::class, 'getListTaskByKodeBooking'])->where('kodebooking', '.*');
            Route::post('/list-task-booking', [FlowAnalyticsController::class, 'getListTaskByKodeBooking']);
            Route::get('/bpjs-dashboard/tanggal', [FlowAnalyticsController::class, 'getBpjsDashboardTanggal']);
            Route::get('/bpjs-dashboard/bulan', [FlowAnalyticsController::class, 'getBpjsDashboardBulan']);
            Route::post('/sync-patient', [FlowAnalyticsController::class, 'syncPatient']);
            Route::post('/sync-batch', [FlowAnalyticsController::class, 'syncBatch']);
            Route::post('/sync-today', [FlowAnalyticsController::class, 'syncToday']);
            Route::post('/sync-range', [FlowAnalyticsController::class, 'syncRange']);
            Route::get('/sync-status', [FlowAnalyticsController::class, 'syncStatus']);
        });
    });
});
