<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'role:mahasiswa'])->prefix('mahasiswa')->name('mahasiswa.')->group(function () {
    Route::get('/ai-advisor', [\App\Http\Controllers\Mahasiswa\AiAdvisorController::class, 'index'])->name('ai-advisor.index');
    Route::post('/ai-advisor/chat', [\App\Http\Controllers\Mahasiswa\AiAdvisorController::class, 'chat'])->middleware('throttle:ai-chat')->name('ai-advisor.chat');
});