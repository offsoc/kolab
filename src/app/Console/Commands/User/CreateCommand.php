<?php

namespace App\Console\Commands\User;

use App\Console\Command;
use App\Group;
use App\Http\Controllers\API\V4\UsersController;
use App\Package;
use App\Resource;
use App\Rules\ExternalEmail;
use App\SharedFolder;
use App\User;
use App\Utils;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create {email} {--package=*} {--password=} {--role=} {--tenant=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a user.";

    /** @var bool Adds --tenant option handler */
    protected $withTenant = true;

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        parent::handle();

        $email = $this->argument('email');
        $packages = $this->option('package');
        $password = $this->option('password') ?: Utils::generatePassphrase();
        $role = $this->option('role');

        $existingDeletedUser = null;
        $packagesToAssign = [];

        if ($role === User::ROLE_SERVICE) {
            // No validation for service accounts
        } elseif ($role === User::ROLE_ADMIN || $role === User::ROLE_RESELLER) {
            if ($error = $this->validateUserWithRole($email)) {
                $this->error($error);
                return 1;
            }

        // TODO: Assigning user to an existing account
        // TODO: Making him an operator of the reseller wallet
        } else {
            [$local, $domainName] = explode('@', $email, 2);

            $role = null;
            $domain = $this->getDomain($domainName);

            if (!$domain) {
                $this->error("No such domain {$domainName}.");
                return 1;
            }

            if (!$domain->isPublic()) {
                $owner = $domain->wallet()->owner;
            }

            // Tenant context for the getObject() call below, and for the new user
            $this->tenantId = $domain->tenant_id;

            foreach ($packages as $package) {
                $userPackage = $this->getObject(Package::class, $package, 'title', false);
                if (!$userPackage) {
                    $this->error("Invalid package: {$package}");
                    return 1;
                }
                $packagesToAssign[] = $userPackage;
            }
        }

        try {
            $user = new User();
            $user->email = $email;
            $user->password = $password;
            $user->role = $role;
            $user->tenant_id = $this->tenantId;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        if (empty($owner)) {
            $owner = $user;
        }

        if ($role != User::ROLE_ADMIN && $role != User::ROLE_RESELLER && $role != User::ROLE_SERVICE) {
            // Validate email address
            if ($error = UsersController::validateEmail($email, $owner, $existingDeletedUser)) {
                $this->error("{$email}: {$error}");
                return 1;
            }
        }

        DB::beginTransaction();

        if ($existingDeletedUser) {
            $this->info("Force deleting existing but deleted user {$email}");
            $existingDeletedUser->forceDelete();
        }

        $user->save();

        foreach ($packagesToAssign as $package) {
            $owner->assignPackage($package, $user);
        }

        DB::commit();

        $this->info((string) $user->id);
    }

    /**
     * Validate email address for a new admin/reseller user
     *
     * @param string $email Email address
     *
     * @return ?string Error message
     */
    protected function validateUserWithRole($email): ?string
    {
        // Validate the email address (basicly just the syntax)
        $v = Validator::make(
            ['email' => $email],
            ['email' => ['required', new ExternalEmail()]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        // Check if an email is already taken
        if (
            User::emailExists($email, true)
            || User::aliasExists($email)
            || Group::emailExists($email, true)
            || Resource::emailExists($email, true)
            || SharedFolder::emailExists($email, true)
            || SharedFolder::aliasExists($email)
        ) {
            return "Email address is already in use";
        }

        return null;
    }
}
