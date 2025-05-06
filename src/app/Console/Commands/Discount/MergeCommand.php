<?php

namespace App\Console\Commands\Discount;

use App\Console\Command;
use App\Discount;

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
    protected $signature = 'discount:merge {source} {target} {--description=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Merge one discount in to another discount, '
        . 'optionally set the description, and delete the source discount';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $source = $this->getObject(Discount::class, $this->argument('source'));

        if (!$source) {
            $this->error("No such source discount: {$source}");
            return 1;
        }

        $target = $this->getObject(Discount::class, $this->argument('target'));

        if (!$target) {
            $this->error("No such target discount: {$target}");
            return 1;
        }

        if ($source->discount !== $target->discount) {
            $this->error("Can't merge two discounts that have different rates");
            return 1;
        }

        if ($source->tenant_id !== $target->tenant_id) {
            $this->error("Can't merge two discounts that have different tenants");
            return 1;
        }

        foreach ($source->wallets as $wallet) {
            $wallet->discount_id = $target->id;
            $wallet->timestamps = false;
            $wallet->save();
        }

        if ($description = $this->option('description')) {
            $target->description = $description;
            $target->save();
        }

        $source->delete();
    }
}
