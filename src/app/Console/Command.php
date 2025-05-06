<?php

namespace App\Console;

use App\Domain;
use App\Group;
use App\Resource;
use App\SharedFolder;
use App\Tenant;
use App\Traits\BelongsToTenantTrait;
use App\User;
use App\Wallet;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Console\Helper\ProgressBar;

abstract class Command extends \Illuminate\Console\Command
{
    /**
     * This needs to be here to be used.
     *
     * @var string
     */
    protected $commandPrefix = '';

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
     * @var bool
     */
    protected $dangerous = false;

    /**
     * Tenant context for the command
     *
     * @var ?int
     */
    protected $tenantId;

    /** @var bool Adds --tenant option handler */
    protected $withTenant = false;

    /**
     * Apply current tenant context to the query
     *
     * @param mixed $object The model class object
     *
     * @return mixed The model class object
     */
    protected function applyTenant($object)
    {
        if ($this->commandPrefix == 'scalpel') {
            return $object;
        }

        if (!$this->tenantId) {
            return $object;
        }

        $modelsWithOwner = [
            Wallet::class,
        ];

        // Add tenant filter
        if (in_array(BelongsToTenantTrait::class, class_uses($object::class))) {
            $context = new User();
            $context->tenant_id = $this->tenantId;

            $object = $object->withObjectTenantContext($context);
        } elseif (in_array($object::class, $modelsWithOwner)) {
            $object = $object->whereExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('users')
                    ->whereRaw('wallets.user_id = users.id')
                    ->whereRaw("users.tenant_id = {$this->tenantId}");
            });
        }

        return $object;
    }

    /**
     * Shortcut to creating a progress bar of a particular format with a particular message.
     *
     * @param int    $count   Number of progress steps
     * @param string $message The description
     *
     * @return ProgressBar
     */
    protected function createProgressBar($count, $message = null)
    {
        $bar = $this->output->createProgressBar($count);
        $bar->setFormat(
            '%current:7s%/%max:7s% [%bar%] %percent:3s%% %elapsed:7s%/%estimated:-7s% %message% '
        );

        if ($message) {
            $bar->setMessage("{$message}...");
        }

        $bar->start();

        return $bar;
    }

    /**
     * Find the domain.
     *
     * @param string $domain      Domain ID or namespace
     * @param bool   $withDeleted Include deleted
     *
     * @return Domain|null
     */
    public function getDomain($domain, $withDeleted = false)
    {
        return $this->getObject(Domain::class, $domain, 'namespace', $withDeleted);
    }

    /**
     * Find a group.
     *
     * @param string $group       Group ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return Group|null
     */
    public function getGroup($group, $withDeleted = false)
    {
        return $this->getObject(Group::class, $group, 'email', $withDeleted);
    }

    /**
     * Find an object.
     *
     * @param string      $objectClass     The name of the class
     * @param string      $objectIdOrTitle the name of a database field to match
     * @param string|null $objectTitle     an additional database field to match
     * @param bool        $withDeleted     Act as if --with-deleted was used
     *
     * @return mixed
     */
    public function getObject($objectClass, $objectIdOrTitle, $objectTitle = null, $withDeleted = false)
    {
        if (!$withDeleted) {
            // @phpstan-ignore-next-line
            $withDeleted = $this->hasOption('with-deleted') && $this->option('with-deleted');
        }

        $object = $this->getObjectModel($objectClass, $withDeleted)->find($objectIdOrTitle);

        if (!$object && !empty($objectTitle)) {
            $object = $this->getObjectModel($objectClass, $withDeleted)
                ->where($objectTitle, $objectIdOrTitle)->first();
        }

        if (!$this->tenantId && $object && !empty($object->tenant_id)) {
            $this->tenantId = $object->tenant_id;
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

        return $this->applyTenant($model);
    }

    /**
     * Find a resource.
     *
     * @param string $resource    Resource ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return Resource|null
     */
    public function getResource($resource, $withDeleted = false)
    {
        return $this->getObject(Resource::class, $resource, 'email', $withDeleted);
    }

    /**
     * Find a shared folder.
     *
     * @param string $folder      Folder ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return SharedFolder|null
     */
    public function getSharedFolder($folder, $withDeleted = false)
    {
        return $this->getObject(SharedFolder::class, $folder, 'email', $withDeleted);
    }

    /**
     * Find the user.
     *
     * @param string $user        User ID or email
     * @param bool   $withDeleted Include deleted
     *
     * @return User|null
     */
    public function getUser($user, $withDeleted = false)
    {
        return $this->getObject(User::class, $user, 'email', $withDeleted);
    }

    /**
     * Find the wallet.
     *
     * @param string $wallet Wallet ID
     *
     * @return Wallet|null
     */
    public function getWallet($wallet)
    {
        return $this->getObject(Wallet::class, $wallet);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        if ($this->dangerous) {
            $this->warn(
                "This command is a dangerous scalpel command with potentially significant unintended consequences"
            );

            $confirmation = $this->confirm("Are you sure you understand what's about to happen?");

            if (!$confirmation) {
                $this->info("Better safe than sorry.");
                exit(0);
            }

            $this->info("Vámonos!");
        }

        // @phpstan-ignore-next-line
        if ($this->withTenant && $this->hasOption('tenant') && ($tenantId = $this->option('tenant'))) {
            $tenant = $this->getObject(Tenant::class, $tenantId, 'title');
            if (!$tenant) {
                $this->error("Tenant {$tenantId} not found");
                return 1;
            }

            $this->tenantId = $tenant->id;
        } else {
            $this->tenantId = \config('app.tenant_id');
        }
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
        // Haven't figured out yet, how to test if this command implements an option for additional attributes.
        // if (!in_array('attr', $this->options())) {
        // return $entry->{$entry->getKeyName()};
        // }

        $str = [
            $entry->{$entry->getKeyName()},
        ];

        // @phpstan-ignore-next-line
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
