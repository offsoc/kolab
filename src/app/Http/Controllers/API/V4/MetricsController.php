<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Payment;
use App\User;
use App\Wallet;
use Illuminate\Support\Facades\DB;

class MetricsController extends Controller
{
    private function addTenantContext($query)
    {
        if ($tenantId = \config('app.tenant_id')) {
            return $query->where('users.tenant_id', $tenantId);
        } else {
            return $query->whereNull('users.tenant_id');
        }
    }

    /**
     * Collect current payers count
     */
    protected function collectPayersCount(): int
    {
        // A subquery to get the all wallets with a successful payment
        $payments = DB::table('payments')
            ->selectRaw('distinct wallet_id')
            ->where('status', Payment::STATUS_PAID);

        // A subquery to get users' wallets (by entitlement) - one record per user
        $wallets = DB::table('entitlements')
            ->selectRaw("min(wallet_id) as id, entitleable_id as user_id")
            ->where('entitleable_type', User::class)
            ->groupBy('entitleable_id');

        // Count all non-degraded and non-deleted users with any successful payment
        $count = DB::table('users')
            ->joinSub($wallets, 'wallets', function ($join) {
                $join->on('users.id', '=', 'wallets.user_id');
            })
            ->joinSub($payments, 'payments', function ($join) {
                $join->on('wallets.id', '=', 'payments.wallet_id');
            })
            ->whereNull('users.deleted_at')
            ->whereNot('users.status', '&', User::STATUS_DEGRADED | User::STATUS_SUSPENDED);

        $count = $this->addTenantContext($count);
        return $count->count();
    }

    /**
     * Collect number of wallets that require topup
     */
    protected function numberOfWalletsWithBalanceBelowManadate(): int
    {
        $count = Wallet::select('wallets.id')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            ->join('wallet_settings', function (\Illuminate\Database\Query\JoinClause $join) {
                $join->on('wallet_settings.wallet_id', '=', 'wallets.id')
                    ->where('wallet_settings.key', '=', 'mandate_balance');
            })
            ->whereNull('users.deleted_at')
            ->whereRaw('wallets.balance < (wallet_settings.value * 100)')
            ->whereNot('users.status', '&', User::STATUS_DEGRADED | User::STATUS_SUSPENDED);

        $count = $this->addTenantContext($count);
        return $count->count();
    }

    /**
     * Expose kolab metrics
     *
     * @return \Illuminate\Http\Response The response
     */
    public function metrics()
    {
        $appDomain = \config('app.domain');
        $tenantId = \config('app.tenant_id');
        // TODO: just get this from the stats table instead?
        $numberOfPayingUsers = $this->collectPayersCount();

        $numberOfUsers = User::count();
        $numberOfDeletedUsers = User::withTrashed()->whereNotNull('deleted_at')->count();
        $numberOfSuspendedUsers = User::where('status', '&', User::STATUS_SUSPENDED)->count();
        $numberOfRestrictedUsers = User::where('status', '&', User::STATUS_RESTRICTED)->count();
        $numberOfWalletsWithBalanceBelowManadate = $this->numberOfWalletsWithBalanceBelowManadate();

        // phpcs:disable
        $text = <<<EOF
        # HELP kolab_users_count Total number of users
        # TYPE kolab_users_count gauge
        kolab_users_count{instance="$appDomain", tenant="$tenantId"} $numberOfUsers
        # HELP kolab_users_deleted_count Number of deleted users
        # TYPE kolab_users_deleted_count gauge
        kolab_users_deleted_count{instance="$appDomain", tenant="$tenantId"} $numberOfDeletedUsers
        # HELP kolab_users_suspended_count Number of suspended users
        # TYPE kolab_users_suspended_count gauge
        kolab_users_suspended_count{instance="$appDomain", tenant="$tenantId"} $numberOfSuspendedUsers
        # HELP kolab_users_restricted_count Number of restricted users
        # TYPE kolab_users_restricted_count gauge
        kolab_users_restricted_count{instance="$appDomain", tenant="$tenantId"} $numberOfRestrictedUsers
        # HELP kolab_users_paying_count Number of paying users
        # TYPE kolab_users_paying_count gauge
        kolab_users_paying_count{instance="$appDomain", tenant="$tenantId"} $numberOfPayingUsers
        # HELP kolab_wallets_balance_below_mandate_amount_count Number of wallets requiring topup
        # TYPE kolab_wallets_balance_below_mandate_amount_count gauge
        kolab_wallets_balance_below_mandate_amount{instance="$appDomain", tenant="$tenantId"} $numberOfWalletsWithBalanceBelowManadate
        \n
        EOF;
        // phpcs:enable

        return response(
            $text,
            200,
            [
                'Content-Type' => "text/plain",
            ]
        );
    }
}
