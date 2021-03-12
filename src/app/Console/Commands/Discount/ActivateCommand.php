<?php

namespace App\Console\Commands\Discount;

use Illuminate\Console\Command;

/**
 * Activate a discount, amking it available for selection.
 */
class ActivateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discount:activate {discount}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Activate a discount.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $discount = $this->getObject(\App\Discount::class, $this->argument('discount'));

        if (!$discount) {
            $this->error("No such discount. {$this->argument('discount')}");
            return 1;
        }

        $discount->active = true;
        $discount->save();
    }
}
