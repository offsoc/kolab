<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\Sku;

class AssignSkuCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:assign-sku {user} {sku} {--qty=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Assign a SKU to the user';

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

        $sku = $this->getObject(Sku::class, $this->argument('sku'), 'title');

        if (!$sku) {
            $this->error("Unable to find the SKU {$this->argument('sku')}.");
            return 1;
        }

        $quantity = (int) $this->option('qty');

        // Check if the entitlement already exists
        if (empty($quantity)) {
            if ($user->entitlements()->where('sku_id', $sku->id)->first()) {
                $this->error("The entitlement already exists. Maybe try with --qty=X?");
                return 1;
            }
        }

        $user->assignSku($sku, $quantity ?: 1);
    }
}
