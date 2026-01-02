<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/lms', [\App\Http\Controllers\Mahasiswa\LmsController::class, 'index'])->name('lms.index');
});