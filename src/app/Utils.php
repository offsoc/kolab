<?php

namespace App;

use App\Rules\UserEmailLocal;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * Small utility functions for App.
 */
class Utils
{
    // Note: Removed '0', 'O', '1', 'I' as problematic with some fonts
    public const CHARS = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';

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
     * Create a configuration/environment data to be passed to
     * the UI
     *
     * @todo Move this to App\Http\Controllers\Controller
     *
     * @return array Configuration data
     */
    public static function uiEnv(): array
    {
        $opts = ['app.name', 'app.url', 'app.domain'];
        $env = \app('config')->getMany($opts);

        $countries = include resource_path('countries.php');

        $env['countries'] = $countries ?: [];
        $env['view'] = 'root';
        $env['jsapp'] = 'user.js';

        $req_domain = preg_replace('/:[0-9]+$/', '', request()->getHttpHost());
        $sys_domain = \config('app.domain');

        switch ($req_domain) {
            case "meet.$sys_domain":
                $env['view'] = 'meet';
                $env['jsapp'] = 'meet.js';
                break;

            case "admin.$sys_domain":
                $env['jsapp'] = 'admin.js';
                break;
        }

        return $env;
    }

    /**
     * Email address (login or alias) validation
     *
     * @param string    $email    Email address
     * @param \App\User $user     The account owner
     * @param bool      $is_alias The email is an alias
     *
     * @return string Error message on validation error
     */
    public static function validateEmail(
        string $email,
        \App\User $user,
        bool $is_alias = false
    ): ?string {
        $attribute = $is_alias ? 'alias' : 'email';

        if (strpos($email, '@') === false) {
            return \trans('validation.entryinvalid', ['attribute' => $attribute]);
        }

        list($login, $domain) = explode('@', $email);

        // Check if domain exists
        $domain = Domain::where('namespace', Str::lower($domain))->first();

        if (empty($domain)) {
            return \trans('validation.domaininvalid');
        }

        // Validate login part alone
        $v = Validator::make(
            [$attribute => $login],
            [$attribute => ['required', new UserEmailLocal(!$domain->isPublic())]]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()[$attribute][0];
        }

        // Check if it is one of domains available to the user
        // TODO: We should have a helper that returns "flat" array with domain names
        //       I guess we could use pluck() somehow
        $domains = array_map(
            function ($domain) {
                return $domain->namespace;
            },
            $user->domains()
        );

        if (!in_array($domain->namespace, $domains)) {
            return \trans('validation.entryexists', ['attribute' => 'domain']);
        }

        // Check if user with specified address already exists
        if (User::findByEmail($email)) {
            return \trans('validation.entryexists', ['attribute' => $attribute]);
        }

        return null;
    }
}
