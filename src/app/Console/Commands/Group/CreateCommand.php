<?php

namespace App\Console\Commands\Group;

use App\Console\Command;
use App\Domain;
use App\Group;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

/**
 * Create a (mail-enabled) distribution group.
 *
 * @see \App\Console\Commands\Scalpel\Group\CreateCommand
 */
class CreateCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'group:create {email} {--member=*}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = "Create a group.";

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $email = $this->argument('email');
        $members = $this->option('member');

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

        // Validate group email address
        foreach ($members as $i => $member) {
            if ($error = $this->validateMemberEmail($member)) {
                $this->error("{$member}: $error");
                return 1;
            }
            if (\strtolower($member) === \strtolower($email)) {
                $this->error("{$member}: Cannot be the same as the group address.");
                return 1;
            }
        }

        // Validate members addresses
        if ($error = $this->validateGroupEmail($email, $owner)) {
            $this->error("{$email}: {$error}");
            return 1;
        }

        DB::beginTransaction();

        // Create the group
        $group = new Group();
        $group->email = $email;
        $group->members = $members;
        $group->save();

        $group->assignToWallet($owner->wallets->first());

        DB::commit();

        $this->info($group->id);
    }

    /**
     * Validate an email address for use as a group member
     *
     * @param string $email Email address
     *
     * @return ?string Error message on validation error
     */
    public static function validateMemberEmail(string $email): ?string
    {
        $v = Validator::make(
            ['email' => $email],
            ['email' => [new \App\Rules\ExternalEmail()]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }

        return null;
    }

    /**
     * Validate an email address for use as a group email
     *
     * @param string    $email Email address
     * @param \App\User $user  The group owner
     *
     * @return ?string Error message on validation error
     */
    public static function validateGroupEmail(string $email, \App\User $user): ?string
    {
        if (strpos($email, '@') === false) {
            return \trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        list($login, $domain) = explode('@', \strtolower($email));

        if (strlen($login) === 0 || strlen($domain) === 0) {
            return \trans('validation.entryinvalid', ['attribute' => 'email']);
        }

        // Check if domain exists
        $domain = Domain::where('namespace', $domain)->first();
/*
        if (empty($domain)) {
            return \trans('validation.domainnotavailable');
        }

        if ($domain->isPublic()) {
            return \trans('validation.domainnotavailable');
        }
*/
        // Validate login part alone
        $v = Validator::make(
            ['email' => $login],
            ['email' => [new \App\Rules\UserEmailLocal(!$domain->isPublic())]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['email'][0];
        }
/*
        // Check if it is one of domains available to the user
        $domains = \collect($user->domains())->pluck('namespace')->all();

        if (!in_array($domain->namespace, $domains)) {
            // return \trans('validation.entryexists', ['attribute' => 'domain']);
            return \trans('validation.domainnotavailable');
        }
*/
        // Check if a user with specified address already exists
        if (User::emailExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        // Check if an alias with specified address already exists.
        if (User::aliasExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        if (Group::emailExists($email)) {
            return \trans('validation.entryexists', ['attribute' => 'email']);
        }

        return null;
    }
}
