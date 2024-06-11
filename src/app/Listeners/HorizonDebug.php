<?php

namespace App\Listeners;

use Laravel\Horizon\Events\JobDeleted;
use Laravel\Horizon\Events\JobPushed;
use Laravel\Horizon\Events\JobReleased;
use Laravel\Horizon\Events\JobReserved;
use Laravel\Horizon\Events\JobsMigrated;
use Illuminate\Events\Dispatcher;

class HorizonDebug
{
    /**
     * Register the listeners for the subscriber.
     *
     * @return array<string, string>
     */
    public function subscribe(Dispatcher $events): array
    {
        if (!\config('app.debug')) {
            return [];
        }

        return [
            JobPushed::class => 'handle',
            JobDeleted::class => 'handle',
            JobReleased::class => 'handle',
            JobReleased::class => 'handle',
            JobsMigrated::class => 'handle'
        ];
    }

    /**
     * Handle the event.
     *
     * @param object $event An event object
     */
    public function handle(object $event): void
    {
        $line = " Job: " . get_class($event);
        $line .= " Queue: " . $event->queue;
        $line .= " Connection: " . $event->connectionName;
        \Log::debug("[Horizon] {$line}");
    }
}



// Event::listen(JobPushed::class, function(JobPushed $event){
//     \Log::debug('JobPushed Event Fired ', [
//         'connection' => $event->connectionName,
//         'queue' => $event->queue,
//         'payload' => [
//             'id' => $event->payload->id(),
//             'displayName' => $event->payload->displayName(),
//             'commandName' => $event->payload->commandName(),
//             'isRetry' => $event->payload->isRetry(),
//             'retryOf' => $event->payload->retryOf(),
//         ]
//     ]);
// });
