<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        \App\Discount::observe(\App\Observers\DiscountObserver::class);
        \App\Domain::observe(\App\Observers\DomainObserver::class);
        \App\Entitlement::observe(\App\Observers\EntitlementObserver::class);
        \App\Package::observe(\App\Observers\PackageObserver::class);
        \App\PackageSku::observe(\App\Observers\PackageSkuObserver::class);
        \App\Plan::observe(\App\Observers\PlanObserver::class);
        \App\SignupCode::observe(\App\Observers\SignupCodeObserver::class);
        \App\Sku::observe(\App\Observers\SkuObserver::class);
        \App\Transaction::observe(\App\Observers\TransactionObserver::class);
        \App\User::observe(\App\Observers\UserObserver::class);
        \App\UserAlias::observe(\App\Observers\UserAliasObserver::class);
        \App\UserSetting::observe(\App\Observers\UserSettingObserver::class);
        \App\VerificationCode::observe(\App\Observers\VerificationCodeObserver::class);
        \App\Wallet::observe(\App\Observers\WalletObserver::class);

        Schema::defaultStringLength(191);

        // Log SQL queries in debug mode
        if (\config('app.debug')) {
            DB::listen(function ($query) {
                \Log::debug(sprintf('[SQL] %s [%s]', $query->sql, implode(', ', $query->bindings)));
            });
        }

        // Register some template helpers
        Blade::directive('theme_asset', function ($path) {
            $path = trim($path, '/\'"');
            return "<?php echo secure_asset('themes/' . \$env['app.theme'] . '/' . '$path'); ?>";
        });
    }
}
