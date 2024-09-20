<?php

namespace App\Auth;

use Illuminate\Support\Facades\Auth;
use Kolab2FA\Driver\Base as DriverBase;
use Kolab2FA\Storage\Base as StorageBase;

/**
 * A class to maintain 2-factor authentication
 */
class SecondFactor extends StorageBase
{
    protected $user;
    protected $cache = [];
    protected $config = [
        'keymap' => [],
    ];


    /**
     * Class constructor
     *
     * @param \App\User $user User object
     */
    public function __construct($user)
    {
        $this->user = $user;
        $this->username = $this->user->email;

        parent::__construct();
    }

    /**
     * Validate 2-factor authentication code
     *
     * @param string $secondfactor The 2-factor authentication code.
     *
     * @throws \Exception on validation failure
     */
    public function validate($secondfactor): void
    {
        // get list of configured authentication factors
        $factors = $this->factors();

        // do nothing if no factors configured
        if (empty($factors)) {
            return;
        }

        if (empty($secondfactor) || !is_string($secondfactor)) {
            throw new \Exception(\trans('validation.2fareq'));
        }

        // try to verify each configured factor
        foreach ($factors as $factor) {
            // verify the submitted code
            if ($this->verify($factor, $secondfactor)) {
                return;
            }
        }

        throw new \Exception(\trans('validation.2fainvalid'));
    }

    /**
     * Validate 2-factor authentication code
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function requestHandler(\Illuminate\Http\Request $request)
    {
        try {
            $this->validate($request->secondfactor);
        } catch (\Exception $e) {
            $errors = ['secondfactor' => $e->getMessage()];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }
        return null;
    }

    /**
     * Remove all configured 2FA methods for the current user
     *
     * @return bool True on success, False otherwise
     */
    public function removeFactors(): bool
    {
        $this->cache = [];

        $prefs = [];
        $prefs[$this->key2property('blob')]    = null;
        $prefs[$this->key2property('factors')] = null;

        return $this->savePrefs($prefs);
    }

    /**
     * Returns a list of 2nd factor methods configured for the user
     */
    public function factors(): array
    {
        // First check if the user has the 2FA SKU
        if ($this->user->hasSku('2fa')) {
            $factors = (array) $this->enumerate();
            $factors = array_unique($factors);

            return $factors;
        }

        return [];
    }

    /**
     * Helper method to verify the given method/code tuple
     *
     * @param string $factor Factor identifier (<method>:<id>)
     * @param string $code   Authentication code
     *
     * @return bool True on successful validation
     */
    protected function verify($factor, $code): bool
    {
        $driver = $this->getDriver($factor);
        return $driver->verify($code, time());
    }

    /**
     * Load driver class for the given authentication factor
     *
     * @param string $factor Factor identifier (<method>:<id>)
     *
     * @return DriverBase
     */
    protected function getDriver(string $factor)
    {
        list($method) = explode(':', $factor, 2);

        $config = \config('2fa.' . $method, []);

        return DriverBase::factory($this, $factor, $config);
    }

    /**
     * Helper for seeding a Roundcube account with 2FA setup
     * for testing.
     *
     * @param string $email Email address
     */
    public static function seed(string $email): void
    {
        $config = [
            'kolab_2fa_blob' => [
                'totp:8132a46b1f741f88de25f47e' => [
                    'label' => 'Mobile app (TOTP)',
                    'created' => 1584573552,
                    'secret' => 'UAF477LDHZNWVLNA',
                    'digest' => 'sha1',
                    'active' => true,
                ],
                // 'dummy:dummy' => [
                //    'active' => true,
                // ],
            ],
            'kolab_2fa_factors' => [
                'totp:8132a46b1f741f88de25f47e',
                // 'dummy:dummy',
            ]
        ];

        self::dbh()->table('users')->updateOrInsert(
            ['username' => $email, 'mail_host' => '127.0.0.1'],
            ['preferences' => serialize($config)]
        );
    }

    /**
     * Helper for generating current TOTP code for a test user
     *
     * @param string $email Email address
     *
     * @return string Generated code
     */
    public static function code(string $email): string
    {
        $sf = new self(\App\User::where('email', $email)->first());

        /** @var \Kolab2FA\Driver\TOTP $driver */
        $driver = $sf->getDriver('totp:8132a46b1f741f88de25f47e');

        return (string) $driver->get_code();
    }


    //******************************************************
    //      Methods required by Kolab2FA Storage Base
    //******************************************************

    /**
     * Initialize the storage driver with the given config options
     */
    public function init(array $config)
    {
        $this->config = array_merge($this->config, $config);
    }

    /**
     * List methods activated for this user
     */
    public function enumerate()
    {
        if ($factors = $this->getFactors()) {
            return array_keys(array_filter($factors, function ($prop) {
                return !empty($prop['active']);
            }));
        }

        return [];
    }

    /**
     * Read data for the given key
     */
    public function read($key)
    {
        if (!isset($this->cache[$key])) {
            $factors = $this->getFactors();
            $this->cache[$key] = isset($factors[$key]) ? $factors[$key] : null;
        }

        return $this->cache[$key];
    }

    /**
     * Save data for the given key
     */
    public function write($key, $value)
    {
        \Log::debug(__METHOD__ . ' ' . @json_encode($value));

        // TODO: Not implemented
        return false;
    }

    /**
     * Remove the data stored for the given key
     */
    public function remove($key)
    {
        return $this->write($key, null);
    }

    /**
     *
     */
    protected function getFactors(): array
    {
        $prefs = $this->getPrefs();
        $key = $this->key2property('blob');

        return isset($prefs[$key]) ? (array) $prefs[$key] : [];
    }

    /**
     *
     */
    protected function key2property($key)
    {
        // map key to configured property name
        if (is_array($this->config['keymap']) && isset($this->config['keymap'][$key])) {
            return $this->config['keymap'][$key];
        }

        // default
        return 'kolab_2fa_' . $key;
    }

    /**
     * Gets user preferences from Roundcube users table
     */
    protected function getPrefs()
    {
        $user = $this->dbh()->table('users')
            ->select('preferences')
            ->where('username', strtolower($this->user->email))
            ->first();

        return $user ? (array) unserialize($user->preferences) : null;
    }

    /**
     * Saves user preferences in Roundcube users table.
     * This will merge into old preferences
     */
    protected function savePrefs($prefs)
    {
        $old_prefs = $this->getPrefs();

        if (!is_array($old_prefs)) {
            return false;
        }

        $prefs = array_merge($old_prefs, $prefs);
        $prefs = array_filter($prefs, fn($v) => !is_null($v));

        $this->dbh()->table('users')
            ->where('username', strtolower($this->user->email))
            ->update(['preferences' => serialize($prefs)]);

        return true;
    }

    /**
     * Init connection to the Roundcube database
     */
    public static function dbh()
    {
        return \App\Backends\Roundcube::dbh();
    }
}
