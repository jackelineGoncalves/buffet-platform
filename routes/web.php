<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return redirect(match (auth()->user()->role) {
        'admin' => '/admin',
        'kitchen' => '/kitchen',
        'floor' => '/floor',
    });
})->middleware('auth')->name('dashboard');

Route::middleware(['auth', 'role:admin'])->get('/admin', function () {
    return Inertia::render('Dashboard');
})->name('admin.dashboard');

Route::middleware(['auth', 'role:kitchen'])->get('/kitchen', function () {
    return Inertia::render('Dashboard');
})->name('kitchen.dashboard');

Route::middleware(['auth', 'role:floor'])->get('/floor', function () {
    return Inertia::render('Dashboard');
})->name('floor.dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::get('/table/{code}', function (string $code) {
    return Inertia::render('Diner/Table', [
        'code' => $code,
    ]);
})->name('diner.table');

require __DIR__.'/auth.php';
