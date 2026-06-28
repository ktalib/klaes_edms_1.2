<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnlineLegalSearch\OnlineLsAuthController;
use App\Http\Controllers\OnlineLegalSearch\OnlineLsDashboardController;

/*
|--------------------------------------------------------------------------
| Online Legal Search Portal Routes
|--------------------------------------------------------------------------
|
| Public landing + auth screens, then the authenticated portal (guard: online_ls).
| Staff-side administration lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('online-legal-search')->name('ols.')->group(function () {

    // ---- Public (no login required) ----
    Route::get('/', [OnlineLsAuthController::class, 'showLanding'])->name('landing');
    Route::get('login', [OnlineLsAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [OnlineLsAuthController::class, 'login'])->name('login.submit');
    Route::get('register', [OnlineLsAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [OnlineLsAuthController::class, 'register'])->name('register.submit');
    Route::get('register/verify/{token}', [OnlineLsAuthController::class, 'verifyEmail'])->name('verify');
    Route::post('password/email', [OnlineLsAuthController::class, 'sendResetLink'])->name('password.email');
    Route::get('password/reset/{token}', [OnlineLsAuthController::class, 'showResetForm'])->name('password.reset');
    Route::post('password/reset', [OnlineLsAuthController::class, 'resetPassword'])->name('password.update');

    // ---- Authenticated Online LS users ----
    Route::middleware('auth:online_ls')->group(function () {
        Route::post('logout', [OnlineLsAuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [OnlineLsDashboardController::class, 'index'])->name('dashboard');
        Route::post('search', [OnlineLsDashboardController::class, 'search'])->name('search');
        Route::post('payment/verify', [OnlineLsDashboardController::class, 'verifyPayment'])->name('payment.verify');
        Route::get('slip/data', [OnlineLsDashboardController::class, 'slipData'])->name('slip.data');
        Route::get('slip/print', [OnlineLsDashboardController::class, 'slipPrint'])->name('slip.print');
        Route::get('search/history', [OnlineLsDashboardController::class, 'history'])->name('history');
        Route::post('feedback', [OnlineLsDashboardController::class, 'feedback'])->name('feedback.store');
    });
});
