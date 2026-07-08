<?php

use App\Http\Controllers\Diner\DinerOrderController;
use App\Http\Controllers\Diner\DinerServiceRequestController;
use App\Http\Controllers\Diner\DinerSessionController;
use App\Http\Controllers\Diner\DinerTableController;
use App\Http\Controllers\Admin\AdminDishController;
use App\Http\Controllers\Admin\AdminIndexController;
use App\Http\Controllers\Admin\AdminSettingController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Floor\FloorIndexController;
use App\Http\Controllers\Floor\FloorOrderItemController;
use App\Http\Controllers\Floor\FloorPaymentController;
use App\Http\Controllers\Floor\FloorServiceRequestController;
use App\Http\Controllers\Floor\FloorWasteController;
use App\Http\Controllers\Kitchen\KitchenIndexController;
use App\Http\Controllers\Kitchen\KitchenOrderItemController;
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

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', [AdminIndexController::class, 'index'])->name('index');

    Route::get('/dishes', [AdminDishController::class, 'index'])->name('dishes.index');
    Route::post('/dishes', [AdminDishController::class, 'store'])->name('dishes.store');
    Route::put('/dishes/{dish}', [AdminDishController::class, 'update'])->name('dishes.update');
    Route::patch('/dishes/{dish}/toggle', [AdminDishController::class, 'toggle'])->name('dishes.toggle');
    Route::delete('/dishes/{dish}', [AdminDishController::class, 'destroy'])->name('dishes.destroy');

    Route::get('/settings', [AdminSettingController::class, 'edit'])->name('settings.edit');
    Route::put('/settings', [AdminSettingController::class, 'update'])->name('settings.update');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::patch('/users/{user}/role', [AdminUserController::class, 'updateRole'])->name('users.update-role');
});

Route::middleware(['auth', 'role:kitchen,admin'])->prefix('kitchen')->name('kitchen.')->group(function () {
    Route::get('/', [KitchenIndexController::class, 'index'])->name('index');
    Route::patch('/order-items/{orderItem}', [KitchenOrderItemController::class, 'advance'])->name('order-items.advance');
});

Route::middleware(['auth', 'role:floor,admin'])->prefix('floor')->name('floor.')->group(function () {
    Route::get('/', [FloorIndexController::class, 'index'])->name('index');
    Route::patch('/order-items/{orderItem}/serve', [FloorOrderItemController::class, 'serve'])->name('order-items.serve');
    Route::patch('/service-requests/{serviceRequest}/resolve', [FloorServiceRequestController::class, 'resolve'])->name('service-requests.resolve');
    Route::post('/dining-sessions/{session}/payment', [FloorPaymentController::class, 'store'])->name('dining-sessions.payment');
    Route::patch('/dining-sessions/{session}/waste', [FloorWasteController::class, 'update'])->name('dining-sessions.waste');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

Route::post('/table/{code}/session', [DinerSessionController::class, 'store'])->name('diner.session.store');
Route::post('/table/{code}/orders', [DinerOrderController::class, 'store'])->name('diner.orders.store');
Route::post('/table/{code}/service-requests', [DinerServiceRequestController::class, 'store'])->name('diner.service-requests.store');

Route::get('/table/{code}', [DinerTableController::class, 'show'])->name('diner.table');

require __DIR__.'/auth.php';
