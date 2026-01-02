<?php

use Illuminate\Support\Facades\Route;

// Redirect root to login page
Route::get('/', fn() => redirect()->route('login'));

// Redirect generic dashboard to role-specific dashboard
Route::get('/dashboard', function () {
    $routes = [
        'superadmin'     => 'admin.dashboard',
        'admin_fakultas' => 'admin.dashboard',
        'dosen'          => 'dosen.dashboard',
        'mahasiswa'      => 'mahasiswa.dashboard',
    ];

    $role = auth()->user()->role;

    return redirect()->route($routes[$role] ?? 'login');
})->middleware(['auth', 'verified'])->name('dashboard');


require __DIR__.'/auth.php';
require __DIR__.'/health.php';
require __DIR__.'/notification.php';
require __DIR__.'/profile.php';
require __DIR__.'/admin/index.php';
require __DIR__.'/dosen/index.php';
require __DIR__.'/mahasiswa/index.php';
