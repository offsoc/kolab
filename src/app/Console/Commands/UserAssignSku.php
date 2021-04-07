<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UserAssignSku extends Command
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
        $user = \App\User::where('email', $this->argument('user'))->first();

        if (!$user) {
            $this->error("Unable to find the user {$this->argument('user')}.");
            return 1;
        }

        $sku = \App\Sku::find($this->argument('sku'));

        if (!$sku) {
            $sku = \App\Sku::where('title', $this->argument('sku'))->first();
        }

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
