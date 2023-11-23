<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use Illuminate\Support\Facades\DB;

class RemoveSkuCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:remove-sku {user} {sku} {--qty=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Remove a SKU from the user';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            $this->error("User not found.");
            return 1;
        }

        $sku = $this->getObject(\App\Sku::class, $this->argument('sku'), 'title');

        if (!$sku) {
            $this->error("Unable to find the SKU {$this->argument('sku')}.");
            return 1;
        }

        $quantity = ((int) $this->option('qty')) ?: 1;

        if ($user->entitlements()->where('sku_id', $sku->id)->count() < $quantity) {
            $this->error("There aren't that many entitlements.");
            return 1;
        }

        // removeSku() can charge the user wallet, let's use database transaction

        DB::beginTransaction();
        $user->removeSku($sku, $quantity);
        DB::commit();
    }
}
