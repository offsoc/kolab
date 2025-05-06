<?php

namespace App\Observers;

use App\Jobs\Resource\UpdateJob;
use App\ResourceSetting;

class ResourceSettingObserver
{
    /**
     * Handle the resource setting "created" event.
     *
     * @param ResourceSetting $resourceSetting Settings object
     */
    public function created(ResourceSetting $resourceSetting)
    {
        $this->dispatchUpdateJob($resourceSetting);
    }

    /**
     * Handle the resource setting "updated" event.
     *
     * @param ResourceSetting $resourceSetting Settings object
     */
    public function updated(ResourceSetting $resourceSetting)
    {
        $this->dispatchUpdateJob($resourceSetting);
    }

    /**
     * Handle the resource setting "deleted" event.
     *
     * @param ResourceSetting $resourceSetting Settings object
     */
    public function deleted(ResourceSetting $resourceSetting)
    {
        $this->dispatchUpdateJob($resourceSetting);
    }

    /**
     * Dispatch resource update job (if needed)
     *
     * @param ResourceSetting $resourceSetting Settings object
     */
    private function dispatchUpdateJob(ResourceSetting $resourceSetting): void
    {
        if ($resourceSetting->isBackendSetting()) {
            $props = [$resourceSetting->key => $resourceSetting->getOriginal('value')];
            UpdateJob::dispatch($resourceSetting->resource_id, $props);
        }
    }
}
