<?php

namespace App\Providers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // This must be here, not in PassportServiceProvider
        Passport::ignoreRoutes();

        $this->app->bind('imap', function () {
            return new \App\Backends\IMAP();
        });
        $this->app->bind('ldap', function () {
            return new \App\Backends\LDAP();
        });
    }

    /**
     * Load the override config and apply it
     *
     * Create a config/override.php file with content like this:
     * return [
     *   'services.imap.uri' => 'overrideValue1',
     *   'queue.connections.database.table' => 'overrideValue2',
     * ];
     */
    private function applyOverrideConfig(): void
    {
        $overrideConfig = (array) \config('override');
        foreach (array_keys($overrideConfig) as $key) {
            \config([$key => $overrideConfig[$key]]);
        }
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        \App\Domain::observe(\App\Observers\DomainObserver::class);
        \App\Entitlement::observe(\App\Observers\EntitlementObserver::class);
        \App\EventLog::observe(\App\Observers\EventLogObserver::class);
        \App\Group::observe(\App\Observers\GroupObserver::class);
        \App\GroupSetting::observe(\App\Observers\GroupSettingObserver::class);
        \App\Meet\Room::observe(\App\Observers\Meet\RoomObserver::class);
        \App\PackageSku::observe(\App\Observers\PackageSkuObserver::class);
        \App\PlanPackage::observe(\App\Observers\PlanPackageObserver::class);
        \App\ReferralCode::observe(\App\Observers\ReferralCodeObserver::class);
        \App\Resource::observe(\App\Observers\ResourceObserver::class);
        \App\ResourceSetting::observe(\App\Observers\ResourceSettingObserver::class);
        \App\SharedFolder::observe(\App\Observers\SharedFolderObserver::class);
        \App\SharedFolderAlias::observe(\App\Observers\SharedFolderAliasObserver::class);
        \App\SharedFolderSetting::observe(\App\Observers\SharedFolderSettingObserver::class);
        \App\SignupCode::observe(\App\Observers\SignupCodeObserver::class);
        \App\SignupInvitation::observe(\App\Observers\SignupInvitationObserver::class);
        \App\SignupToken::observe(\App\Observers\SignupTokenObserver::class);
        \App\Transaction::observe(\App\Observers\TransactionObserver::class);
        \App\User::observe(\App\Observers\UserObserver::class);
        \App\UserAlias::observe(\App\Observers\UserAliasObserver::class);
        \App\UserSetting::observe(\App\Observers\UserSettingObserver::class);
        \App\VerificationCode::observe(\App\Observers\VerificationCodeObserver::class);
        \App\Wallet::observe(\App\Observers\WalletObserver::class);

        \App\PowerDNS\Domain::observe(\App\Observers\PowerDNS\DomainObserver::class);
        \App\PowerDNS\Record::observe(\App\Observers\PowerDNS\RecordObserver::class);

        Schema::defaultStringLength(191);

        // Register some template helpers
        Blade::directive(
            'theme_asset',
            function ($path) {
                $path = trim($path, '/\'"');
                return "<?php echo secure_asset('themes/' . \$env['app.theme'] . '/' . '$path'); ?>";
            }
        );

        Builder::macro(
            'withEnvTenantContext',
            function (string $table = null) {
                $tenantId = \config('app.tenant_id');

                if ($tenantId) {
                    /** @var Builder $this */
                    return $this->where(($table ? "$table." : "") . "tenant_id", $tenantId);
                }

                /** @var Builder $this */
                return $this->whereNull(($table ? "$table." : "") . "tenant_id");
            }
        );

        Builder::macro(
            'withObjectTenantContext',
            function (object $object, string $table = null) {
                $tenantId = $object->tenant_id;

                if ($tenantId) {
                    /** @var Builder $this */
                    return $this->where(($table ? "$table." : "") . "tenant_id", $tenantId);
                }

                /** @var Builder $this */
                return $this->whereNull(($table ? "$table." : "") . "tenant_id");
            }
        );

        Builder::macro(
            'withSubjectTenantContext',
            function (string $table = null) {
                if ($user = auth()->user()) {
                    $tenantId = $user->tenant_id;
                } else {
                    $tenantId = \config('app.tenant_id');
                }

                if ($tenantId) {
                    /** @var Builder $this */
                    return $this->where(($table ? "$table." : "") . "tenant_id", $tenantId);
                }

                /** @var Builder $this */
                return $this->whereNull(($table ? "$table." : "") . "tenant_id");
            }
        );

        Http::macro('withSlowLog', function () {
            return Http::withOptions([
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
                    $threshold = \config('logging.slow_log');
                    if ($threshold && ($sec = $stats->getTransferTime()) > $threshold) {
                        $url = $stats->getEffectiveUri();
                        $method = $stats->getRequest()->getMethod();
                        \Log::warning(sprintf("[STATS] %s %s: %.4f sec.", $method, $url, $sec));
                    }
                },
            ]);
        });

        Http::macro('fakeClear', function () {
            $reflection = new \ReflectionObject(Http::getFacadeRoot());
            $property = $reflection->getProperty('stubCallbacks');
            $property->setAccessible(true);
            $property->setValue(Http::getFacadeRoot(), collect());

            return Http::getFacadeRoot();
        });

        $this->applyOverrideConfig();
    }
}
