<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/tugas/{kelas}', [\App\Http\Controllers\Mahasiswa\TugasController::class, 'index'])->name('tugas.index');
    Route::get('/tugas/{kelas}/{tugas}', [\App\Http\Controllers\Mahasiswa\TugasController::class, 'show'])->name('tugas.show');
    Route::post('/tugas/{kelas}/{tugas}/submit', [\App\Http\Controllers\Mahasiswa\TugasController::class, 'submit'])->name('tugas.submit');
    Route::get('/tugas/{kelas}/{tugas}/download', [\App\Http\Controllers\Mahasiswa\TugasController::class, 'download'])->name('tugas.download');
});