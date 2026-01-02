<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Dosen\LmsController;

Route::middleware(['auth', 'role:dosen'])->prefix('dosen')->name('dosen.')->group(function () {
    Route::get('/lms', [LmsController::class, 'index'])->name('lms.index');
});