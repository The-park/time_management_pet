<?php

use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminTwoFactorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use Illuminate\Support\Facades\Route;

// Dashboard is the public homepage. Authenticated users get personalised
// data; guests see the same UI but only the Custom countdown is interactive,
// every other action prompts them to sign in.
Route::get('/', function () {
    return view('dashboard');
})->name('dashboard');

// Backwards-compat: anything still pointing at /dashboard lands on /.
Route::get('/dashboard', function () {
    return redirect('/');
});

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

Route::middleware(['auth'])->group(function () {
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])->name('account.destroy');
});
