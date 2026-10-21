<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ChainReviewController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PalletController;
use App\Http\Controllers\Api\SolventUsageController;
use Illuminate\Support\Facades\Route;

// Public Auth Routes (Rate limited: maks 10 request per menit untuk proteksi brute force)
Route::prefix('auth')->group(function () {
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
});

// Protected Routes. Route modul memakai middleware akses per fitur,
// misalnya ->middleware('akses:batch,isi'). Lihat config/access.php.
Route::middleware(['auth:sanctum', 'aktif'])->group(function () {
    Route::get('/auth/me', [AuthController::class, 'me']);
    Route::post('/auth/logout', [AuthController::class, 'logout']);

    // Lonceng dan banner: semua pengguna yang login
    Route::get('/notifications', [NotificationController::class, 'index']);
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead']);
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead']);

    Route::get('/dashboard', [DashboardController::class, 'show'])->middleware('akses:dashboard');
    Route::post('/batch-chains/{batch}/review', [ChainReviewController::class, 'store'])->middleware('akses:dashboard,tinjau');

    Route::get('/reports/reclaimer', [ReportController::class, 'reclaimer'])->middleware('akses:laporan');
    Route::get('/reports/reclaimer/export', [ReportController::class, 'exportReclaimer'])->middleware('akses:laporan');

    Route::get('/machine', [MachineController::class, 'show'])->middleware('akses:mesin');
    Route::post('/machine/maintenance', [MachineController::class, 'startMaintenance'])->middleware('akses:mesin,isi');
    Route::post('/machine/operating', [MachineController::class, 'endMaintenance'])->middleware('akses:mesin,isi');

    Route::get('/batches', [BatchController::class, 'index'])->middleware('akses:batch');
    Route::get('/batches/create', [BatchController::class, 'create'])->middleware('akses:batch,isi');
    Route::post('/batches', [BatchController::class, 'store'])->middleware('akses:batch,isi');

    Route::get('/solvent-usages', [SolventUsageController::class, 'index'])->middleware('akses:solvent');
    Route::post('/solvent-usages', [SolventUsageController::class, 'store'])->middleware('akses:solvent,isi');

    Route::get('/pallets',[PalletController::class, 'index'])->middleware('akses:drum_b3');
    Route::post('/pallets', [PalletController::class, 'store'])->middleware('akses:drum_b3,isi');
    Route::post('/pallets/{pallet}/drums', [PalletController::class, 'storeDrum'])->middleware('akses:drum_b3,isi');
});
