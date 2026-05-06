<?php

use App\Http\Controllers\Admin\AdminAuditController;
use App\Http\Controllers\Admin\AdminAuthController;
use App\Http\Controllers\Admin\AdminDashboardController;
use App\Http\Controllers\Admin\AdminDomainController;
use App\Http\Controllers\Admin\AdminUserController;
use App\Http\Controllers\Admin\AdministratorController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TimeBlockSyncController;
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

    Route::middleware(['auth:admin'])->group(function () {
        Route::get('/', [AdminDashboardController::class, 'index'])->name('dashboard');

        Route::get('users', [AdminUserController::class, 'index'])->name('users.index');
        Route::post('users/bulk', [AdminUserController::class, 'bulk'])->name('users.bulk');
        Route::get('users/{id}', [AdminUserController::class, 'show'])->whereNumber('id')->name('users.show');
        Route::get('users/{id}/edit', [AdminUserController::class, 'edit'])->whereNumber('id')->name('users.edit');
        Route::put('users/{id}', [AdminUserController::class, 'update'])->whereNumber('id')->name('users.update');
        Route::post('users/{id}/suspend', [AdminUserController::class, 'suspend'])->whereNumber('id')->name('users.suspend');
        Route::post('users/{id}/unsuspend', [AdminUserController::class, 'unsuspend'])->whereNumber('id')->name('users.unsuspend');
        Route::post('users/{id}/verify-email', [AdminUserController::class, 'verifyEmail'])->whereNumber('id')->name('users.verify-email');
        Route::post('users/{id}/resend-verification', [AdminUserController::class, 'resendVerification'])->whereNumber('id')->name('users.resend-verification');
        Route::post('users/{id}/send-password-reset', [AdminUserController::class, 'sendPasswordReset'])->whereNumber('id')->name('users.send-password-reset');
        Route::delete('users/{id}', [AdminUserController::class, 'destroy'])->whereNumber('id')->name('users.destroy');
        Route::post('users/{id}/restore', [AdminUserController::class, 'restore'])->whereNumber('id')->name('users.restore');
        Route::delete('users/{id}/force', [AdminUserController::class, 'forceDestroy'])->whereNumber('id')->name('users.force-destroy');

        Route::get('administrators', [AdministratorController::class, 'index'])->name('administrators.index');
        Route::get('administrators/create', [AdministratorController::class, 'create'])->name('administrators.create');
        Route::post('administrators', [AdministratorController::class, 'store'])->name('administrators.store');
        Route::get('administrators/{id}/edit', [AdministratorController::class, 'edit'])->whereNumber('id')->name('administrators.edit');
        Route::put('administrators/{id}', [AdministratorController::class, 'update'])->whereNumber('id')->name('administrators.update');
        Route::delete('administrators/{id}', [AdministratorController::class, 'destroy'])->whereNumber('id')->name('administrators.destroy');

        Route::get('audit', [AdminAuditController::class, 'index'])->name('audit.index');
        Route::post('audit/prune', [AdminAuditController::class, 'prune'])->name('audit.prune');

        Route::get('domains', [AdminDomainController::class, 'index'])->name('domains.index');
        Route::post('domains', [AdminDomainController::class, 'store'])->name('domains.store');
        Route::post('domains/refresh', [AdminDomainController::class, 'refresh'])->name('domains.refresh');
        Route::delete('domains/{id}', [AdminDomainController::class, 'destroy'])->whereNumber('id')->name('domains.destroy');
    });
});

Route::middleware(['auth'])->get('/history', function () {
    return view('history.index');
})->name('history.index');

Route::middleware(['auth'])->group(function () {
    Route::post('/time-blocks/sync', [TimeBlockSyncController::class, 'sync'])->name('time-blocks.sync');

    Route::get('/goals', [GoalController::class, 'index'])->name('goals.index');
    Route::get('/goals/create', [GoalController::class, 'create'])->name('goals.create');
    Route::post('/goals', [GoalController::class, 'store'])->name('goals.store');
    Route::get('/goals/{goal}', [GoalController::class, 'show'])->whereNumber('goal')->name('goals.show');
    Route::get('/goals/{goal}/edit', [GoalController::class, 'edit'])->whereNumber('goal')->name('goals.edit');
    Route::put('/goals/{goal}', [GoalController::class, 'update'])->whereNumber('goal')->name('goals.update');
    Route::post('/goals/{goal}/extend', [GoalController::class, 'extend'])->whereNumber('goal')->name('goals.extend');
    Route::post('/goals/{goal}/keywords', [GoalController::class, 'addKeyword'])->whereNumber('goal')->name('goals.keywords.add');
    Route::post('/goals/{goal}/complete', [GoalController::class, 'complete'])->whereNumber('goal')->name('goals.complete');
    Route::post('/goals/{goal}/abandon', [GoalController::class, 'abandon'])->whereNumber('goal')->name('goals.abandon');
    Route::get('/goals/{goal}/logs', [GoalController::class, 'logs'])->whereNumber('goal')->name('goals.logs');
    Route::delete('/goals/{goal}', [GoalController::class, 'destroy'])->whereNumber('goal')->name('goals.destroy');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::get('/settings', [SettingsController::class, 'show'])->name('settings.show');
    Route::put('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::delete('/settings/account', [SettingsController::class, 'destroyAccount'])->name('account.destroy');
});
