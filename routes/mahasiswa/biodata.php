<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/biodata', [\App\Http\Controllers\Mahasiswa\BiodataController::class, 'index'])->name('biodata.index');
    Route::put('/biodata', [\App\Http\Controllers\Mahasiswa\BiodataController::class, 'update'])->name('biodata.update');
    Route::put('/biodata/password', [\App\Http\Controllers\Mahasiswa\BiodataController::class, 'updatePassword'])->name('biodata.password');
});