<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mahasiswa\KrsController;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/krs', [KrsController::class, 'index'])->name('krs.index');
    Route::post('/krs', [KrsController::class, 'store'])->middleware('throttle:krs')->name('krs.store');
    Route::delete('/krs/{detailId}', [KrsController::class, 'destroy'])->middleware('throttle:krs')->name('krs.destroy');
    Route::post('/krs/submit', [KrsController::class, 'submit'])->middleware('throttle:krs')->name('krs.submit');
    Route::post('/krs/revise', [KrsController::class, 'revise'])->middleware('throttle:krs')->name('krs.revise');
});