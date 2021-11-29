<?php

namespace App\Observers;

use App\Backends\LDAP;
use App\ResourceSetting;

class ResourceSettingObserver
{
    /**
     * Handle the resource setting "created" event.
     *
     * @param \App\ResourceSetting $resourceSetting Settings object
     *
     * @return void
     */
    public function created(ResourceSetting $resourceSetting)
    {
        if (in_array($resourceSetting->key, LDAP::RESOURCE_SETTINGS)) {
            \App\Jobs\Resource\UpdateJob::dispatch($resourceSetting->resource_id);
        }
    }

    /**
     * Handle the resource setting "updated" event.
     *
     * @param \App\ResourceSetting $resourceSetting Settings object
     *
     * @return void
     */
    public function updated(ResourceSetting $resourceSetting)
    {
        if (in_array($resourceSetting->key, LDAP::RESOURCE_SETTINGS)) {
            \App\Jobs\Resource\UpdateJob::dispatch($resourceSetting->resource_id);
        }
    }

    /**
     * Handle the resource setting "deleted" event.
     *
     * @param \App\ResourceSetting $resourceSetting Settings object
     *
     * @return void
     */
    public function deleted(ResourceSetting $resourceSetting)
    {
        if (in_array($resourceSetting->key, LDAP::RESOURCE_SETTINGS)) {
            \App\Jobs\Resource\UpdateJob::dispatch($resourceSetting->resource_id);
        }
    }
}
