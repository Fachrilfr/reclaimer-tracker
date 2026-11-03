<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\ChainReviewController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\HseDashboardController;
use App\Http\Controllers\Api\MachineController;
use App\Http\Controllers\Api\MasterDataController;
use App\Http\Controllers\Api\ParameterController;
use App\Http\Controllers\Api\NotificationController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\PalletController;
use App\Http\Controllers\Api\SolventUsageController;
use App\Http\Controllers\Api\SuratJalanController;
use App\Http\Controllers\Api\WasteController;
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
    Route::get('/dashboard/hse', [HseDashboardController::class, 'show'])->middleware('akses:dashboard_hse');
    Route::post('/batch-chains/{batch}/review', [ChainReviewController::class, 'store'])->middleware('akses:dashboard,tinjau');

    Route::get('/reports/reclaimer', [ReportController::class, 'reclaimer'])->middleware('akses:laporan');
    Route::get('/reports/reclaimer/export', [ReportController::class, 'exportReclaimer'])->middleware('akses:laporan');
    Route::get('/reports/reclaimer/document', [ReportController::class, 'reclaimerDocument'])->middleware('akses:laporan');
    Route::get('/reports/b3', [ReportController::class, 'b3'])->middleware('akses:laporan_b3');
    Route::get('/reports/b3/export', [ReportController::class, 'exportB3'])->middleware('akses:laporan_b3');
    Route::get('/reports/b3/document', [ReportController::class, 'b3Document'])->middleware('akses:laporan_b3');

    Route::get('/machine', [MachineController::class, 'show'])->middleware('akses:mesin');
    Route::post('/machine/maintenance', [MachineController::class, 'startMaintenance'])->middleware('akses:mesin,isi');
    Route::post('/machine/operating', [MachineController::class, 'endMaintenance'])->middleware('akses:mesin,isi');

    Route::get('/batches', [BatchController::class, 'index'])->middleware('akses:batch');
    Route::get('/batches/create', [BatchController::class, 'create'])->middleware('akses:batch,isi');
    Route::post('/batches', [BatchController::class, 'store'])->middleware('akses:batch,isi');

    Route::get('/solvent-usages', [SolventUsageController::class, 'index'])->middleware('akses:solvent');
    Route::post('/solvent-usages', [SolventUsageController::class, 'store'])->middleware('akses:solvent,isi');

    Route::get('/parameters', [ParameterController::class, 'index'])->middleware('akses:parameter');
    Route::put('/parameters', [ParameterController::class, 'update'])->middleware('akses:parameter,kelola');

    // Data master: shift dan vendor. Shift yang sudah dipakai hanya bisa dinonaktifkan.
    Route::get('/master', [MasterDataController::class, 'index'])->middleware('akses:data_master');
    Route::post('/master/shifts', [MasterDataController::class, 'storeShift'])->middleware('akses:data_master,kelola');
    Route::put('/master/shifts/{shift}', [MasterDataController::class, 'updateShift'])->middleware('akses:data_master,kelola');
    Route::delete('/master/shifts/{shift}', [MasterDataController::class, 'destroyShift'])->middleware('akses:data_master,kelola');
    Route::post('/master/vendors', [MasterDataController::class, 'storeVendor'])->middleware('akses:data_master,kelola');
    Route::put('/master/vendors/{vendor}', [MasterDataController::class, 'updateVendor'])->middleware('akses:data_master,kelola');
    Route::delete('/master/vendors/{vendor}', [MasterDataController::class, 'destroyVendor'])->middleware('akses:data_master,kelola');

    // Limbah B3 (HSE): inventaris pallet, masa simpan, dan log pengingat
    Route::get('/b3/pallets', [WasteController::class, 'index'])->middleware('akses:limbah_b3');
    Route::get('/b3/reminders', [WasteController::class, 'reminders'])->middleware('akses:limbah_b3');

    // Surat jalan ke vendor (penutup dokumen) dan arsipnya
    Route::get('/surat-jalan/create', [SuratJalanController::class, 'create'])->middleware('akses:surat_jalan');
    Route::post('/surat-jalan', [SuratJalanController::class, 'store'])->middleware('akses:surat_jalan,isi');
    Route::get('/surat-jalan', [SuratJalanController::class, 'index'])->middleware('akses:arsip');
    Route::get('/surat-jalan/{suratJalan}/file', [SuratJalanController::class, 'file'])->middleware('akses:arsip');

    Route::get('/pallets', [PalletController::class, 'index'])->middleware('akses:drum_b3');
    Route::post('/pallets', [PalletController::class, 'store'])->middleware('akses:drum_b3,isi');
    Route::post('/pallets/{pallet}/full', [PalletController::class, 'markFull'])->middleware('akses:drum_b3,isi');
});
