<?php

namespace App\Auth;

use Kolab2FA\Storage\Base;
use Illuminate\Support\Facades\DB;

class SecondFactorStorage extends Base
{
    // sefault config
    protected $config = array(
        'keymap' => array(),
    );

    private $cache = array();


    /**
     * List/set methods activated for this user
     */
    public function enumerate()
    {
        if ($factors = $this->get_factors()) {
            return array_keys(array_filter($factors, function ($prop) {
                return !empty($prop['active']);
            }));
        }

        return array();
    }

    /**
     * Read data for the given key
     */
    public function read($key)
    {
        \Log::debug(__METHOD__ . ' ' . $key);

        if (!isset($this->cache[$key])) {
            $factors = $this->get_factors();
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
     * Set username to store data for
     */
    public function set_username($username)
    {
        parent::set_username($username);

        // reset cached values
        $this->cache = array();
    }

    public function remove_all_factors()
    {
        $this->cache = array();

        $prefs = array();
        $prefs[$this->key2property('blob')]    = null;
        $prefs[$this->key2property('factors')] = null;

        return $this->username ? $this->save_prefs($prefs) : false;
    }

    /**
     *
     */
    private function get_factors()
    {
        $prefs = $this->get_prefs();

        return (array) $prefs[$this->key2property('blob')];
    }

    /**
     *
     */
    private function key2property($key)
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
    private function get_prefs()
    {
        $dbh = DB::connection('2fa');
        $user = $dbh->table('users')
            ->select('preferences')
            ->where('username', strtolower($this->username))
            ->first();

        return $user ? (array) unserialize($user->preferences) : null;
    }

    /**
     * Saves user preferences in Roundcube users table.
     * This will merge into old preferences
     */
    private function save_prefs($prefs)
    {
        $old_prefs = $this->get_prefs();

        if (!is_array($old_prefs)) {
            return false;
        }

        $prefs = array_merge($old_prefs, $prefs);

        $dbh = DB::connection('2fa');
        $dbh->table('users')
            ->where('username', strtolower($this->username))
            ->update(['preferences' => serialize($prefs)]);

        return true;
    }
}
