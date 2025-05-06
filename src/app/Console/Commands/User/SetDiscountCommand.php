<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\Discount;

class SetDiscountCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:set-discount {user} {discount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Apply a discount to all of the user's wallets";

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

        if ($this->argument('discount') === '0') {
            $discount = null;
        } else {
            $discount = $this->getObject(Discount::class, $this->argument('discount'));

            if (!$discount) {
                $this->error("Discount not found.");
                return 1;
            }
        }

        foreach ($user->wallets as $wallet) {
            if (!$discount) {
                $wallet->discount()->dissociate();
            } else {
                $wallet->discount()->associate($discount);
            }

            $wallet->save();
        }
    }
}
