<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/kp', [\App\Http\Controllers\Mahasiswa\KpController::class, 'index'])->name('kp.index');
    Route::get('/kp/create', [\App\Http\Controllers\Mahasiswa\KpController::class, 'create'])->name('kp.create');
    Route::post('/kp', [\App\Http\Controllers\Mahasiswa\KpController::class, 'store'])->name('kp.store');
    Route::post('/kp/logbook', [\App\Http\Controllers\Mahasiswa\KpController::class, 'storeLogbook'])->name('kp.logbook.store');
});