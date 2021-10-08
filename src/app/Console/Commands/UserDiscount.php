<?php

namespace App\Console\Commands;

use App\Console\Command;

class UserDiscount extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:discount {user} {discount}';

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
            return 1;
        }

        $this->info("Found user {$user->id}");

        if ($this->argument('discount') === '0') {
            $discount = null;
        } else {
            $discount = $this->getObject(\App\Discount::class, $this->argument('discount'));

            if (!$discount) {
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