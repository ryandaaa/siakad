<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/export/transkrip', [\App\Http\Controllers\Mahasiswa\ExportController::class, 'transkrip'])->name('export.transkrip');
    Route::get('/export/khs/{tahunAkademik}', [\App\Http\Controllers\Mahasiswa\ExportController::class, 'khs'])->name('export.khs');
});