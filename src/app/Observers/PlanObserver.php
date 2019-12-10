<?php

namespace App\Observers;

use App\Plan;

/**
 * This is an observer for the Plan model definition.
 */
class PlanObserver
{
    /**
     * Handle the "creating" event on an Plan.
     *
     * Ensures that the entry uses a custom ID (uuid).
     *
     * @param Plan $plan The Plan being created.
     *
     * @return void
     */
    public function creating(Plan $plan)
    {
        while (true) {
            $allegedly_unique = \App\Utils::uuidStr();
            if (!Plan::find($allegedly_unique)) {
                $plan->{$plan->getKeyName()} = $allegedly_unique;
                break;
            }
        }
    }
}
