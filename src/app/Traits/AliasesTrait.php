<?php

namespace App\Traits;

use App\SharedFolderAlias;
use App\UserAlias;
use App\Utils;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait AliasesTrait
{
    /**
     * Email aliases of this object.
     *
     * @return HasMany
     */
    public function aliases()
    {
        return $this->hasMany(static::class . 'Alias');
    }

    /**
     * Find whether an email address exists as an alias
     *
     * @param string $email Email address
     *
     * @return bool True if found, False otherwise
     */
    public static function aliasExists(string $email): bool
    {
        if (!str_contains($email, '@')) {
            return false;
        }

        $email = Utils::emailToLower($email);
        $class = static::class . 'Alias';

        return $class::where('alias', $email)->count() > 0;
    }

    /**
     * A helper to update object's aliases list.
     *
     * Example Usage:
     *
     * ```php
     * $user = User::firstOrCreate(['email' => 'some@other.org']);
     * $user->setAliases(['alias1@other.org', 'alias2@other.org']);
     * ```
     *
     * @param array $aliases An array of email addresses
     */
    public function setAliases(array $aliases): void
    {
        $aliases = array_map('\App\Utils::emailToLower', $aliases);
        $aliases = array_unique($aliases);

        $existing_aliases = [];

        foreach ($this->aliases()->get() as $alias) {
            /** @var UserAlias|SharedFolderAlias $alias */
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
