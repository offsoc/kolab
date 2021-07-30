<?php

namespace App\Console\Commands\Tenant;

use App\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create {user} {--title=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a tenant (with a set of SKUs/plans/packages) and make the user a reseller.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $user = $this->getUser($this->argument('user'));

        if (!$user) {
            $this->error('User not found.');
            return 1;
        }

        DB::beginTransaction();

        // Create a tenant
        $tenant = \App\Tenant::create(['title' => $this->option('title') ?: $user->name()]);

        // Clone plans, packages, skus for the tenant
        $sku_map = [];
        \App\Sku::withEnvTenantContext()->where('active', true)->get()
            ->each(function ($sku) use ($sku_map, $tenant) {
                $sku_new = \App\Sku::create([
                        'title' => $sku->title,
                        'name' => $sku->getTranslations('name'),
                        'description' => $sku->getTranslations('description'),
                        'cost' => $sku->cost,
                        'units_free' => $sku->units_free,
                        'period' => $sku->period,
                        'handler_class' => $sku->handler_class,
                        'active' => true,
                        'fee' => $sku->fee,
                ]);

                $sku_new->tenant_id = $tenant->id;
                $sku_new->save();

                $sku_map[$sku->id] = $sku_new->id;
            });

        $plan_map = [];
        \App\Plan::withEnvTenantContext()->get()
            ->each(function ($plan) use ($plan_map, $tenant) {
                $plan_new = \App\Plan::create([
                        'title' => $plan->title,
                        'name' => $plan->getTranslations('name'),
                        'description' => $plan->getTranslations('description'),
                        'promo_from' => $plan->promo_from,
                        'promo_to' => $plan->promo_to,
                        'qty_min' => $plan->qty_min,
                        'qty_max' => $plan->qty_max,
                        'discount_qty' => $plan->discount_qty,
                        'discount_rate' => $plan->discount_rate,
                ]);

                $plan_new->tenant_id = $tenant->id;
                $plan_new->save();

                $plan_map[$plan->id] = $plan_new->id;
            });

        $package_map = [];
        \App\Package::withEnvTenantContext()->get()
            ->each(function ($package) use ($package_map, $tenant) {
                $package_new = \App\Package::create([
                        'title' => $package->title,
                        'name' => $package->getTranslations('name'),
                        'description' => $package->getTranslations('description'),
                        'discount_rate' => $package->discount_rate,
                ]);

                $package_new->tenant_id = $tenant->id;
                $package_new->save();

                $package_map[$package->id] = $package_new->id;
            });

        DB::table('package_skus')->whereIn('package_id', array_keys($package_map))->get()
            ->each(function ($item) use ($package_map, $sku_map) {
                if (isset($sku_map[$item->sku_id])) {
                    DB::table('package_skus')->insert([
                            'qty' => $item->qty,
                            'cost' => $item->cost,
                            'sku_id' => $sku_map[$item->sku_id],
                            'package_id' => $package_map[$item->package_id],
                    ]);
                }
            });

        DB::table('plan_packages')->whereIn('plan_id', array_keys($plan_map))->get()
            ->each(function ($item) use ($package_map, $plan_map) {
                if (isset($package_map[$item->package_id])) {
                    DB::table('plan_packages')->insert([
                            'qty' => $item->qty,
                            'qty_min' => $item->qty_min,
                            'qty_max' => $item->qty_max,
                            'discount_qty' => $item->discount_qty,
                            'discount_rate' => $item->discount_rate,
                            'plan_id' => $plan_map[$item->plan_id],
                            'package_id' => $package_map[$item->package_id],
                    ]);
                }
            });

        // Disable jobs, they would fail anyway as the TENANT_ID is different
        // TODO: We could probably do config(['app.tenant' => $tenant->id]) here
        Queue::fake();

        // Assign 'reseller' role to the user
        $user->role = 'reseller';
        $user->tenant_id = $tenant->id;
        $user->save();

        // Switch tenant_id for all of the user belongings
        $user->wallets->each(function ($wallet) use ($tenant) {
            $wallet->entitlements->each(function ($entitlement) use ($tenant) {
                $entitlement->entitleable->tenant_id = $tenant->id;
                $entitlement->entitleable->save();

                // TODO: If user already has any entitlements, they will have to be
                //       removed/replaced by SKUs in the newly created tenant
                //       I think we don't really support this yet anyway.
            });

            // TODO: If the wallet has a discount we should remove/replace it too
            //       I think we don't really support this yet anyway.
        });

        DB::commit();

        // Make sure the transaction wasn't aborted
        $tenant = \App\Tenant::find($tenant->id);

        if (!$tenant) {
            $this->error("Failed to create a tenant.");
            return 1;
        }

        $this->info("Created tenant {$tenant->id}.");
    }
}
