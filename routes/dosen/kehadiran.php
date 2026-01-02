<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\KehadiranController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/kehadiran', [KehadiranController::class, 'index'])->name('kehadiran.index');
    Route::post('/kehadiran', [KehadiranController::class, 'store'])->name('kehadiran.store');
    Route::post('/kehadiran/{kehadiran}/checkout', [KehadiranController::class, 'checkout'])->name('kehadiran.checkout');
});