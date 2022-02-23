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
        $params = [];

        // Role change
        if ($connection->role != $connection->getOriginal('role')) {
            $params['role'] = $connection->role;

            // TODO: When demoting publisher to subscriber maybe we should
            // destroy all streams using REST API. For now we trust the
            // participant browser to do this.
        }

        // Detect metadata changes for specified properties
        $keys = [
            'hand' => 'bool',
            'language' => '',
        ];

        foreach ($keys as $key => $type) {
            $newState = $connection->metadata[$key] ?? null;
            $oldState = $connection->getOriginal('metadata')[$key] ?? null;

            if ($newState !== $oldState) {
                $params[$key] = $type == 'bool' ? !empty($newState) : $newState;
            }
        }

        // Send the signal to all participants
        if (!empty($params)) {
            $params['connectionId'] = $connection->id;
            $connection->room->signal('connectionUpdate', $params);
        }
    }
}
