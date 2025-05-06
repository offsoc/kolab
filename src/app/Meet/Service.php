<?php

namespace App\Meet;

use Illuminate\Support\Facades\Http;

/**
 * The Meet service utilities.
 */
class Service
{
    /**
     * Select a Meet server (for a room)
     *
     * This needs to always result in the same server for the same room,
     * so all participants end up on the same server.
     */
    private static function selectMeetServer($roomName = null): string
    {
        $urls = \config('meet.api_urls');

        $count = count($urls);

        if ($count == 0) {
            throw new \Exception("No meet server configured.");
        }

        // Select a random backend.
        // If the names are evenly distributed this should theoretically result in an even distribution.
        $index = 0;
        if ($count > 1 && $roomName) {
            $index = abs(intval(hash('crc32b', $roomName), 16) % $count);
        }

        return $urls[$index];
    }

    /**
     * Creates HTTP client for connection to the Meet server
     *
     * @param ?string $roomName Room name
     *
     * @return Http client instance
     */
    public static function clientForRoom($roomName = null)
    {
        $url = self::selectMeetServer($roomName);

        return Http::withSlowLog()
            ->withOptions([
                'verify' => \config('meet.api_verify_tls'),
            ])
            ->withHeaders([
                'X-Auth-Token' => \config('meet.api_token'),
            ])
            ->baseUrl($url)
            ->timeout(10)
            ->connectTimeout(10);
    }

    /**
     * Creates HTTP client for connection to the Meet server.
     * Server location can be provided, otherwise first server on the list is used.
     *
     * @param ?string $baseUrl Server location
     *
     * @return Http client instance
     */
    public static function client($baseUrl = null)
    {
        if (empty($baseUrl)) {
            $baseUrl = self::selectMeetServer();
        }

        return Http::withSlowLog()
            ->withOptions([
                'verify' => \config('meet.api_verify_tls'),
            ])
            ->withHeaders([
                'X-Auth-Token' => \config('meet.api_token'),
            ])
            ->baseUrl($baseUrl)
            ->timeout(10)
            ->connectTimeout(10);
    }
}
