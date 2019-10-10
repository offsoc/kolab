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
        \App\Domain::observe(\App\Observers\DomainObserver::class);
        \App\Entitlement::observe(\App\Observers\EntitlementObserver::class);
        \App\SignupCode::observe(\App\Observers\SignupCodeObserver::class);
        \App\Sku::observe(\App\Observers\SkuObserver::class);
        \App\User::observe(\App\Observers\UserObserver::class);
        \App\Wallet::observe(\App\Observers\WalletObserver::class);

        // Log SQL queries in debug mode
            DB::listen(function ($query) {
                \Log::debug(sprintf('[SQL] %s [%s]', $query->sql, implode(', ', $query->bindings)));
            });
    }
}
