<?php


use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\TugasController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/tugas/{kelas}', [TugasController::class, 'index'])->name('tugas.index');
    Route::post('/tugas/{kelas}', [TugasController::class, 'store'])->name('tugas.store');
    Route::get('/tugas/{kelas}/{tugas}', [TugasController::class, 'show'])->name('tugas.show');
    Route::post('/tugas/{kelas}/submission/{submission}/grade', [TugasController::class, 'grade'])->name('tugas.grade');
    Route::post('/tugas/{kelas}/{tugas}/toggle', [TugasController::class, 'toggle'])->name('tugas.toggle');
    Route::delete('/tugas/{kelas}/{tugas}', [TugasController::class, 'destroy'])->name('tugas.destroy');
    Route::get('/tugas/{kelas}/submission/{submission}/download', [TugasController::class, 'downloadSubmission'])->name('tugas.submission.download');
});