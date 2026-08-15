<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Laas\LaasApplicationController;
use App\Http\Controllers\Laas\LaasAuthController;
use App\Http\Controllers\Laas\LaasDashboardController;
use App\Http\Controllers\Laas\LaasLandingController;

/*
|--------------------------------------------------------------------------
| LAAS Portal Routes (Land Allocation Application System)
|--------------------------------------------------------------------------
|
| Public landing + auth screens, then the applicant portal (guard: laas).
| This whole file sits OUTSIDE staff admin auth, exactly as routes/phs.php does.
|
| The staff side of the same workflow — the Director's review queue and MLP's
| file-number desk — lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('laas')->name('laas.')->group(function () {

    // ---- Public (no login required) ----
    Route::get('/', [LaasLandingController::class, 'index'])->name('landing');
    Route::get('login', [LaasAuthController::class, 'showLogin'])->name('login');
    Route::post('login', [LaasAuthController::class, 'login'])->name('login.submit');
    Route::get('register', [LaasAuthController::class, 'showRegister'])->name('register');
    Route::post('register', [LaasAuthController::class, 'register'])->name('register.submit');

    // ---- Authenticated applicants ----
    Route::middleware('auth:laas')->group(function () {
        Route::post('logout', [LaasAuthController::class, 'logout'])->name('logout');

        Route::get('dashboard', [LaasDashboardController::class, 'index'])->name('dashboard');
        Route::get('notifications', [LaasDashboardController::class, 'notifications'])->name('notifications');

        // Application form (spec a) and submission (spec b).
        Route::get('apply', [LaasApplicationController::class, 'form'])->name('apply.form');
        Route::post('apply/draft', [LaasApplicationController::class, 'saveDraft'])->name('apply.draft');
        Route::post('apply', [LaasApplicationController::class, 'store'])->name('apply.store');

        // Status page and its documents.
        Route::get('application/{reference}', [LaasApplicationController::class, 'show'])->name('application.show');
        Route::post('application/{reference}/documents', [LaasApplicationController::class, 'uploadDocument'])->name('application.documents.upload');
        Route::get('application/{reference}/documents/{document}', [LaasApplicationController::class, 'downloadDocument'])->name('application.documents.download');

        // Lookup data for the form's dependent dropdowns. The portal's own gated
        // copy of the internal /api/reference/* endpoints — same tested
        // controller, behind auth:laas. Districts are fetched per-LGA rather
        // than rendered into the page: the full table is ~1,800 rows and
        // inlining it into several selects is what makes the OSS applications
        // page unusable.
        Route::prefix('api/reference')->name('api.reference.')
            ->controller(\App\Http\Controllers\ReferenceDataController::class)
            ->group(function () {
                Route::get('lgas', 'lgas')->name('lgas');
                Route::get('districts', 'districts')->name('districts');
                Route::get('land-uses', 'landUses')->name('land-uses');
                Route::get('purposes', 'purposes')->name('purposes');
            });
    });
});
