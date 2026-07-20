<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\MobileJknController;
use App\Http\Controllers\RegPeriksaController;
use App\Http\Controllers\BpjsLogController;
use App\Http\Controllers\CommandOutputController;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/time', function() {
    echo now();
});

// Mobile JKN API Routes
Route::prefix('api/mobilejkn')->group(function () {
    Route::post('/update-task-id', [MobileJknController::class, 'updateTaskId']);
    Route::post('/update-task-id-from-db', [MobileJknController::class, 'updateTaskIdFromDatabase']);
    Route::post('/update-task-id-now', [MobileJknController::class, 'updateTaskIdNow']);
    Route::post('/batch-update-task-ids', [MobileJknController::class, 'batchUpdateTaskIds']);
    Route::post('/batal-antrean', [MobileJknController::class, 'batalAntrean']);
    Route::get('/task-id-logs', [MobileJknController::class, 'getTaskIdLogs']);
    Route::get('/filtered-task-id-logs', [MobileJknController::class, 'getFilteredTaskIdLogs']);
    Route::get('/get-patient-data/{reg_no}', [MobileJknController::class, 'getPatientData']);
    Route::get('/antrean-logs', [MobileJknController::class, 'getAntreanAddLogs']);
});

// RegPeriksa Routes
Route::prefix('regperiksa')->group(function () {
    Route::get('/', [RegPeriksaController::class, 'index'])->name('regperiksa.index');
});

// Mobile JKN Routes
Route::prefix('mobilejkn')->group(function () {
    Route::get('/taskid-logs', [MobileJknController::class, 'taskIdLogs'])->name('taskid.logs');
    Route::get('/run-command', [CommandOutputController::class, 'index'])->name('command.index');
    Route::get('/patient-data', [MobileJknController::class, 'showPatientDataForm'])->name('patient.data');
    Route::get('/referensi-pendafataran', [MobileJknController::class, 'referensiPendafataran'])->name('referensi.pendafataran');
    Route::post('/referensi-pendafataran', [MobileJknController::class, 'updateReferensiStatus'])->name('referensi.update-status');
});

// Command Output Routes
Route::post('/run-command', [CommandOutputController::class, 'runCommand'])->name('command.run');
Route::post('/stop-command', [CommandOutputController::class, 'stopCommand'])->name('command.stop');
Route::get('/command-output/{jobId}', [CommandOutputController::class, 'getOutput'])->name('command.output');
Route::get('/get-task-ids', [CommandOutputController::class, 'getTaskIds'])->name('command.task-ids');
Route::get('/debug-command-cache/{jobId?}', [CommandOutputController::class, 'debugCache'])->name('command.debug');
Route::get('/log-viewer', [CommandOutputController::class, 'showLogViewer'])->name('log.viewer');
Route::get('/stream-logs', [CommandOutputController::class, 'streamLogs'])->name('logs.stream');
Route::get('/recent-logs/{lines?}', [CommandOutputController::class, 'getRecentLogs'])->name('logs.recent');
// Execution tracking (detailed API and streaming)
Route::get('/execution-details/{jobId}', [CommandOutputController::class, 'getDetailedExecution'])->name('execution.details.api');
Route::get('/stream-execution/{jobId}', [CommandOutputController::class, 'streamTaskExecution'])->name('execution.stream');

// Execution details view
Route::get('/execution-viewer/{jobId}', [CommandOutputController::class, 'showExecutionViewer'])->name('execution.viewer');

// RegPeriksa API Routes
Route::prefix('api/regperiksa')->group(function () {
    Route::get('/today-bpjs', [RegPeriksaController::class, 'getTodayBpjsPatients']);
    Route::get('/filtered', [RegPeriksaController::class, 'getFilteredPatients']);
    Route::get('/statistics', [RegPeriksaController::class, 'getStatistics']);
    Route::get('/patient', [RegPeriksaController::class, 'getPatient']);
    Route::get('/by-status', [RegPeriksaController::class, 'getPatientsByStatus']);
    Route::get('/by-doctor', [RegPeriksaController::class, 'getPatientsByDoctor']);
    Route::get('/by-polyclinic', [RegPeriksaController::class, 'getPatientsByPolyclinic']);
    Route::get('/date-range', [RegPeriksaController::class, 'getPatientsByDateRange']);
});

// BPJS Log Routes
Route::prefix('bpjs-logs')->group(function () {
    Route::get('/', [BpjsLogController::class, 'index'])->name('bpjs-logs.index');
});

// BPJS Log API Routes
Route::prefix('api/bpjs-logs')->group(function () {
    Route::get('/', [BpjsLogController::class, 'getLogs']);
    Route::get('/by-date-range', [BpjsLogController::class, 'getLogsByDateRange']);
    Route::get('/by-code', [BpjsLogController::class, 'getLogsByCode']);
    Route::get('/by-task', [BpjsLogController::class, 'getLogsByTask']);
});

// Command Output API Routes
Route::prefix('api/command-output')->group(function () {
    Route::get('/', [CommandOutputController::class, 'getOutputs']);
    Route::get('/by-date-range', [CommandOutputController::class, 'getOutputsByDateRange']);
    Route::get('/by-code', [CommandOutputController::class, 'getOutputsByCode']);
    Route::get('/by-task', [CommandOutputController::class, 'getOutputsByTask']);
});
Route::post('/api/mobilejkn/update-task-id', [MobileJknController::class, 'updateTaskId']);
Route::post('/api/antrian', [App\Http\Controllers\MobileJknController::class, 'sendAntrian']);

// Flow Monitoring Routes
Route::get('/monitoring', [App\Http\Controllers\FlowAnalyticsController::class, 'index'])->name('monitoring.index');
Route::get('/monitoring/print', [App\Http\Controllers\FlowAnalyticsController::class, 'print'])->name('monitoring.print');
Route::prefix('api/monitoring')->group(function () {
    Route::get('/analytics', [App\Http\Controllers\FlowAnalyticsController::class, 'getAnalyticsData']);
    Route::get('/clinic/{nmPoli}', [App\Http\Controllers\FlowAnalyticsController::class, 'getClinicDetail']);
    Route::get('/patient/{noRawat}', [App\Http\Controllers\FlowAnalyticsController::class, 'getPatientDetail'])->where('noRawat', '.*');
    Route::get('/verify/{noRawat}', [App\Http\Controllers\FlowAnalyticsController::class, 'verifyBpjs'])->where('noRawat', '.*');
    Route::get('/list-task-booking/{kodebooking?}', [App\Http\Controllers\FlowAnalyticsController::class, 'getListTaskByKodeBooking'])->where('kodebooking', '.*');
    Route::post('/list-task-booking', [App\Http\Controllers\FlowAnalyticsController::class, 'getListTaskByKodeBooking']);
    Route::get('/bpjs-dashboard/tanggal', [App\Http\Controllers\FlowAnalyticsController::class, 'getBpjsDashboardTanggal']);
    Route::get('/bpjs-dashboard/bulan', [App\Http\Controllers\FlowAnalyticsController::class, 'getBpjsDashboardBulan']);
    Route::post('/sync-patient', [App\Http\Controllers\FlowAnalyticsController::class, 'syncPatient']);
    Route::post('/sync-batch', [App\Http\Controllers\FlowAnalyticsController::class, 'syncBatch']);
    Route::post('/sync-today', [App\Http\Controllers\FlowAnalyticsController::class, 'syncToday']);
    Route::post('/sync-range', [App\Http\Controllers\FlowAnalyticsController::class, 'syncRange']);
    Route::get('/sync-status', [App\Http\Controllers\FlowAnalyticsController::class, 'syncStatus']);
});
