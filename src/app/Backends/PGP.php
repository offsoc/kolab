<?php

namespace App\Backends;

use App\User;
use Illuminate\Support\Facades\Storage;

class PGP
{
    /** @var \Crypt_GPG GnuPG engine instance */
    private static $gpg;

    /** @var array Crypt_GPG configuration */
    private static $config = [];


    /**
     * Remove all files from the user homedir
     *
     * @param \App\User $user User object
     * @param bool      $del  Delete also the homedir itself
     */
    public static function homedirCleanup(User $user, bool $del = false): void
    {
        $homedir = self::setHomedir($user);

        // Remove all files from the filesystem (and optionally the dir itself)
        if ($del) {
            Storage::disk('pgp')->deleteDirectory($homedir);
        } else {
            Storage::disk('pgp')->delete(Storage::disk('pgp')->files($homedir));

            foreach (Storage::disk('pgp')->files($homedir) as $subdir) {
                Storage::disk('pgp')->deleteDirectory($subdir);
            }
        }

        // Remove all files from the Enigma database
        // Note: This will cause existing files in the Roundcube filesystem
        // to be removed, but only if the user used the Enigma functionality
        Roundcube::enigmaCleanup($user->email);
    }

    /**
     * Generate a keypair.
     * This will also initialize the user GPG homedir content.
     *
     * @param \App\User $user  User object
     * @param string    $email Email address to use for the key
     *
     * @throws \Exception
     */
    public static function keypairCreate(User $user, string $email): void
    {
        self::initGPG($user, true);

        if ($user->email === $email) {
            // Make sure the homedir is empty for a new user
            self::homedirCleanup($user);
        }

        $keygen = new \Crypt_GPG_KeyGenerator(self::$config);

        $key = $keygen
            // ->setPassphrase()
            // ->setExpirationDate(0)
            ->setKeyParams(\Crypt_GPG_SubKey::ALGORITHM_RSA, \config('pgp.length'))
            ->setSubKeyParams(\Crypt_GPG_SubKey::ALGORITHM_RSA, \config('pgp.length'))
            ->generateKey(null, $email);

        // Store the keypair in Roundcube Enigma storage
        self::dbSave(true);

        // Get the ASCII armored data of the public key
        $armor = self::$gpg->exportPublicKey((string) $key, true);

        // Register the public key in DNS
        self::keyRegister($email, $armor);

        // FIXME: Should we remove the files from the worker filesystem?
        //        They are still in database and Roundcube hosts' filesystem
    }

    /**
     * List (public and private) keys from a user keyring.
     *
     * @param \App\User $user User object
     *
     * @returns \Crypt_GPG_Key[] List of keys
     * @throws \Exception
     */
    public static function listKeys(User $user): array
    {
        self::initGPG($user);

        return self::$gpg->getKeys('');
    }

    /**
     * Debug logging callback
     */
    public static function logDebug($msg): void
    {
        \Log::debug("[GPG] $msg");
    }

    /**
     * Register the key in the WOAT DNS system
     *
     * @param string $email Email address
     * @param string $key   The ASCII-armored key content
     */
    public static function keyRegister(string $email, string $key)
    {
        // TODO
    }

    /**
     * Remove the key from the WOAT DNS system
     *
     * @param string $email Email address
     */
    public static function keyUnregister(string $email)
    {
        // TODO
    }

    /**
     * Prepare Crypt_GPG configuration
     */
    private static function initConfig(User $user, $nosync = false): void
    {
        if (!empty(self::$config) && self::$config['email'] == $user->email) {
            return;
        }

        $debug   = \config('app.debug');
        $binary  = \config('pgp.binary');
        $agent   = \config('pgp.agent');
        $gpgconf = \config('pgp.gpgconf');

        $dir = self::setHomedir($user);
        $options = [
            'email' => $user->email, // this one is not a Crypt_GPG option
            'dir' => $dir, // this one is not a Crypt_GPG option
            'homedir' => \config('filesystems.disks.pgp.root') . '/' . $dir,
            'debug' => $debug ? 'App\Backends\PGP::logDebug' : null,
        ];

        if ($binary) {
            $options['binary'] = $binary;
        }

        if ($agent) {
            $options['agent'] = $agent;
        }

        if ($gpgconf) {
            $options['gpgconf'] = $gpgconf;
        }

        self::$config = $options;

        // Sync the homedir directory content with the Enigma storage
        if (!$nosync) {
            self::dbSync();
        }
    }

    /**
     * Initialize Crypt_GPG
     */
    private static function initGPG(User $user, $nosync = false): void
    {
        self::initConfig($user, $nosync);

        self::$gpg = new \Crypt_GPG(self::$config);
    }

    /**
     * Prepare a homedir for the user
     */
    private static function setHomedir(User $user): string
    {
        // Create a subfolder using two first digits of the user ID
        $dir = sprintf('%02d', substr((string) $user->id, 0, 2)) . '/' . $user->email;

        Storage::disk('pgp')->makeDirectory($dir);

        return $dir;
    }

    /**
     * Synchronize keys database of a user
     */
    private static function dbSync(): void
    {
        Roundcube::enigmaSync(self::$config['email'], self::$config['dir']);
    }

    /**
     * Save the keys database
     */
    private static function dbSave($is_empty = false): void
    {
        Roundcube::enigmaSave(self::$config['email'], self::$config['dir'], $is_empty);
    }
}
