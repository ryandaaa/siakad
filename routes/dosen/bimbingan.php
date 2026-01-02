<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\BimbinganController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/bimbingan', [BimbinganController::class, 'index'])->name('bimbingan.index');
    Route::get('/bimbingan/krs-approval', [BimbinganController::class, 'krsApproval'])->name('bimbingan.krs-approval');
    Route::get('/bimbingan/krs/{krs}', [BimbinganController::class, 'showKrs'])->name('bimbingan.krs-show');
    Route::post('/bimbingan/krs/{krs}/approve', [BimbinganController::class, 'approveKrs'])->name('bimbingan.krs-approve');
    Route::post('/bimbingan/krs/{krs}/reject', [BimbinganController::class, 'rejectKrs'])->name('bimbingan.krs-reject');
});