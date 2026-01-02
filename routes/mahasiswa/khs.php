<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/khs', [\App\Http\Controllers\Mahasiswa\KhsController::class, 'index'])->name('khs.index');
    Route::get('/khs/{tahunAkademik}', [\App\Http\Controllers\Mahasiswa\KhsController::class, 'show'])->name('khs.show');
});