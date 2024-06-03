<?php

namespace App\Console\Commands\User;

use App\Http\Controllers\API\V4\UsersController;
use App\Rules\ExternalEmail;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

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

        $existingDeletedUser = null;
        $packagesToAssign = [];

        if ($role === User::ROLE_ADMIN || $role === User::ROLE_RESELLER) {
            if ($error = $this->validateUserWithRole($email)) {
                $this->error($error);
                return 1;
            }

            // TODO: Assigning user to an existing account
            // TODO: Making him an operator of the reseller wallet
        } else {
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

            // Validate email address
            if ($error = UsersController::validateEmail($email, $owner, $existingDeletedUser)) {
                $this->error("{$email}: {$error}");
                return 1;
            }

            foreach ($packages as $package) {
                $userPackage = $this->getObject(\App\Package::class, $package, 'title', false);
                if (!$userPackage) {
                    $this->error("Invalid package: {$package}");
                    return 1;
                }
                $packagesToAssign[] = $userPackage;
            }
        }

        if (!$password) {
            $password = \App\Utils::generatePassphrase();
        }

        try {
            $user = new \App\User();
            $user->email = $email;
            $user->password = $password;
            $user->role = $role;
        } catch (\Exception $e) {
            $this->error($e->getMessage());
            return 1;
        }

        DB::beginTransaction();

        if ($existingDeletedUser) {
            $this->info("Force deleting existing but deleted user {$email}");
            $existingDeletedUser->forceDelete();
        }

        $user->save();

        if (empty($owner)) {
            $owner = $user;
        }

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
            || \App\Group::emailExists($email, true)
            || \App\Resource::emailExists($email, true)
            || \App\SharedFolder::emailExists($email, true)
            || \App\SharedFolder::aliasExists($email)
        ) {
            return "Email address is already in use";
        }

        return null;
    }
}
