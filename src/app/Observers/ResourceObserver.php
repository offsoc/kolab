<?php

namespace App\Observers;

use App\Jobs\Resource\CreateJob;
use App\Jobs\Resource\DeleteJob;
use App\Jobs\Resource\UpdateJob;
use App\Resource;

class ResourceObserver
{
    /**
     * Handle the resource "creating" event.
     *
     * @param Resource $resource The resource
     */
    public function creating(Resource $resource): void
    {
        $resource->status |= Resource::STATUS_NEW;
    }

    /**
     * Handle the resource "created" event.
     *
     * @param Resource $resource The resource
     */
    public function created(Resource $resource)
    {
        $domainName = explode('@', $resource->email, 2)[1];

        $settings = [
            'folder' => "shared/Resources/{$resource->name}@{$domainName}",
        ];

        foreach ($settings as $key => $value) {
            $settings[$key] = [
                'key' => $key,
                'value' => $value,
                'resource_id' => $resource->id,
            ];
        }

        // Note: Don't use setSettings() here to bypass ResourceSetting observers
        // Note: This is a single multi-insert query
        $resource->settings()->insert(array_values($settings));

        // Create the resource in the backend (LDAP and IMAP)
        CreateJob::dispatch($resource->id);
    }

    /**
     * Handle the resource "deleted" event.
     *
     * @param Resource $resource The resource
     */
    public function deleted(Resource $resource)
    {
        if ($resource->isForceDeleting()) {
            return;
        }

        DeleteJob::dispatch($resource->id);
    }

    /**
     * Handle the resource "updated" event.
     *
     * @param Resource $resource The resource
     */
    public function updated(Resource $resource)
    {
        if (!$resource->trashed()) {
            UpdateJob::dispatch($resource->id);
        }

        // Update the folder property if name changed
        if ($resource->name != $resource->getOriginal('name')) {
            $domainName = explode('@', $resource->email, 2)[1];
            $folder = "shared/Resources/{$resource->name}@{$domainName}";

            // Note: This does not invoke ResourceSetting observer events, good.
            $resource->settings()->where('key', 'folder')->update(['value' => $folder]);
        }
    }
}
