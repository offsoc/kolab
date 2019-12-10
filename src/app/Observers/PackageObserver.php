<?php

namespace App\Observers;

use App\Package;

/**
 * This is an observer for the Package model definition.
 */
class PackageObserver
{
    /**
     * Handle the "creating" event on an Package.
     *
     * Ensures that the entry uses a custom ID (uuid).
     *
     * @param Package $package The Package being created.
     *
     * @return void
     */
    public function creating(Package $package)
    {
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Package::find($allegedly_unique)) {
                $package->{$package->getKeyName()} = $allegedly_unique;
                break;
            }
        }
    }
}
