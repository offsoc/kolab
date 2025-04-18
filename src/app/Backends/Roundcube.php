<?php

namespace App\Backends;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class Roundcube
{
    private const FILESTORE_TABLE = 'filestore';
    private const USERS_TABLE = 'users';
    private const IDENTITIES_TABLE = 'identities';

    /** @var array List of GnuPG files to store */
    private static $enigma_files = ['pubring.gpg', 'secring.gpg', 'pubring.kbx'];


    /**
     * Return connection to the Roundcube database
     *
     * @return \Illuminate\Database\ConnectionInterface
     */
    public static function dbh()
    {
        if (!\config('database.connections.roundcube')) {
            \Log::warning("Roundcube database not configured");

            return DB::connection(\config('database.default'));
        }

        return DB::connection('roundcube');
    }

    /**
     * Remove all files from the Enigma filestore.
     *
     * @param string $email User email address
     */
    public static function enigmaCleanup(string $email): void
    {
        self::dbh()->table(self::FILESTORE_TABLE)
            ->where('user_id', self::userId($email))
            ->where('context', 'enigma')
            ->delete();
    }

    /**
     * List all files from the Enigma filestore.
     *
     * @param string $email User email address
     *
     * @return array List of Enigma filestore records
     */
    public static function enigmaList(string $email): array
    {
        return self::dbh()->table(self::FILESTORE_TABLE)
            ->where('user_id', self::userId($email))
            ->where('context', 'enigma')
            ->orderBy('filename')
            ->get()
            ->all();
    }

    /**
     * Synchronize Enigma filestore from/to specified directory
     *
     * @param string $email   User email address
     * @param string $homedir Directory location
     */
    public static function enigmaSync(string $email, string $homedir): void
    {
        $db = self::dbh();
        $debug = \config('app.debug');
        $user_id = self::userId($email);
        $root = \config('filesystems.disks.pgp.root');
        $fs = Storage::disk('pgp');
        $files = [];

        $result = $db->table(self::FILESTORE_TABLE)->select('file_id', 'filename', 'mtime')
            ->where('user_id', $user_id)
            ->where('context', 'enigma')
            ->get();

        foreach ($result as $record) {
            $file = $homedir . '/' . $record->filename;
            $mtime = $fs->exists($file) ? $fs->lastModified($file) : 0;
            $files[] = $record->filename;

            if ($mtime < $record->mtime) {
                $file_id = $record->file_id;
                $record = $db->table(self::FILESTORE_TABLE)->select('file_id', 'data', 'mtime')
                    ->where('file_id', $file_id)
                    ->first();

                $data = $record ? base64_decode($record->data) : false;

                if ($data === false) {
                    \Log::error("Failed to sync $file ({$file_id}). Decode error.");
                    continue;
                }

                if ($fs->put($file, $data, true)) {
                    // Note: Laravel Filesystem API does not provide touch method
                    touch("$root/$file", $record->mtime);

                    if ($debug) {
                        \Log::debug("[SYNC] Fetched file: $file");
                    }
                }
            }
        }

        // Remove files not in database
        foreach (array_diff(self::enigmaFilesList($homedir), $files) as $file) {
            $file = $homedir . '/' . $file;

            if ($fs->delete($file)) {
                if ($debug) {
                    \Log::debug("[SYNC] Removed file: $file");
                }
            }
        }

        // No records found, do initial sync if already have the keyring
        if (empty($file)) {
            self::enigmaSave($email, $homedir);
        }
    }

