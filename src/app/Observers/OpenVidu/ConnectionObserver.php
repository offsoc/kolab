<?php

namespace App\Observers\OpenVidu;

use App\OpenVidu\Connection;

class ConnectionObserver
{
    /**
     * Handle the OpenVidu connection "updated" event.
     *
     * @param \App\OpenVidu\Connection $connection The connection.
     *
     * @return void
     */
    public function updated(Connection $connection)
    {
        if ($connection->role != $connection->getOriginal('role')) {
            $params = [
                'connectionId' => $connection->id,
                'role' => $connection->role
            ];

            // Send the signal to all participants
            $connection->room->signal('connectionUpdate', $params);

            // TODO: When demoting publisher to subscriber maybe we should
            // destroy all streams using REST API. For now we trust the
            // participant browser to do this.
        }
    }
}
