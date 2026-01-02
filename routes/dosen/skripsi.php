<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\SkripsiController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/skripsi', [SkripsiController::class, 'index'])->name('skripsi.index');
    Route::get('/skripsi/{skripsi}', [SkripsiController::class, 'show'])->name('skripsi.show');
    Route::post('/skripsi/bimbingan/{bimbingan}/review', [SkripsiController::class, 'reviewBimbingan'])->name('skripsi.bimbingan.review');
    Route::put('/skripsi/{skripsi}/status', [SkripsiController::class, 'updateStatus'])->name('skripsi.update-status');
});