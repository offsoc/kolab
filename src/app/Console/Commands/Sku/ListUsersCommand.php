<?php

namespace App\Console\Commands\Sku;

use App\Console\Command;
use App\Entitlement;
use App\Sku;
use App\User;

class ListUsersCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'sku:list-users {sku} {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List users with the SKU entitlement.';

    /** @var bool Adds --tenant option handler */
    protected $withTenant = true;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();

        $sku = $this->getObject(Sku::class, $this->argument('sku'), 'title');

        if (!$sku) {
            $this->error("Unable to find the SKU.");
            return 1;
        }

        $fn = static function ($entitlement) {
            $user_id = $entitlement->user_id;
            if ($entitlement->entitleable_type == User::class) {
                $user_id = $entitlement->entitleable_id;
            }

            return $user_id;
        };

        $users = Entitlement::select('user_id', 'entitleable_id', 'entitleable_type')
            ->join('wallets', 'wallets.id', '=', 'wallet_id')
            ->where('sku_id', $sku->id)
            ->get()
            ->map($fn)
            ->unique();

        // TODO: This whereIn() might not scale
        User::whereIn('id', $users)->orderBy('email')->get()
            ->pluck('email')
            ->each(function ($email, $key) {
                $this->info($email);
            });
    }
}
