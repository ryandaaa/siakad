<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/materi/{kelas}', [\App\Http\Controllers\Mahasiswa\MateriController::class, 'index'])->name('materi.index');
    Route::get('/materi/{kelas}/download/{materi}', [\App\Http\Controllers\Mahasiswa\MateriController::class, 'download'])->name('materi.download');
});