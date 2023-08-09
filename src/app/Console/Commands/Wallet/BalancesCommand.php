<?php

namespace App\Console\Commands\Wallet;

use App\Transaction;
use App\Wallet;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class BalancesCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'wallet:balances {--skip-zeros} {--negative} {--invalid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Show the balance on wallets';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $skip_zeros = $this->option('skip-zeros');
        $negative = $this->option('negative');
        $invalid = $this->option('invalid');

        $wallets = Wallet::select('wallets.*', 'users.email')
            ->join('users', 'users.id', '=', 'wallets.user_id')
            ->withEnvTenantContext('users')
            ->whereNull('users.deleted_at')
            ->orderBy('balance');

        if ($invalid) {
            $balances = Transaction::select(DB::raw('sum(amount) as summary, object_id as wallet_id'))
                ->where('object_type', Wallet::class)
                ->groupBy('wallet_id');
        
            $wallets->addSelect('balances.summary')
                ->leftJoinSub($balances, 'balances', function ($join) {
                    $join->on('wallets.id', '=', 'balances.wallet_id');
                })
                ->whereRaw('(balances.summary != wallets.balance or (balances.summary is null and wallets.balance != 0))');

            if ($negative) {
                $wallets->where('balances.summary', '<', 0);
            } elseif ($skip_zeros) {
                $wallets->whereRaw('balances.summary != 0 and balances.summary is not null');
            }
        } else {
            if ($negative) {
                $wallets->where('wallets.balance', '<', 0);
            } elseif ($skip_zeros) {
                $wallets->whereNot('wallets.balance', 0);
            }
        }

        $wallets->cursor()->each(
            function (Wallet $wallet) use ($invalid) {
                $balance = $wallet->balance;
                $summary = $wallet->summary ?? 0;
                $email = $wallet->email; // @phpstan-ignore-line

                if ($invalid) {
                    $this->info(sprintf("%s: %8s %8s (%s)", $wallet->id, $balance, $summary, $email));
                    return;
                }

                $this->info(sprintf("%s: %8s (%s)", $wallet->id, $balance, $email));
            }
        );
    }
}
