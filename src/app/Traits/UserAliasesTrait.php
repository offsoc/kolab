<?php

namespace App\Traits;

trait UserAliasesTrait
{
    /**
     * Find whether an email address exists as an alias
     * (including aliases of deleted users).
     *
     * @param string $email Email address
     *
     * @return bool True if found, False otherwise
     */
    public static function aliasExists(string $email): bool
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \strtolower($email);

        $count = \App\UserAlias::where('alias', $email)->count();

        return $count > 0;
    }

    /**
     * A helper to update user aliases list.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.org']);
     * $user->setAliases(['alias1@other.org', 'alias2@other.org']);
     * ```
     *
     * @param array $aliases An array of email addresses
     *
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $aliases = array_map('strtolower', $aliases);
        $aliases = array_unique($aliases);

        $existing_aliases = [];

        foreach ($this->aliases()->get() as $alias) {
            if (!in_array($alias->alias, $aliases)) {
                $alias->delete();
            } else {
                $existing_aliases[] = $alias->alias;
            }
        }

        foreach (array_diff($aliases, $existing_aliases) as $alias) {
            $this->aliases()->create(['alias' => $alias]);
        }
    }
}
