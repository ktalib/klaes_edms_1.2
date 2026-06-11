<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Phs\PhsLandingController;
use App\Http\Controllers\Phs\PhsAuthController;
use App\Http\Controllers\Phs\PhsDashboardController;
use App\Http\Controllers\Phs\PhsSlipController;
use App\Http\Controllers\Phs\PhsTokenController;
use App\Http\Controllers\Phs\PhsOrganizationController;
use App\Http\Controllers\Phs\PhsOnboardingController;

/*
|--------------------------------------------------------------------------
| PHS Portal Routes (Property History Search — Institutional SaaS)
|--------------------------------------------------------------------------
|
| Public landing + auth screens, then the authenticated portal (guard: phs).
| Staff-side administration lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('phs')->name('phs.')->group(function () {

    // ---- Public (no login required) ----
    Route::get('/', [PhsLandingController::class, 'index'])->name('landing');
    Route::get('login', [PhsAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [PhsAuthController::class, 'login'])->name('login.submit');
    Route::get('get-started', function () { return view('phs.get-started'); })->name('get_started');
    Route::get('register', [PhsAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [PhsAuthController::class, 'register'])->name('register.submit');

    // Onboarding request flow
    Route::get('request', [PhsOnboardingController::class, 'showForm'])->name('request.form');
    Route::post('request', [PhsOnboardingController::class, 'confirmPayment'])->name('request.confirm');
    Route::post('request/submit', [PhsOnboardingController::class, 'submitRequest'])->name('request.submit');
    Route::get('request/{id}/pending', [PhsOnboardingController::class, 'showPending'])->name('request.pending');
    Route::get('request/{id}/invoice', [PhsOnboardingController::class, 'downloadInvoice'])->name('request.invoice');
    Route::get('register/{token}', [PhsAuthController::class, 'showRegisterWithToken'])->name('register.token');
    Route::post('register/{token}', [PhsAuthController::class, 'registerWithToken'])->name('register.token.submit');

    // ---- Authenticated PHS members ----
    Route::middleware('auth:phs')->group(function () {
        Route::post('logout', [PhsAuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [PhsDashboardController::class, 'index'])->name('dashboard');
        Route::post('search', [PhsDashboardController::class, 'search'])->name('search');

        Route::get('slip/data', [PhsSlipController::class, 'data'])->name('slip.data');
        Route::get('slip/print', [PhsSlipController::class, 'print'])->name('slip.print');

        Route::get('tokens/transactions', [PhsTokenController::class, 'transactions'])->name('tokens.transactions');
        Route::post('tokens/pay-online', [PhsTokenController::class, 'payOnline'])->name('tokens.payOnline');
        Route::post('tokens/topup', [PhsTokenController::class, 'requestTopup'])->name('tokens.topup');
        Route::post('tokens/request-invoice', [PhsTokenController::class, 'requestInvoice'])->name('tokens.requestInvoice');

        // Organization / User Management console — super_admin only.
        Route::middleware('phs.admin')->prefix('organization')->name('org.')->group(function () {
            Route::get('/', [PhsOrganizationController::class, 'index'])->name('index');
            Route::get('members', [PhsOrganizationController::class, 'listMembers'])->name('members');
            Route::post('members', [PhsOrganizationController::class, 'storeMember'])->name('members.store');
            Route::put('members/{id}', [PhsOrganizationController::class, 'updateMember'])->name('members.update');
            Route::delete('members/{id}', [PhsOrganizationController::class, 'destroyMember'])->name('members.destroy');
            Route::get('activity', [PhsOrganizationController::class, 'activity'])->name('activity');
            Route::post('branding', [PhsOrganizationController::class, 'updateBranding'])->name('branding');
            Route::get('invoice/download', [PhsOnboardingController::class, 'downloadOrgInvoice'])->name('invoice.download');
        });
    });
});
