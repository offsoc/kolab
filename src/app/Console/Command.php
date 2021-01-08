<?php

namespace App\Console;

abstract class Command extends \Illuminate\Console\Command
{
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
     * @param string $domain Domain ID or namespace
     *
     * @return \App\Domain|null
     */
    public function getDomain($domain)
    {
        return $this->getObject(\App\Domain::class, $domain, 'namespace');
    }

    /**
     * Find an object.
     *
     * @param string $objectClass      The name of the class
     * @param string $objectIdOrTitle  The name of a database field to match.
     * @param string|null $objectTitle An additional database field to match.
     *
     * @return mixed
     */
    public function getObject($objectClass, $objectIdOrTitle, $objectTitle = null)
    {
        if ($this->hasOption('with-deleted') && $this->option('with-deleted')) {
            $object = $objectClass::withTrashed()->find($objectIdOrTitle);
        } else {
            $object = $objectClass::find($objectIdOrTitle);
        }

        if (!$object && !empty($objectTitle)) {
            if ($this->hasOption('with-deleted') && $this->option('with-deleted')) {
                $object = $objectClass::withTrashed()->where($objectTitle, $objectIdOrTitle)->first();
            } else {
                $object = $objectClass::where($objectTitle, $objectIdOrTitle)->first();
            }
        }

        return $object;
    }

    /**
     * Find the user.
     *
     * @param string $user User ID or email
     *
     * @return \App\User|null
     */
    public function getUser($user)
    {
        return $this->getObject(\App\User::class, $user, 'email');
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
