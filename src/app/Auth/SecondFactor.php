<?php

namespace App\Auth;

use App\Sku;
use Illuminate\Support\Facades\Auth;

/**
 * A class to maintain 2-factor authentication
 */
class SecondFactor
{
    protected $user;
    protected $storage;
    protected $drivers = array();


    /**
     * Class constructor
     */
    public function __construct()
    {
    }

    /**
     * Handle login action
     */
    public function login($request): ?array
    {
        $this->user = Auth::guard()->user();

        // get list of configured authentication factors
        $factors = $this->factors();

        // do nothing if no factors configured
        if (empty($factors)) {
            return null;
        }

        // flag session for 2nd factor verification
        $_SESSION['2fa_time']     = time();
        $_SESSION['2fa_nonce']    = bin2hex(openssl_random_pseudo_bytes(32));
        $_SESSION['2fa_factors']  = $factors;
        $_SESSION['2fa_username'] = $this->username;
        $_SESSION['2fa_account']  = $user;
        $_SESSION['2fa_apitoken'] = API\Client::get_user_instance()->get_session_token();

        // define login form
        $nonce = $_SESSION['2fa_nonce'];

        $methods = array_unique(
            array_map(function ($factor) {
                    list($method, $id) = explode(':', $factor);
                    return $method;
                },
            $factors
            )
        );

        $required = count($methods) == 1;

        foreach ($methods as $i => $method) {
            $methods[$i] = array(
                'name'     => "${nonce}-${method}",
                'label'    => \trans("login.$method"),
                'required' => $required,
            );
        }

        return [
            'second-factor' => $methods
        ];
    }

    /**
     * Validate 2-factor authentication code
     */
    public function verify($post)
    {
        if (empty($this->username)
            || empty($_SESSION['2fa_username'])
            || $_SESSION['2fa_username'] != $post['username']
        ) {
            return;
        }

        $time     = $_SESSION['2fa_time'];
        $nonce    = $_SESSION['2fa_nonce'];
        $factors  = (array) $_SESSION['2fa_factors'];
        $expired  = $time < time() - \config('2fa.timeout', 120);
        $verified = false;

        if (!empty($factors) && !empty($nonce) && !$expired) {
            // try to verify each configured factor
            foreach ($factors as $factor) {
                list($method) = explode(':', $factor, 2);

                // verify the submitted code
                $code = strip_tags($_POST["${nonce}-${method}"]);
                if ($code && ($verified = $this->verify_factor_auth($factor, $code))) {
                    // accept first successful method
                    break;
                }
            }
        }

/*
        if (!$verified) {
            \Log::info("2-FACTOR failure for {$this->user->name}");
            $this->output->add_message(T('login.invalid2facode'), 'warning');
            $this->login($_SESSION['2fa_account']);
            return;
        }

        // setup user session
        $user = $_SESSION['2fa_account'];
        // API\Client::get_user_instance()->set_session_token($_SESSION['2fa_apitoken']);

        // clean up
        unset($_SESSION['2fa_time'], $_SESSION['2fa_nonce'], $_SESSION['2fa_factors'],
            $_SESSION['2fa_username'], $_SESSION['2fa_account'], $_SESSION['2fa_apitoken']);

        return $user;
*/
    }

    /**
     * Remove all configured 2FA methods for the current user
     *
     * @return bool True on success, False otherwise
     */
    public function removeFactors(): bool
    {
        if ($this->user && ($storage = $this->get_storage($this->user->email))) {
            return $storage->remove_all_factors();
        }

        return false;
    }

    /**
     * Returns a list of 2nd factor methods configured for the user
     */
    protected function factors(): ?array
    {
        $sku_2fa = Sku::where('title', '2fa')->first();
        $has_2fa = $this->user->entitlements()->where('sku_id', $sku_2fa->id)->first();

        if ($has_2fa) {
            if ($storage = $this->get_storage($this->user->email)) {
                $factors = (array) $storage->enumerate();
                $factors = array_unique($factors);

                return $factors;
            }
        }

        return null;
    }

    /**
     * Helper method to verify the given method/code tuple
     *
     * @param string $factor Factor identifier (<method>:<id>)
     * @param string $code   Authentication code
     *
     * @return boolean
     */
    protected function verify_factor_auth($factor, $code)
    {
        if (strlen($code) && ($driver = $this->get_driver($factor))) {
            $driver->username = $this->user->email;

            try {
                // verify the submitted code
                return $driver->verify($code, $_SESSION['2fa_time']);
            }
            catch (\Exception $e) {
                \Log::error("2-FACTOR failure for {$this->user->email}: " . $e->getMessage());
            }
        }

        return false;
    }

    /**
     * Load driver class for the given authentication factor
     *
     * @param string $factor Factor identifier (<method>:<id>)
     *
     * @return Kolab2FA\Driver\Base
     */
    protected function get_driver($factor)
    {
        list($method) = explode(':', $factor, 2);

        if ($this->drivers[$factor]) {
            return $this->drivers[$factor];
        }

        $config = \config('2fa.' . $method, array());

        // use product name as "issuer"
        if (empty($config['issuer'])) {
            $config['issuer'] = \config('app.name');
        }

        try {
            $driver = \Kolab2FA\Driver\Base::factory($factor, $config);

            // configure driver
            $driver->storage  = $this->get_storage();
            $driver->username = $this->user->email;

            return $driver;
        }
        catch (\Exception $e) {
            \Log::error("2-FACTOR driver failure for {$this->user->email}: " . $e->getMessage());
        }
    }

    /**
     * Getter for a storage instance singleton
     */
    protected function get_storage($for = null)
    {
        if (!isset($this->storage) || (!empty($for) && $this->storage->username !== $for)) {
            $config = \config('2fa', array());

            try {
                $this->storage = new SecondFactorStorage($config);
                $this->storage->set_username($for);
//TODO                $this->storage->set_logger(new \Kolab2FA\Log\RcubeLogger());
            }
            catch (\Exception $e) {
                $this->storage = null;
                \Log::error("2-FACTOR storage failure for {$for}: " . $e->getMessage());
            }
        }

        return $this->storage;
    }
}
