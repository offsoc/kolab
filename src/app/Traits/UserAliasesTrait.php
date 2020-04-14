<?php

namespace App\Traits;

trait UserAliasesTrait
{
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
