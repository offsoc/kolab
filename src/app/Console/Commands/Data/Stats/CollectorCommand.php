<?php

namespace App\Console\Commands\Data\Stats;

use App\Http\Controllers\API\V4\Admin\StatsController;
use App\Payment;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CollectorCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:stats:collector';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Collects statictical data about the system (for charts)';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $this->collectPayersCount();
    }

    /**
     * Collect current payers count
     */
    protected function collectPayersCount(): void
    {
        $tenant_id = \config('app.tenant_id');

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
            ->whereNot('users.status', '&', User::STATUS_DEGRADED)
            ->whereNot('users.status', '&', User::STATUS_SUSPENDED);

        if ($tenant_id) {
            $count->where('users.tenant_id', $tenant_id);
        } else {
            $count->whereNull('users.tenant_id');
        }

        $count = $count->count();

        if ($count) {
            DB::table('stats')->insert([
                    'tenant_id' => $tenant_id,
                    'type' => StatsController::TYPE_PAYERS,
                    'value' => $count,
            ]);
        }
    }
}
