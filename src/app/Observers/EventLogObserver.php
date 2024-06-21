<?php

namespace App\Observers;

use App\EventLog;

class EventLogObserver
{
    /**
     * Ensure the event entry ID is a custom ID (uuid).
     *
     * @param \App\EventLog $eventlog The EventLog object
     */
    public function creating(EventLog $eventlog): void
    {
        if (!isset($eventlog->user_email)) {
            $eventlog->user_email = \App\Utils::userEmailOrNull();
        }

        if (!isset($eventlog->type)) {
            throw new \Exception("Unset event type");
        }
    }
}
