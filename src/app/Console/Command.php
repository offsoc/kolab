<?php

namespace App\Console;

use Illuminate\Support\Facades\DB;

abstract class Command extends \Illuminate\Console\Command
{
    /**
     * This needs to be here to be used.
     *
     * @var null
     */
    protected $commandPrefix = null;

    /**
     * Annotate this command as being dangerous for any potential unintended consequences.
     *
     * Commands are considered dangerous if;
     *
     * * observers are deliberately not triggered, meaning that the deletion of an object model that requires the
     *   associated observer to clean some things up, or charge a wallet or something, are deliberately not triggered,
     *
     * * deletion of objects and their relations rely on database foreign keys with obscure cascading,
     *
     * * a command will result in the permanent, irrecoverable loss of data.
     *
     * @var boolean
     */
    protected $dangerous = false;

    /**
     * Find the domain.
     *
     * @param string $domain      Domain ID or namespace
     * @param bool   $withDeleted Include deleted
     *
     * @return \App\Domain|null
     */
    public function getDomain($domain, $withDeleted = false)
    {
        return $this->getObject(\App\Domain::class, $domain, 'namespace', $withDeleted);
    }

    /**
     * Find a group.
     *
     * @param string $group       Group ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return \App\Group|null
     */
    public function getGroup($group, $withDeleted = false)
    {
        return $this->getObject(\App\Group::class, $group, 'email', $withDeleted);
    }

    /**
     * Find an object.
     *
     * @param string $objectClass      The name of the class
     * @param string $objectIdOrTitle  The name of a database field to match.
     * @param string|null $objectTitle An additional database field to match.
     * @param bool        $withDeleted Act as if --with-deleted was used
     *
     * @return mixed
     */
    public function getObject($objectClass, $objectIdOrTitle, $objectTitle = null, $withDeleted = false)
    {
        if (!$withDeleted) {
            $withDeleted = $this->hasOption('with-deleted') && $this->option('with-deleted');
        }

        $object = $this->getObjectModel($objectClass, $withDeleted)->find($objectIdOrTitle);

        if (!$object && !empty($objectTitle)) {
            $object = $this->getObjectModel($objectClass, $withDeleted)
                ->where($objectTitle, $objectIdOrTitle)->first();
        }

        return $object;
    }

    /**
     * Returns a preconfigured Model object for a specified class.
     *
     * @param string $objectClass The name of the class
     * @param bool   $withDeleted Include withTrashed() query
     *
     * @return mixed
     */
    protected function getObjectModel($objectClass, $withDeleted = false)
    {
        if ($withDeleted) {
            $model = $objectClass::withTrashed();
        } else {
            $model = new $objectClass();
        }

        if ($this->commandPrefix == 'scalpel') {
            return $model;
        }

        $modelsWithTenant = [
            \App\Discount::class,
            \App\Domain::class,
            \App\Group::class,
            \App\Package::class,
            \App\Plan::class,
            \App\Resource::class,
            \App\Sku::class,
            \App\User::class,
        ];

        $modelsWithOwner = [
            \App\Wallet::class,
        ];

        $tenantId = \config('app.tenant_id');

        // Add tenant filter
        if (in_array($objectClass, $modelsWithTenant)) {
            $model = $model->withEnvTenantContext();
        } elseif (in_array($objectClass, $modelsWithOwner)) {
            $model = $model->whereExists(function ($query) use ($tenantId) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereRaw('wallets.user_id = users.id')
                    ->whereRaw('users.tenant_id ' . ($tenantId ? "= $tenantId" : 'is null'));
            });
        }

        return $model;
    }

    /**
     * Find a resource.
     *
     * @param string $resource    Resource ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return \App\Resource|null
     */
    public function getResource($resource, $withDeleted = false)
    {
        return $this->getObject(\App\Resource::class, $resource, 'email', $withDeleted);
    }

    /**
     * Find the user.
     *
     * @param string $user        User ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return \App\User|null
     */
    public function getUser($user, $withDeleted = false)
    {
        return $this->getObject(\App\User::class, $user, 'email', $withDeleted);
    }

    /**
     * Find the wallet.
     *
     * @param string $wallet Wallet ID
     *
     * @return \App\Wallet|null
     */
    public function getWallet($wallet)
    {
        return $this->getObject(\App\Wallet::class, $wallet, null);
    }

    public function handle()
    {
        if ($this->dangerous) {
            $this->warn(
                "This command is a dangerous scalpel command with potentially significant unintended consequences"
            );

            $confirmation = $this->confirm("Are you sure you understand what's about to happen?");

            if (!$confirmation) {
                $this->info("Better safe than sorry.");
                return false;
            }

            $this->info("Vámonos!");
        }

        return true;
    }

    /**
     * Return a string for output, with any additional attributes specified as well.
     *
     * @param mixed $entry An object
     *
     * @return string
     */
    protected function toString($entry)
    {
        /**
         * Haven't figured out yet, how to test if this command implements an option for additional
         * attributes.
        if (!in_array('attr', $this->options())) {
            return $entry->{$entry->getKeyName()};
        }
        */

        $str = [
            $entry->{$entry->getKeyName()}
        ];

        foreach ($this->option('attr') as $attr) {
            if ($attr == $entry->getKeyName()) {
                $this->warn("Specifying {$attr} is not useful.");
                continue;
            }

            if (!array_key_exists($attr, $entry->toArray())) {
                $this->error("Attribute {$attr} isn't available");
                continue;
            }

            if (is_numeric($entry->{$attr})) {
                $str[] = $entry->{$attr};
            } else {
                $str[] = !empty($entry->{$attr}) ? $entry->{$attr} : "null";
            }
        }

        return implode(" ", $str);
    }
}
