<?php

use App\Http\Controllers\Api\AuthController;
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
});
