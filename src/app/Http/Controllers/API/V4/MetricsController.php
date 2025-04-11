<?php

namespace App\Http\Controllers\API\V4;

use App\Http\Controllers\Controller;
use App\Payment;
use App\User;
use App\Wallet;
use App\Transaction;
use Illuminate\Support\Facades\DB;
use Laravel\Horizon\Contracts\JobRepository;
use Laravel\Horizon\Contracts\MetricsRepository;
use Laravel\Horizon\Contracts\WorkloadRepository;

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

    protected function horizonMetrics($appDomain, $tenantId): string
    {
        $recentJobs = app(JobRepository::class)->countRecent();
        $recentFailedJobs = app(JobRepository::class)->countRecentlyFailed();
        $jobsPerMinute = intval(app(MetricsRepository::class)->jobsProcessedPerMinute());

        $text = <<<EOF
        # HELP kolab_horizon_recent_jobs Number of jobs in past 7 days
        # TYPE kolab_horizon_recent_jobs gauge
        kolab_horizon_recent_jobs{instance="$appDomain", tenant="$tenantId"} $recentJobs
        # HELP kolab_horizon_recent_failed_jobs Number of jobs failed in past 7 days
        # TYPE kolab_horizon_recent_failed_jobs gauge
        kolab_horizon_recent_failed_jobs{instance="$appDomain", tenant="$tenantId"} $recentFailedJobs
        # HELP kolab_horizon_jobs_per_minute Number of jobs processed per minute
        # TYPE kolab_horizon_jobs_per_minute gauge
        kolab_horizon_jobs_per_minute{instance="$appDomain", tenant="$tenantId"} $jobsPerMinute

        EOF;

        // phpcs:disable
        foreach (app(WorkloadRepository::class)->get() as $workloadMetrics) {
            $queueName = $workloadMetrics['name'] ?? 'unknown'; // @phpstan-ignore-line
            $queueSize = $workloadMetrics['length'] ?? 0; // @phpstan-ignore-line
            $queueWaitTime = $workloadMetrics['wait'] ?? 0; // @phpstan-ignore-line
            $text .= <<<EOF
            # HELP kolab_horizon_queue_size Number of jobs in queue
            # TYPE kolab_horizon_queue_size gauge
            kolab_horizon_queue_size{instance="$appDomain", tenant="$tenantId", queue="$queueName"} $queueSize
            # HELP kolab_horizon_queue_wait_seconds Time until queue is empty
            # TYPE kolab_horizon_queue_wait_seconds gauge
            kolab_horizon_queue_wait_seconds{instance="$appDomain", tenant="$tenantId", queue="$queueName"} $queueWaitTime

            EOF;
        }
        // phpcs:enable

        return $text;
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
        $with_ldap = \config('app.with_ldap');

        $numberOfPayingUsers = $this->collectPayersCount();

        $numberOfPayers = Payment::where('status', Payment::STATUS_PAID)
            ->distinct()->count('wallet_id');
        $numberOfActivePayers = Payment::where('status', Payment::STATUS_PAID)
            ->where('created_at', '>', \Carbon\Carbon::now()->subMonths(1))
            ->distinct()->count('wallet_id');

        $numberOfBilledTransactions = Transaction::where('type', Transaction::ENTITLEMENT_BILLED)->count();
        $numberOfRefundTransactions = Transaction::where('type', Transaction::WALLET_REFUND)->count();
        $numberOfChargebackTransactions = Transaction::where('type', Transaction::WALLET_CHARGEBACK)->count();

        $numberOfUsers = User::count();
        $numberOfDeletedUsers = User::onlyTrashed()->count();
        $numberOfActiveUsers = User::where('status', '&', User::STATUS_ACTIVE)
            ->whereNot('status', '&', User::STATUS_SUSPENDED)->count();
        $numberOfSuspendedUsers = User::where('status', '&', User::STATUS_SUSPENDED)->count();
        $numberOfRestrictedUsers = User::where('status', '&', User::STATUS_RESTRICTED)
            ->whereNot('status', '&', User::STATUS_SUSPENDED)->count();
        $numberOfDegradedUsers = User::where('status', '&', User::STATUS_ACTIVE | User::STATUS_DEGRADED)
            ->whereNot('status', '&', User::STATUS_SUSPENDED)->count();
        $numberOfWalletsWithBalanceBelowManadate = $this->numberOfWalletsWithBalanceBelowManadate();
        // Should be ~0 (otherwise a cleanup job failed)
        $numberOfDeletedUserWithMissingCleanup = User::onlyTrashed()
            ->where('deleted_at', '<', \Carbon\Carbon::now()->subDays(1))
            ->where(function ($query) use ($with_ldap) {
                $query = $query->where('status', '&', User::STATUS_IMAP_READY);
                if ($with_ldap) {
                    $query->orWhere('status', '&', User::STATUS_LDAP_READY);
                }
            })->count();

        $numberOfUserWithFailedInit = User::where('created_at', '<', \Carbon\Carbon::now()->subDays(1));
        $numberOfUserWithFailedInit->where(function ($query) use ($with_ldap) {
            $query = $query->whereNot('status', '&', User::STATUS_IMAP_READY)
                ->orWhereNot('status', '&', User::STATUS_ACTIVE);
            if ($with_ldap) {
                $query->orWhereNot('status', '&', User::STATUS_LDAP_READY);
            }
        });
        $numberOfUserWithFailedInit = $numberOfUserWithFailedInit->count();

        $horizon = $this->horizonMetrics($appDomain, $tenantId);

        $numberOfBilledTransactions = Transaction::where('type', Transaction::ENTITLEMENT_BILLED)->count();
        $numberOfRefundTransactions = Transaction::where('type', Transaction::WALLET_REFUND)->count();
        $numberOfChargebackTransactions = Transaction::where('type', Transaction::WALLET_CHARGEBACK)->count();

        $numberOfPaidPayments = Payment::where('status', Payment::STATUS_PAID)->count();
        $numberOfFailedPayments = Payment::where('status', Payment::STATUS_FAILED)->count();
        // phpcs:disable
        $text = <<<EOF
        # HELP kolab_users_count Number of users with a certain state
        # TYPE kolab_users_count gauge
        kolab_users_count{instance="$appDomain", tenant="$tenantId", state="existing"} $numberOfUsers
        kolab_users_count{instance="$appDomain", tenant="$tenantId", state="active"} $numberOfActiveUsers
        kolab_users_count{instance="$appDomain", tenant="$tenantId", state="trashed"} $numberOfDeletedUsers
        kolab_users_count{instance="$appDomain", tenant="$tenantId", state="suspended"} $numberOfSuspendedUsers
        kolab_users_count{instance="$appDomain", tenant="$tenantId", state="restricted"} $numberOfRestrictedUsers
        kolab_users_count{instance="$appDomain", tenant="$tenantId", state="degraded"} $numberOfDegradedUsers
        # HELP kolab_users_paying_count Number of paying users
        # TYPE kolab_users_paying_count gauge
        kolab_users_paying_count{instance="$appDomain", tenant="$tenantId"} $numberOfPayingUsers
        # HELP kolab_wallets_balance_below_mandate_amount_count Number of wallets requiring topup
        # TYPE kolab_wallets_balance_below_mandate_amount_count gauge
        kolab_wallets_balance_below_mandate_amount{instance="$appDomain", tenant="$tenantId"} $numberOfWalletsWithBalanceBelowManadate
        # HELP kolab_users_deleted_with_missing_cleanup Number of users that are still imap/ldap ready
        # TYPE kolab_users_deleted_with_missing_cleanup gauge
        kolab_users_deleted_with_missing_cleanup{instance="$appDomain", tenant="$tenantId"} $numberOfDeletedUserWithMissingCleanup
        # HELP kolab_users_failed_init Number of users that are still imap/ldap ready
        # TYPE kolab_users_failed_init gauge
        kolab_users_failed_init{instance="$appDomain", tenant="$tenantId"} $numberOfUserWithFailedInit
        # HELP kolab_transactions_count Number of transactions
        # TYPE kolab_transactions_count gauge
        kolab_transactions_count{instance="$appDomain", tenant="$tenantId", type="billed"} $numberOfBilledTransactions
        kolab_transactions_count{instance="$appDomain", tenant="$tenantId", type="refund"} $numberOfRefundTransactions
        kolab_transactions_count{instance="$appDomain", tenant="$tenantId", type="chargeback"} $numberOfChargebackTransactions
        # HELP kolab_payments_paid_count Number of paid payments
        # TYPE kolab_payments_paid_count gauge
        kolab_payments_count{instance="$appDomain", tenant="$tenantId", status="paid"} $numberOfPaidPayments
        kolab_payments_count{instance="$appDomain", tenant="$tenantId", status="failed"} $numberOfFailedPayments
        # HELP kolab_payers_count Number of distinct wallets with payments
        # TYPE kolab_payers_count gauge
        kolab_payers_count{instance="$appDomain", tenant="$tenantId"} $numberOfPayers
        # HELP kolab_payers_active_count Number of distinct wallets with payments in past month
        # TYPE kolab_payers_active_count gauge
        kolab_payers_active_count{instance="$appDomain", tenant="$tenantId"} $numberOfActivePayers
        $horizon
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
