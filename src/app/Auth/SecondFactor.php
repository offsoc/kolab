<?php

namespace App\Auth;

use App\Sku;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Kolab2FA\Storage\Base;

/**
 * A class to maintain 2-factor authentication
 */
class SecondFactor extends Base
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

        parent::__construct();
    }

    /**
     * Validate 2-factor authentication code
     *
     * @param \Illuminate\Http\Request $request The API request.
     *
     * @return \Illuminate\Http\JsonResponse|null
     */
    public function requestHandler($request)
    {
        // get list of configured authentication factors
        $factors = $this->factors();

        // do nothing if no factors configured
        if (empty($factors)) {
            return null;
        }

        if (empty($request->secondfactor) || !is_string($request->secondfactor)) {
            $errors = ['secondfactor' => \trans('validation.2fareq')];
            return response()->json(['status' => 'error', 'errors' => $errors], 422);
        }

        // try to verify each configured factor
        foreach ($factors as $factor) {
            // verify the submitted code
            if (strpos($factor, 'dummy:') === 0) {
                // This is for automated tests
                if ($request->secondfactor === 'dummy') {
                    return null;
                }
            } elseif ($this->verify($factor, $request->secondfactor)) {
                return null;
            }
        }

        $errors = ['secondfactor' => \trans('validation.2fainvalid')];
        return response()->json(['status' => 'error', 'errors' => $errors], 422);
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
    protected function factors(): ?array
    {
        // First check if the user has the 2FA SKU
        $sku_2fa = Sku::where('title', '2fa')->first();

        if (!$sku_2fa) {
            return null;
        }

        $has_2fa = $this->user->entitlements()->where('sku_id', $sku_2fa->id)->first();

        if ($has_2fa) {
            $factors = (array) $this->enumerate();
            $factors = array_unique($factors);

            return $factors;
        }

        return null;
    }

    /**
     * Helper method to verify the given method/code tuple
     *
     * @param string $factor Factor identifier (<method>:<id>)
     * @param string $code   Authentication code
     *
     * @return bool
     */
    protected function verify($factor, $code)
    {
        if ($driver = $this->getDriver($factor)) {
            return $driver->verify($code, time());
        }

        return false;
    }

    /**
     * Load driver class for the given authentication factor
     *
     * @param string $factor Factor identifier (<method>:<id>)
     *
     * @return \Kolab2FA\Driver\Base
     */
    protected function getDriver($factor)
    {
        list($method) = explode(':', $factor, 2);

        $config = \config('2fa.' . $method, []);

        $driver = \Kolab2FA\Driver\Base::factory($factor, $config);

        // configure driver
        $driver->storage  = $this;
        $driver->username = $this->user->email;

        return $driver;
    }

    /**
     * Helper for seeding a Roundcube account with 2FA setup
     * for testing.
     *
     * @param string $email Email address
     */
    public static function seed($email)
    {
        $config = [
            'kolab_2fa_blob' => [
                'totp:8132a46b1f741f88de25f47e' => [
                    'label' => 'Mobile app (TOTP)',
                    'created' => 1584573552,
                    'secret' => 'UAF477LDHZNWVLNA',
                    'active' => true,
                ],
                'dummy:dummy' => [
                    'active' => true,
                ],
            ],
            'kolab_2fa_factors' => [
                'totp:8132a46b1f741f88de25f47e',
                'dummy:dummy',
            ]
        ];

        self::dbh()->table('users')->updateOrInsert(
            ['username' => $email, 'mail_host' => '127.0.0.1'],
            ['preferences' => serialize($config)]
        );
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
        \Log::debug(__METHOD__ . ' ' . $key);

        if (!isset($this->cache[$key])) {
            $factors = $this->getFactors();
            $this->cache[$key] = $factors[$key];
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
    protected function getFactors()
    {
        $prefs = $this->getPrefs();

        return (array) $prefs[$this->key2property('blob')];
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

        $this->dbh()->table('users')
            ->where('username', strtolower($this->user->email))
            ->update(['preferences' => serialize($prefs)]);

        return true;
    }

    /**
     * Init connection to the Roundcube database
     */
    protected static function dbh()
    {
        \Config::set('database.connections.2fa', ['url' => \config('2fa.dsn')]);

        return DB::connection('2fa');
    }
}
