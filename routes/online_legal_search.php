<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OnlineLegalSearch\OnlineLsDashboardController;

/*
|--------------------------------------------------------------------------
| Online Legal Search Portal Routes
|--------------------------------------------------------------------------
|
| Fully public, no accounts. Anyone can run a Legal Search, pay, and view the
| full report without logging in or registering. Each payment is tracked
| back-office by a generated tracking id (e.g. USER-0001).
| Staff-side administration lives in routes/app3.php under the staff `auth` guard.
|
*/

Route::prefix('online-legal-search')->name('ols.')->group(function () {

    // Public landing page.
    Route::get('/', [OnlineLsDashboardController::class, 'landing'])->name('landing');

    // Public search: anyone can run a Legal Search and see a preview/summary.
    Route::post('search', [OnlineLsDashboardController::class, 'search'])->name('search');

    // Public Select2 autocomplete for file numbers (queries file_indexings).
    Route::get('file-numbers', [OnlineLsDashboardController::class, 'fileNumbers'])->name('file-numbers');

    // Public full result. Shows the Paystack checkout until a verified payment
    // exists for this file number; the report then opens via its payment reference.
    Route::get('result', [OnlineLsDashboardController::class, 'result'])->name('result');
    Route::post('payment/verify', [OnlineLsDashboardController::class, 'verifyPayment'])->name('payment.verify');
});
