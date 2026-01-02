<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/dashboard', [\App\Http\Controllers\Mahasiswa\DashboardController::class, 'index'])->name('dashboard');
});