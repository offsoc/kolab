<?php

namespace App\Console\Commands;

use App\Domain;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class UserEntitlementsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:entitlements {userid}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "List a user's entitlements.";

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
        $user = User::where('email', $this->argument('userid'))->first();

        $this->info("Found user: {$user->id}");

        $entitlements = $user->entitlements()->get();

        foreach ($entitlements as $entitlement) {
            //yes: dd($entitlement);
            $_entitleable = $entitlement->entitleable;

            if ($_entitleable instanceof Domain) {
                $this->info(sprintf("Domain: %s", $_entitleable->namespace));
            }

            if ($_entitleable instanceof User) {
                $this->info(sprintf("User: %s", $_entitleable->email));
            }
        }
    }
}
