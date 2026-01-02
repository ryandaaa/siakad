<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\MateriController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/materi/{kelas}', [MateriController::class, 'index'])->name('materi.index');
    Route::post('/materi/{kelas}', [MateriController::class, 'store'])->name('materi.store');
    Route::put('/materi/{kelas}/{materi}', [MateriController::class, 'update'])->name('materi.update');
    Route::delete('/materi/{kelas}/{materi}', [MateriController::class, 'destroy'])->name('materi.destroy');
    Route::get('/materi/{kelas}/download/{materi}', [MateriController::class, 'download'])->name('materi.download');
});