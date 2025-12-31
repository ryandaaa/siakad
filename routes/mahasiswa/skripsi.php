<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/skripsi', [\App\Http\Controllers\Mahasiswa\SkripsiController::class, 'index'])->name('skripsi.index');
    Route::get('/skripsi/create', [\App\Http\Controllers\Mahasiswa\SkripsiController::class, 'create'])->name('skripsi.create');
    Route::post('/skripsi', [\App\Http\Controllers\Mahasiswa\SkripsiController::class, 'store'])->name('skripsi.store');
    Route::post('/skripsi/bimbingan', [\App\Http\Controllers\Mahasiswa\SkripsiController::class, 'storeBimbingan'])->name('skripsi.bimbingan.store');
});