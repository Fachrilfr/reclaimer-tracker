<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BatchController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\PalletController;
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

    Route::get('/dashboard', [DashboardController::class, 'show'])->middleware('akses:dashboard');

    Route::get('/batches', [BatchController::class, 'index'])->middleware('akses:batch');
    Route::get('/batches/create', [BatchController::class, 'create'])->middleware('akses:batch,isi');
    Route::post('/batches', [BatchController::class, 'store'])->middleware('akses:batch,isi');

    Route::get('/pallets', [PalletController::class, 'index'])->middleware('akses:drum_b3');
    Route::post('/pallets', [PalletController::class, 'store'])->middleware('akses:drum_b3,isi');
    Route::post('/pallets/{pallet}/drums', [PalletController::class, 'storeDrum'])->middleware('akses:drum_b3,isi');
});
