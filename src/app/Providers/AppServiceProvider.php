<?php

namespace App\Providers;

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
        \App\Sku::observe(\App\Observers\SkuObserver::class);
        \App\User::observe(\App\Observers\UserObserver::class);
        \App\Wallet::observe(\App\Observers\WalletObserver::class);
    }
}
