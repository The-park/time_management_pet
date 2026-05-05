<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::middleware(['auth'])->get('/dashboard', function () {
    return view('dashboard');
})->name('dashboard');

Route::prefix('admin')->name('admin.')->group(function () {
    Route::get('login', [AdminAuthController::class, 'showLoginForm'])->name('login');
    Route::post('login', [AdminAuthController::class, 'login'])->name('login.store');
    Route::post('logout', [AdminAuthController::class, 'logout'])->name('logout');

    Route::get('two-factor/setup', [AdminTwoFactorController::class, 'showSetup'])->name('two-factor.setup');
    Route::post('two-factor/confirm', [AdminTwoFactorController::class, 'confirm'])->name('two-factor.confirm');
    Route::get('two-factor/challenge', [AdminTwoFactorController::class, 'challenge'])->name('two-factor.challenge');
    Route::post('two-factor/challenge', [AdminTwoFactorController::class, 'verify'])->name('two-factor.verify');
});

Route::middleware(['auth'])->get('/history', function () {
    return view('history.index');
})->name('history.index');

Route::middleware(['auth'])->get('/settings', function () {
    return view('settings');
})->name('settings');
