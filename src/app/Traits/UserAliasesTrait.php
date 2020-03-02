<?php

namespace App\Traits;

use App\UserAlias;
use Illuminate\Support\Facades\Cache;

trait UserAliasesTrait
{
    /**
     * A helper to update user aliases list.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.erg']);
     * $user->setAliases(['alias1@other.org', 'alias2@other.org']);
     * ```
     *
     * @param array $aliases An array of email addresses
     *
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $existing_aliases = $this->aliases()->get()->map(function ($alias) {
            return $alias->alias;
        })->toArray();

        $aliases = array_map('strtolower', $aliases);
        $aliases = array_unique($aliases);

        foreach (array_diff($aliases, $existing_aliases) as $alias) {
            $this->aliases()->create(['alias' => $alias]);
        }

        foreach (array_diff($existing_aliases, $aliases) as $alias) {
            $this->aliases()->where('alias', $alias)->delete();
        }
    }
}
