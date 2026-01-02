<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
        Route::get('/jadwal', [\App\Http\Controllers\Mahasiswa\JadwalController::class, 'index'])->name('jadwal.index');
});