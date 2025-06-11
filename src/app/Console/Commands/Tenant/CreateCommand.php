<?php

namespace App\Console\Commands\Tenant;

use App\Console\Command;
use App\Domain;
use App\Http\Controllers\API\V4\UsersController;
use App\Package;
use App\Plan;
use App\Sku;
use App\Tenant;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tenant:create {user} {domain} {--title=} {--password=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a tenant (with clonoed plans), and a reseller user and domain for it.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('user');

        if ($user = User::where('email', $email)->first()) {
            $this->error("The user already exists.");
            return 1;
        }

        if ($domain = Domain::where('namespace', $this->argument('domain'))->first()) {
            $this->error("The domain already exists.");
            return 1;
        }

        DB::beginTransaction();

        // Create a tenant
        $tenant = Tenant::create(['title' => $this->option('title')]);

        // Clone plans, packages, skus for the tenant
        $sku_map = Sku::withEnvTenantContext()->where('active', true)->get()
            ->mapWithKeys(static function ($sku) use ($tenant) {
                $sku_new = Sku::create([
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

                return [$sku->id => $sku_new->id];
            })
            ->all();

        $plan_map = Plan::withEnvTenantContext()->get()
            ->mapWithKeys(static function ($plan) use ($tenant) {
                $plan_new = Plan::create([
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

                return [$plan->id => $plan_new->id];
            })
            ->all();

        $package_map = Package::withEnvTenantContext()->get()
            ->mapWithKeys(static function ($package) use ($tenant) {
                $package_new = Package::create([
                    'title' => $package->title,
                    'name' => $package->getTranslations('name'),
                    'description' => $package->getTranslations('description'),
                    'discount_rate' => $package->discount_rate,
                ]);

                $package_new->tenant_id = $tenant->id;
                $package_new->save();

                return [$package->id => $package_new->id];
            })
            ->all();

        DB::table('package_skus')->whereIn('package_id', array_keys($package_map))->get()
            ->each(static function ($item) use ($package_map, $sku_map) {
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
            ->each(static function ($item) use ($package_map, $plan_map) {
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

        // Make sure the transaction wasn't aborted
        $tenant = Tenant::find($tenant->id);

        if (!$tenant) {
            $this->error("Failed to create a tenant.");
            return 1;
        }

        $this->info("Created tenant {$tenant->id}.");

        // Set up the primary tenant domain
        $domain = Domain::create(
            [
                'namespace' => $this->argument('domain'),
                'type' => Domain::TYPE_PUBLIC,
            ]
        );
        $domain->tenant_id = $tenant->id;
        $domain->status = Domain::STATUS_CONFIRMED | Domain::STATUS_ACTIVE;
        $domain->save();
        $this->info("Created domain {$domain->id}.");

        $user = new User();
        $user->email = $email;
        $user->password = $this->option('password');
        $user->role = User::ROLE_RESELLER;
        $user->tenant_id = $tenant->id;

        if ($error = UsersController::validateEmail($email, $user)) {
            $this->error("{$email}: {$error}");
            return 1;
        }

        $user->save();
        $this->info("Created user {$user->id}.");

        $tenant->setSettings([
            "app.name" => $this->option("title"),
            "app.url" => $this->argument("domain"),
            "app.public_url" => "https://" . $this->argument("domain"),
            "app.support_url" => "https://" . $this->argument("domain") . "/support",
            "mail.sender.address" => "noreply@" . $this->argument("domain"),
            "mail.sender.name" => $this->option("title"),
            "mail.replyto.address" => "noreply@" . $this->argument("domain"),
            "mail.replyto.name" => $this->option("title"),
        ]);

        DB::commit();

        $this->info("Applied default tenant settings.");
    }
}
