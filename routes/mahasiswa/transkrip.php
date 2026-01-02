<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Mahasiswa\TranskripController;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/transkrip', [TranskripController::class, 'index'])->name('transkrip.index');
});