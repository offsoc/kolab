<?php

namespace App\Providers;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
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
        Passport::ignoreMigrations();
        Passport::ignoreRoutes();
    }

    /**
     * Serialize a bindings array to a string.
     */
    private static function serializeSQLBindings(array $array, string $sql): string
    {
        $ipv = preg_match('/ip([46])nets/', $sql, $m) ? $m[1] : null;

        $serialized = array_map(function ($entry) use ($ipv) {
            if ($entry instanceof \DateTime) {
                return $entry->format('Y-m-d h:i:s');
            } elseif ($ipv && is_string($entry) && strlen($entry) == ($ipv == 6 ? 16 : 4)) {
                // binary IP address? use HEX representation
                return '0x' . bin2hex($entry);
            }

            return $entry;
        }, $array);

        return implode(', ', $serialized);
    }

    /**
     * Load the override config and apply it
     *
     * Create a config/override.php file with content like this:
     * return [
     *   'imap.uri' => 'overrideValue1',
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
        \App\Group::observe(\App\Observers\GroupObserver::class);
        \App\GroupSetting::observe(\App\Observers\GroupSettingObserver::class);
        \App\Meet\Room::observe(\App\Observers\Meet\RoomObserver::class);
        \App\PackageSku::observe(\App\Observers\PackageSkuObserver::class);
        \App\PlanPackage::observe(\App\Observers\PlanPackageObserver::class);
        \App\Resource::observe(\App\Observers\ResourceObserver::class);
        \App\ResourceSetting::observe(\App\Observers\ResourceSettingObserver::class);
        \App\SharedFolder::observe(\App\Observers\SharedFolderObserver::class);
        \App\SharedFolderAlias::observe(\App\Observers\SharedFolderAliasObserver::class);
        \App\SharedFolderSetting::observe(\App\Observers\SharedFolderSettingObserver::class);
        \App\SignupCode::observe(\App\Observers\SignupCodeObserver::class);
        \App\SignupInvitation::observe(\App\Observers\SignupInvitationObserver::class);
        \App\Transaction::observe(\App\Observers\TransactionObserver::class);
        \App\User::observe(\App\Observers\UserObserver::class);
        \App\UserAlias::observe(\App\Observers\UserAliasObserver::class);
        \App\UserSetting::observe(\App\Observers\UserSettingObserver::class);
        \App\VerificationCode::observe(\App\Observers\VerificationCodeObserver::class);
        \App\Wallet::observe(\App\Observers\WalletObserver::class);

        \App\PowerDNS\Domain::observe(\App\Observers\PowerDNS\DomainObserver::class);
        \App\PowerDNS\Record::observe(\App\Observers\PowerDNS\RecordObserver::class);

        Schema::defaultStringLength(191);

        // Log SQL queries in debug mode
        if (\config('app.debug')) {
            DB::listen(function ($query) {
                \Log::debug(
                    sprintf(
                        '[SQL] %s [%s]: %.4f sec.',
                        $query->sql,
                        self::serializeSQLBindings($query->bindings, $query->sql),
                        $query->time / 1000
                    )
                );
            });
        }

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

        // Query builder 'whereLike' mocro
        Builder::macro(
            'whereLike',
            function (string $column, string $search, int $mode = 0) {
                $search = addcslashes($search, '%_');

                switch ($mode) {
                    case 2:
                        $search .= '%';
                        break;
                    case 1:
                        $search = '%' . $search;
                        break;
                    default:
                        $search = '%' . $search . '%';
                }

                /** @var Builder $this */
                return $this->where($column, 'like', $search);
            }
        );

        $this->applyOverrideConfig();
    }
}
