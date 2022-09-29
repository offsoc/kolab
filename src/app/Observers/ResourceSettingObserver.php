<?php

namespace App\Observers;

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
        $this->dispatchUpdateJob($resourceSetting);
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
        $this->dispatchUpdateJob($resourceSetting);
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
        $this->dispatchUpdateJob($resourceSetting);
    }

    /**
     * Dispatch resource update job (if needed)
     *
     * @param \App\ResourceSetting $resourceSetting Settings object
     */
    private function dispatchUpdateJob(ResourceSetting $resourceSetting): void
    {
        if ((\config('app.with_ldap') && in_array($resourceSetting->key, \App\Backends\LDAP::RESOURCE_SETTINGS))
            || in_array($resourceSetting->key, \App\Backends\IMAP::RESOURCE_SETTINGS)
        ) {
            $props = [$resourceSetting->key => $resourceSetting->getOriginal('value')];
            \App\Jobs\Resource\UpdateJob::dispatch($resourceSetting->resource_id, $props);
        }
    }
}
