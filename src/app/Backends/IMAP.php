<?php

namespace App\Backends;

use App\Domain;
use App\User;

class IMAP
{
    /**
     * Check if an account is set up
     *
     * @param string $username User login (email address)
     *
     * @return bool True if an account exists and is set up, False otherwise
     */
    public static function verifyAccount(string $username): bool
    {
        $config = self::getConfig();
        $imap = self::initIMAP($config, $username);

        $folders = $imap->listSubscribed('', '*');

        if (!is_array($folders)) {
            throw new \Exception("Failed to get IMAP folders");
        }

        $imap->closeConnection();

        return count($folders) > 1;
    }

    /**
     * Initialize connection to IMAP
     */
    private static function initIMAP(array $config, string $login_as = null)
    {
        $imap = new \rcube_imap_generic();

        if (\config('app.debug')) {
            $imap->setDebug(true, 'App\Backends\IMAP::logDebug');
        }

        if ($login_as) {
            $config['options']['auth_cid'] = $config['user'];
            $config['options']['auth_pw'] = $config['password'];
            $config['options']['auth_type'] = 'PLAIN';
            $config['user'] = $login_as;
        }

        $imap->connect($config['host'], $config['user'], $config['password'], $config['options']);

        if (!$imap->connected()) {
            $message = sprintf("Login failed for %s against %s. %s", $config['user'], $config['host'], $imap->error);

            \Log::error($message);

            throw new \Exception("Connection to IMAP failed");
        }

        return $imap;
    }

    /**
     * Get LDAP configuration for specified access level
     */
    private static function getConfig()
    {
        $uri = \parse_url(\config('imap.uri'));
        $default_port = 143;
        $ssl_mode = null;

        if (isset($uri['scheme'])) {
            if (preg_match('/^(ssl|imaps)/', $uri['scheme'])) {
                $default_port = 993;
                $ssl_mode = 'ssl';
            } elseif ($uri['scheme'] === 'tls') {
                $ssl_mode = 'tls';
            }
        }

        $config = [
            'host' => $uri['host'],
            'user' => \config('imap.admin_login'),
            'password' => \config('imap.admin_password'),
            'options' => [
                'port' => !empty($uri['port']) ? $uri['port'] : $default_port,
                'ssl_mode' => $ssl_mode,
                'socket_options' => [
                    'ssl' => [
                        'verify_peer' => \config('imap.verify_peer'),
                        'verify_peer_name' => \config('imap.verify_name'),
                        'cafile' => \config('imap.cafile'),
                    ],
                ],
            ],
        ];

        return $config;
    }

    /**
     * Debug logging callback
     */
    public static function logDebug($conn, $msg): void
    {
        $msg = '[IMAP] ' . $msg;

        \Log::debug($msg);
    }
}
