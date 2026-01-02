<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/presensi', [\App\Http\Controllers\Mahasiswa\PresensiController::class, 'index'])->name('presensi.index');
    Route::get('/presensi/{kelas}', [\App\Http\Controllers\Mahasiswa\PresensiController::class, 'show'])->name('presensi.show');
});