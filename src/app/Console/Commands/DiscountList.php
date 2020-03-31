<?php

namespace App\Console\Commands;

use App\Discount;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DiscountList extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'discount:list';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'List available (active) discounts';

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
        Discount::where('active', true)->orderBy('discount')->get()->each(
            function ($discount) {
                $name = $discount->description;

                if ($discount->code) {
                    $name .= " [{$discount->code}]";
                }

                $this->info(
                    sprintf(
                        "%s %3d%% %s",
                        $discount->id,
                        $discount->discount,
                        $name
                    )
                );
            }
        );
    }
}