    /**
     * Save the keys database
     *
     * @param string $email    User email address
     * @param string $homedir  Directory location
     * @param bool   $is_empty Set to Tre if it is a initial save
     */
    public static function enigmaSave(string $email, string $homedir, bool $is_empty = false): void
    {
        $db = self::dbh();
        $debug = \config('app.debug');
        $user_id = self::userId($email);
        $fs = Storage::disk('pgp');
        $records = [];

        if (!$is_empty) {
            $records = $db->table(self::FILESTORE_TABLE)->select('file_id', 'filename', 'mtime')
                ->where('user_id', $user_id)
                ->where('context', 'enigma')
                ->get()
                ->keyBy('filename')
                ->all();
        }

        foreach (self::enigmaFilesList($homedir) as $filename) {
            $file = $homedir . '/' . $filename;
            $mtime = $fs->exists($file) ? $fs->lastModified($file) : 0;

            $existing = !empty($records[$filename]) ? $records[$filename] : null;
            unset($records[$filename]);

            if ($mtime && (empty($existing) || $mtime > $existing->mtime)) {
                $data = base64_encode($fs->get($file));
/*
                if (empty($maxsize)) {
                    $maxsize = min($db->get_variable('max_allowed_packet', 1048500), 4*1024*1024) - 2000;
                }

                if (strlen($data) > $maxsize) {
                    \Log::error("Failed to save $file. Size exceeds max_allowed_packet.");
                    continue;
                }
*/
                $result = $db->table(self::FILESTORE_TABLE)->updateOrInsert(
                    ['user_id' => $user_id, 'context' => 'enigma', 'filename' => $filename],
                    ['mtime' => $mtime, 'data' => $data]
                );

                if ($debug) {
                    \Log::debug("[SYNC] Pushed file: $file");
                }
            }
        }

        // Delete removed files from database
        foreach (array_keys($records) as $filename) {
            $file = $homedir . '/' . $filename;
            $result = $db->table(self::FILESTORE_TABLE)
                ->where('user_id', $user_id)
                ->where('context', 'enigma')
                ->where('filename', $filename)
                ->delete();

            if ($debug) {
                \Log::debug("[SYNC] Removed file: $file");
            }
        }
    }

    /**
     * Delete a Roundcube user.
     *
     * @param string $email User email address
     */
    public static function deleteUser(string $email): void
    {
        $db = self::dbh();

        $db->table(self::USERS_TABLE)->where('username', \strtolower($email))->delete();
    }

    /**
     * Check if we can connect to the Roundcube
     *
     * @return bool True on success
     */
    public static function healthcheck(): bool
    {
        // TODO: Make some query? Access the webmail URL?
        self::dbh();
        return true;
    }

    /**
     * Find the Roundcube user identifier for the specified user.
     *
     * @param string $email  User email address
     * @param bool   $create Make sure the user record exists
     *
     * @returns ?int Roundcube user identifier
     */
    public static function userId(string $email, bool $create = true): ?int
    {
        $db = self::dbh();

        $user = $db->table(self::USERS_TABLE)->select('user_id')
            ->where('username', \strtolower($email))
            ->first();

        // Create a user record, without it we can't use the Roundcube storage
        if (empty($user)) {
            if (!$create) {
                return null;
            }

            $uri = \parse_url(\config('services.imap.uri'));

            $user_id = (int) $db->table(self::USERS_TABLE)->insertGetId(
                [
                    'username' => $email,
                    'mail_host' => $uri['host'],
                    'created' => now()->toDateTimeString(),
                ],
                'user_id'
            );

            $username = \App\User::where('email', $email)->first()->name();

            $db->table(self::IDENTITIES_TABLE)->insert([
                    'user_id' => $user_id,
                    'email' => $email,
                    'name' => $username,
                    'changed' => now()->toDateTimeString(),
                    'standard' => 1,
            ]);

            return $user_id;
        }

        return (int) $user->user_id;
    }

    /**
     * Returns list of Enigma user homedir files to backup/sync
     */
    private static function enigmaFilesList(string $homedir)
    {
        $files = [];
        $fs = Storage::disk('pgp');

        foreach (self::$enigma_files as $file) {
            if ($fs->exists($homedir . '/' . $file)) {
                $files[] = $file;
            }
        }

        foreach ($fs->files($homedir . '/private-keys-v1.d') as $file) {
            if (preg_match('/\.key$/', $file)) {
                $files[] = substr($file, strlen($homedir . '/'));
            }
        }

        return $files;
    }
}
