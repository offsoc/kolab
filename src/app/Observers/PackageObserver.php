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
     * @param Package $package The Package being created.
     *
     * @return void
     */
    public function creating(Package $package)
    {
        $package->tenant_id = \config('app.tenant_id');
    }
}
