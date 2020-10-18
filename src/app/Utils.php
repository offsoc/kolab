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
     * Count the number of lines in a file.
     *
     * Useful for progress bars.
     *
     * @param string $file The filepath to count the lines of.
     *
     * @return int
     */
    public static function countLines($file)
    {
        $fh = fopen($file, 'rb');
        $numLines = 0;

        while (!feof($fh)) {
            $numLines += substr_count(fread($fh, 8192), "\n");
        }

        fclose($fh);

        return $numLines;
    }

    /**
     * Return the country ISO code for an IP address.
     *
     * @return string
     */
    public static function countryForIP($ip)
    {
        if (strpos(':', $ip) === false) {
            $query = "
                SELECT country FROM ip4nets
                WHERE INET_ATON(net_number) <= INET_ATON(?)
                AND INET_ATON(net_broadcast) >= INET_ATON(?)
                ORDER BY INET_ATON(net_number), net_mask DESC LIMIT 1
            ";
        } else {
            $query =  "
                SELECT id FROM ip6nets
                WHERE INET6_ATON(net_number) <= INET6_ATON(?)
                AND INET6_ATON(net_broadcast) >= INET6_ATON(?)
                ORDER BY INET6_ATON(net_number), net_mask DESC LIMIT 1
            ";
        }

        $nets = \Illuminate\Support\Facades\DB::select($query, [$ip, $ip]);

        if (sizeof($nets) > 0) {
            return $nets[0]->country;
        }

        return 'CH';
    }

    /**
     * Return the country ISO code for the current request.
     */
    public static function countryForRequest()
    {
        $request = \request();
        $ip = $request->ip();

        return self::countryForIP($ip);
    }

    /**
     * Shortcut to creating a progress bar of a particular format with a particular message.
     *
     * @param \Illuminate\Console\OutputStyle $output  Console output object
     * @param int                             $count   Number of progress steps
     * @param string                          $message The description
     *
     * @return \Symfony\Component\Console\Helper\ProgressBar
     */
    public static function createProgressBar($output, $count, $message = null)
    {
        $bar = $output->createProgressBar($count);

        $bar->setFormat(
            '%current:7s%/%max:7s% [%bar%] %percent:3s%% %elapsed:7s%/%estimated:-7s% %message% '
        );

        if ($message) {
            $bar->setMessage($message . " ...");
        }

        $bar->start();

        return $bar;
    }

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
     * Download a file from the interwebz and store it locally.
     *
     * @param string $source The source location
     * @param string $target The target location
     * @param bool $force    Force the download (and overwrite target)
     *
     * @return void
     */
    public static function downloadFile($source, $target, $force = false)
    {
        if (is_file($target) && !$force) {
            return;
        }

        \Log::info("Retrieving {$source}");

        $fp = fopen($target, 'w');

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $source);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($curl, CURLOPT_FILE, $fp);
        curl_exec($curl);

        if (curl_errno($curl)) {
            \Log::error("Request error on {$source}: " . curl_error($curl));

            curl_close($curl);
            fclose($fp);

            unlink($target);
            return;
        }

        curl_close($curl);
        fclose($fp);
    }

    /**
     * Calculate the broadcast address provided a net number and a prefix.
     *
     * @param string $net A valid IPv6 network number.
     * @param int $prefix The network prefix.
     *
     * @return string
     */
    public static function ip6Broadcast($net, $prefix)
    {
        $netHex = bin2hex(inet_pton($net));

        // Overwriting first address string to make sure notation is optimal
        $net = inet_ntop(hex2bin($netHex));

        // Calculate the number of 'flexible' bits
        $flexbits = 128 - $prefix;

        // Build the hexadecimal string of the last address
        $lastAddrHex = $netHex;

        // We start at the end of the string (which is always 32 characters long)
        $pos = 31;
        while ($flexbits > 0) {
            // Get the character at this position
            $orig = substr($lastAddrHex, $pos, 1);

            // Convert it to an integer
            $origval = hexdec($orig);

            // OR it with (2^flexbits)-1, with flexbits limited to 4 at a time
            $newval = $origval | (pow(2, min(4, $flexbits)) - 1);

            // Convert it back to a hexadecimal character
            $new = dechex($newval);

            // And put that character back in the string
            $lastAddrHex = substr_replace($lastAddrHex, $new, $pos, 1);

            // We processed one nibble, move to previous position
            $flexbits -= 4;
            $pos -= 1;
        }

        // Convert the hexadecimal string to a binary string
        # Using pack() here
        # Newer PHP version can use hex2bin()
        $lastaddrbin = pack('H*', $lastAddrHex);

        // And create an IPv6 address from the binary string
        $lastaddrstr = inet_ntop($lastaddrbin);

        return $lastaddrstr;
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
