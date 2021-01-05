<?php

namespace App\Console\Commands\Scalpel\Discount;

use Illuminate\Console\Command;

/**
 * Merge one discount (source) with another discount (target), and delete the source discount.
 *
 * This command re-associates the wallets that are discounted with the source discount to become discounted with the
 * target discount.
 *
 * Optionally, update the description of the target discount.
 *
 * You are not allowed to merge discounts that have different discount rates.
 *
 * This command makes it feasible to merge existing discounts like the ones that are 100% and described as
 * "It's us..", "it's us", "This is us", etc.
 *
 * Example usage:
 *
 * ```
 * $ ./artisan scalpel:discount:merge \
 * > 158f660b-e992-4fb9-ac12-5173b5f33807 \
 * > 62af659f-17d8-4527-87c1-c69eaa26653c \
 * > --description="Employee discount"
 * ```
 */
class MergeCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'scalpel:discount:merge {source} {target} {--description*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge one discount in to another discount, ' .
                             'optionally set the description, and delete the source discount';

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
        $source = \App\Discount::find($this->argument('source'));

        if (!$source) {
            $this->error("No such source discount: {$source}");
            return 1;
        }

        $target = \App\Discount::find($this->argument('target'));

        if (!$target) {
            $this->error("No such target discount: {$target}");
            return 1;
        }

        if ($source->discount !== $target->discount) {
            $this->error("Can't merge two discounts that have different rates");
            return 1;
        }

        foreach ($source->wallets as $wallet) {
            $wallet->discount = $target;
            $wallet->timestamps = false;
            $wallet->save();
        }

        if ($this->option('description')) {
            $target->{'description'} = $this->option('description');
            $target->save();
        }

        $source->delete();
    }
}
