<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\PenilaianController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/penilaian', [PenilaianController::class, 'index'])->name('penilaian.index');
    Route::get('/penilaian/{kelas}', [PenilaianController::class, 'show'])->name('penilaian.show');
    Route::post('/penilaian/{kelas}', [PenilaianController::class, 'store'])->middleware('throttle:penilaian')->name('penilaian.store');
});