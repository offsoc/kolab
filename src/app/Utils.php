<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Ramsey\Uuid\Uuid;

/**
 * Small utility functions for App.
 */
class Utils
{
    // Note: Removed '0', 'O', '1', 'I' as problematic with some fonts
    public const CHARS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

    /**
     * Return the number of days in the month prior to this one.
     *
     * @return int
     */
    public static function daysInLastMonth()
    {
        $start = new Carbon('first day of last month');
        $end = new Carbon('last day of last month');

        return $start->diffInDays($end) + 1;
    }

    /**
     * Provide all unique combinations of elements in $input, with order and duplicates irrelevant.
     *
     * @param array $input The input array of elements.
     *
     * @return array[]
     */
    public static function powerSet(array $input): array
    {
        $output = [];

        for ($x = 0; $x < count($input); $x++) {
            self::combine($input, $x + 1, 0, [], 0, $output);
        }

        return $output;
    }

    /**
     * Returns the current user's email address or null.
     *
     * @return string
     */
    public static function userEmailOrNull(): ?string
    {
        $user = Auth::user();

        if (!$user) {
            return null;
        }

        return $user->email;
    }

    /**
     * Returns a random string consisting of a quantity of segments of a certain length joined.
     *
     * Example:
     *
     * ```php
     * $roomName = strtolower(\App\Utils::randStr(3, 3, '-');
     * // $roomName == '3qb-7cs-cjj'
     * ```
     *
     * @param int $length  The length of each segment
     * @param int $qty     The quantity of segments
     * @param string $join The string to use to join the segments
     *
     * @return string
     */
    public static function randStr($length, $qty = 1, $join = '')
    {
        $chars = env('SHORTCODE_CHARS', self::CHARS);

        $randStrs = [];

        for ($x = 0; $x < $qty; $x++) {
            $randStrs[$x] = [];

            for ($y = 0; $y < $length; $y++) {
                $randStrs[$x][] = $chars[rand(0, strlen($chars) - 1)];
            }

            shuffle($randStrs[$x]);

            $randStrs[$x] = implode('', $randStrs[$x]);
        }

        return implode($join, $randStrs);
    }

    /**
     * Returns a UUID in the form of an integer.
     *
     * @return integer
     */
    public static function uuidInt(): int
    {
        $hex = Uuid::uuid4();
        $bin = pack('h*', str_replace('-', '', $hex));
        $ids = unpack('L', $bin);
        $id = array_shift($ids);

        return $id;
    }

    /**
     * Returns a UUID in the form of a string.
     *
     * @return string
     */
    public static function uuidStr(): string
    {
        return Uuid::uuid4()->toString();
    }

    private static function combine($input, $r, $index, $data, $i, &$output): void
    {
        $n = count($input);

        // Current cobination is ready
        if ($index == $r) {
            $output[] = array_slice($data, 0, $r);
            return;
        }

        // When no more elements are there to put in data[]
        if ($i >= $n) {
            return;
        }

        // current is included, put next at next location
        $data[$index] = $input[$i];
        self::combine($input, $r, $index + 1, $data, $i + 1, $output);

        // current is excluded, replace it with next (Note that i+1
        // is passed, but index is not changed)
        self::combine($input, $r, $index, $data, $i + 1, $output);
    }

    /**
     * Create self URL
     *
     * @param string $route Route/Path
     * @todo Move this to App\Http\Controllers\Controller
     *
     * @return string Full URL
     */
    public static function serviceUrl(string $route): string
    {
        $url = \url($route);

        $app_url = trim(\config('app.url'), '/');
        $pub_url = trim(\config('app.public_url'), '/');

        if ($pub_url != $app_url) {
            $url = str_replace($app_url, $pub_url, $url);
        }

        return $url;
    }

    /**
     * Create a configuration/environment data to be passed to
     * the UI
     *
     * @todo Move this to App\Http\Controllers\Controller
     *
     * @return array Configuration data
     */
    public static function uiEnv(): array
    {
        $countries = include resource_path('countries.php');
        $req_domain = preg_replace('/:[0-9]+$/', '', request()->getHttpHost());
        $sys_domain = \config('app.domain');
        $path = request()->path();

        $opts = ['app.name', 'app.url', 'app.domain'];
        $env = \app('config')->getMany($opts);

        $env['countries'] = $countries ?: [];
        $env['view'] = 'root';
        $env['jsapp'] = 'user.js';

        if ($path == 'meet' || strpos($path, 'meet/') === 0) {
            $env['view'] = 'meet';
            $env['jsapp'] = 'meet.js';
        } elseif ($req_domain == "admin.$sys_domain") {
            $env['jsapp'] = 'admin.js';
        }

        $env['paymentProvider'] = \config('services.payment_provider');
        $env['stripePK'] = \config('services.stripe.public_key');

        return $env;
    }
}
