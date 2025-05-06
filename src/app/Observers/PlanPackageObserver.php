<?php

namespace App\Observers;

use App\PlanPackage;

class PlanPackageObserver
{
    /**
     * Handle the "creating" event on an PlanPackage relation.
     *
     * Ensures that the entries belong to the same tenant.
     *
     * @param PlanPackage $planPackage The plan-package relation
     */
    public function creating(PlanPackage $planPackage)
    {
        $package = $planPackage->package;
        $plan = $planPackage->plan;

        if ($package->tenant_id != $plan->tenant_id) {
            throw new \Exception("Package and Plan owned by different tenants");
        }
    }
}
