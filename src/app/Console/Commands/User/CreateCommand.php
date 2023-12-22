<?php

namespace App\Console\Commands\User;

use Illuminate\Support\Facades\DB;
use App\Http\Controllers\API\V4\UsersController;

class CreateCommand extends \App\Console\Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email} {--package=*} {--password=} {--role=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a user.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('email');
        $packages = $this->option('package');
        $password = $this->option('password');
        $role = $this->option('role');

        list($local, $domainName) = explode('@', $email, 2);

        $domain = $this->getDomain($domainName);

        if (!$domain) {
            $this->error("No such domain {$domainName}.");
            return 1;
        }

        if ($domain->isPublic()) {
            $this->error("Domain {$domainName} is public.");
            return 1;
        }

        $owner = $domain->wallet()->owner;
        $existingDeletedUser = null;

        // Validate email address
        if ($error = UsersController::validateEmail($email, $owner, $existingDeletedUser)) {
            $this->error("{$email}: {$error}");
            return 1;
        }

        if (!$password) {
            $password = \App\Utils::generatePassphrase();
        }

        $packagesToAssign = [];
        foreach ($packages as $package) {
            $userPackage = $this->getObject(\App\Package::class, $package, 'title', false);
            if (!$userPackage) {
                $this->error("Invalid package: {$package}");
                return 1;
            }
            $packagesToAssign[] = $userPackage;
        }

        //TODO we need a central location for role validation
        if ($role && $role != "admin" && $role != "reseller") {
            $this->error("Tried to set an invalid role: {$role}");
            return 1;
        }

        DB::beginTransaction();

        if ($existingDeletedUser) {
            $this->info("Force deleting existing but deleted user {$email}");
            $existingDeletedUser->forceDelete();
        }

        $user = \App\User::create(
            [
                'email' => $email,
                'password' => $password
            ]
        );
        $user->role = $role;
        $user->save();

        foreach ($packagesToAssign as $package) {
            $user->assignPackage($package);
        }

        DB::commit();

        $this->info($user->id);
    }
}
