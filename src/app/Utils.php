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
     * Generate a passphrase. Not intended for use in production, so limited to environments that are not production.
     *
     * @return string
     */
    public static function generatePassphrase()
    {
        $alphaLow = 'abcdefghijklmnopqrstuvwxyz';
        $alphaUp = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $num = '0123456789';
        $stdSpecial = '~`!@#$%^&*()-_+=[{]}\\|\'";:/?.>,<';

        $source = $alphaLow . $alphaUp . $num . $stdSpecial;

        $result = '';

        for ($x = 0; $x < 16; $x++) {
            $result .= substr($source, rand(0, (strlen($source) - 1)), 1);
        }

        return $result;
    }

    /**
     * Validate an email address against RFC conventions
     *
     * @param string $email The email address
     *
     * @return bool
     */
    public static function isValidEmailAddress($email): bool
    {
        // the email address can not start with a dot.
        if (substr($email, 0, 1) == '.') {
            return false;
        }

        return true;
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
     *
     * @return string Full URL
     */
    public static function serviceUrl(string $route): string
    {
        $url = \config('app.public_url');

        if (!$url) {
            $url = \config('app.url');
        }

        return rtrim(trim($url, '/') . '/' . ltrim($route, '/'), '/');
    }

    /**
     * Create a configuration/environment data to be passed to
     * the UI
     *
     * @todo For a lack of better place this is put here for now
     *
     * @return array Configuration data
     */
    public static function uiEnv(): array
    {
        $opts = ['app.name', 'app.url', 'app.domain'];
        $env = \app('config')->getMany($opts);

        $countries = include resource_path('countries.php');
        $env['countries'] = $countries ?: [];

        $isAdmin = strpos(request()->getHttpHost(), 'admin.') === 0;
        $env['jsapp'] = $isAdmin ? 'admin.js' : 'user.js';

        $env['paymentProvider'] = \config('services.payment_provider');
        $env['stripePK'] = \config('services.stripe.public_key');

        return $env;
    }
}
