<?php

namespace App\Observers;

use App\EventLog;
use App\Utils;

class EventLogObserver
{
    /**
     * Ensure the event entry ID is a custom ID (uuid).
     *
     * @param EventLog $eventlog The EventLog object
     */
    public function creating(EventLog $eventlog): void
    {
        if (!isset($eventlog->user_email)) {
            $eventlog->user_email = Utils::userEmailOrNull();
        }

        if (!isset($eventlog->type)) {
            throw new \Exception("Unset event type");
        }
    }
}
