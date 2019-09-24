<?php

namespace App\Providers;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\ServiceProvider;

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
        \App\Entitlement::observe(\App\Observers\EntitlementObserver::class);
        \App\SignupCode::observe(\App\Observers\SignupCodeObserver::class);
        \App\Sku::observe(\App\Observers\SkuObserver::class);
        \App\User::observe(\App\Observers\UserObserver::class);
        \App\Wallet::observe(\App\Observers\WalletObserver::class);

        // Log SQL queries in debug mode
        if (env('APP_DEBUG')) {
            DB::listen(function($query) {
                File::append(
                    storage_path('/logs/sql.log'),
                    $query->sql . ' [' . implode(', ', $query->bindings) . ']' . PHP_EOL
                );
            });
        }
    }
}
