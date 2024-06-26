<?php

namespace App\Console\Commands\Data\Import;

use App\Plan;
use App\SignupToken;
use App\Console\Command;

class SignupTokensCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'data:import:signup-tokens {plan} {file} {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports signup tokens from a file.';

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($tenantId = $this->option('tenant')) {
            $tenant = $this->getObject(\App\Tenant::class, $tenantId, 'title');
            if (!$tenant) {
                $this->error("Tenant {$tenantId} not found");
                return 1;
            }

            $this->tenantId = $tenant->id;
        } else {
            $this->tenantId = \config('app.tenant_id');
        }

        $plan = $this->getObject(Plan::class, $this->argument('plan'), 'title', false);

        if (!$plan) {
            $this->error("Plan not found");
            return 1;
        }

        if ($plan->mode != Plan::MODE_TOKEN) {
            $this->error("The plan is not for tokens");
            return 1;
        }

        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File '$file' does not exist");
            return 1;
        }

        $list = file($file);

        if (empty($list)) {
            $this->error("File '$file' is empty");
            return 1;
        }

        $bar = $this->createProgressBar(count($list), "Validating tokens");

        $list = array_map('trim', $list);
        $list = array_map('strtoupper', $list);

        // Validate tokens
        foreach ($list as $idx => $token) {
            if (!strlen($token)) {
                // Skip empty lines
                unset($list[$idx]);
            } elseif (strlen($token) > 191) {
                $bar->finish();
                $this->error("Token '$token' is too long");
                return 1;
            } elseif (SignupToken::find($token)) {
                // Skip existing tokens
                unset($list[$idx]);
            }

            $bar->advance();
        }

        $bar->finish();

        $this->info("DONE");

        if (empty($list)) {
            $this->info("Nothing to import");
            return 0;
        }

        $list = array_unique($list); // remove duplicated tokens

        $bar = $this->createProgressBar(count($list), "Importing tokens");

        // Import tokens
        foreach ($list as $token) {
            $plan->signupTokens()->create([
                    'id' => $token,
                    // This allows us to update counter when importing old tokens in migration.
                    // It can be removed later
                    'counter' => \App\UserSetting::where('key', 'signup_token')
                        ->whereRaw('UPPER(value) = ?', [$token])->count(),
            ]);

            $bar->advance();
        }

        $bar->finish();

        $this->info("DONE");
    }
}
