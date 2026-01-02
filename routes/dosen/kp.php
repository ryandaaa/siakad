<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\KpController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
   Route::get('/kp', [KpController::class, 'index'])->name('kp.index');
    Route::get('/kp/{kp}', [KpController::class, 'show'])->name('kp.show');
    Route::post('/kp/logbook/{logbook}/review', [KpController::class, 'reviewLogbook'])->name('kp.logbook.review');
    Route::put('/kp/{kp}/status', [KpController::class, 'updateStatus'])->name('kp.update-status');
});