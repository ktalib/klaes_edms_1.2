<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        // Use custom PersonalAccessToken model so Sanctum reads tokens from sqlsrv
        Sanctum::usePersonalAccessTokenModel(\App\Models\PersonalAccessToken::class);

        // Only set default string length for MySQL connections
        if (config('database.default') === 'mysql') {
            Schema::defaultStringLength(191);
        }
    }
}
