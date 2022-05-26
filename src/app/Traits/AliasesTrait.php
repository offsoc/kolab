<?php

namespace App\Traits;

trait AliasesTrait
{
    /**
     * Email aliases of this object.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
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
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \App\Utils::emailToLower($email);
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
     *
     * @return void
     */
    public function setAliases(array $aliases): void
    {
        $aliases = array_map('\App\Utils::emailToLower', $aliases);
        $aliases = array_unique($aliases);

        $existing_aliases = [];

        foreach ($this->aliases()->get() as $alias) {
            /** @var \App\UserAlias|\App\SharedFolderAlias $alias */
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
