<?php

namespace App\DataMigrator;

use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\ExporterInterface;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Interface\ItemSet;

/**
 * Data migration from IMAP
 */
class IMAP implements ExporterInterface, ImporterInterface
{
    /** @const int Max number of items to migrate in one go */
    protected const CHUNK_SIZE = 100;

    /** @var \rcube_imap_generic Imap backend */
    protected $imap;

    /** @var Account Account to operate on */
    protected $account;

    /** @var Engine Data migrator engine */
    protected $engine;


    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine)
    {
        $this->account = $account;
        $this->engine = $engine;

        // TODO: Move this to self::authenticate()?
        $config = self::getConfig($account->username, $account->password, $account->uri);
        $this->imap = self::initIMAP($config);
    }

    /**
     * Authenticate
     */
    public function authenticate(): void
    {
    }

    /**
     * Create a folder.
     *
     * @param Folder $folder Folder data
     *
     * @throws \Exception on error
     */
    public function createFolder(Folder $folder): void
    {
        if ($folder->type != 'mail') {
            throw new \Exception("IMAP does not support folder of type {$folder->type}");
        }

        if ($folder->fullname == 'INBOX') {
            // INBOX always exists
            return;
        }

        if (!$this->imap->createFolder($folder->fullname)) {
            \Log::warning("Failed to create the folder: {$this->imap->error}");

            if (str_contains($this->imap->error, "Mailbox already exists")) {
                // Not an error
            } else {
                throw new \Exception("Failed to create an IMAP folder {$folder->fullname}");
            }
        }
    }

    /**
     * Create an item in a folder.
     *
     * @param Item $item Item to import
     *
     * @throws \Exception
     */
    public function createItem(Item $item): void
    {
        $mailbox = $item->folder->fullname;

        // TODO: When updating an email we have to just update flags

        if ($item->filename) {
            $result = $this->imap->appendFromFile(
                $mailbox, $item->filename, null, $item->data['flags'], $item->data['internaldate'], true
            );

            if ($result === false) {
                throw new \Exception("Failed to append IMAP message into {$mailbox}");
            }
        }
    }

    /**
     * Fetching an item
     */
    public function fetchItem(Item $item): void
    {
        [$uid, $messageId] = explode(':', $item->id, 2);

        $mailbox = $item->folder->fullname;

        // Get message flags
        $header = $this->imap->fetchHeader($mailbox, (int) $uid, true, false, ['FLAGS']);

        if ($header === false) {
            throw new \Exception("Failed to get IMAP message headers for {$mailbox}/{$uid}");
        }

        // Remove flags that we can't append (e.g. RECENT)
        $flags = $this->filterImapFlags(array_keys($header->flags));

        // TODO: If message already exists in the destination account we should update flags
        // and be done with it. On the other hand for Drafts it's not unusual to get completely
        // different body for the same Message-ID. Same can happen not only in Drafts, I suppose.

        // Save the message content to a file
        $location = $item->folder->location;

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        // TODO: What if parent folder not yet exists?
        $location .= '/' . $uid . '.eml';

        // TODO: We should consider streaming the message, it should be possible
        // with append() and handlePartBody(), but I don't know if anyone tried that.

        $fp = fopen($location, 'w');

        if (!$fp) {
            throw new \Exception("Failed to write to {$location}");
        }

        $result = $this->imap->handlePartBody($mailbox, $uid, true, '', null, null, $fp);

        if ($result === false) {
            fclose($fp);
            throw new \Exception("Failed to fetch IMAP message for {$mailbox}/{$uid}");
        }

        $item->filename = $location;
        $item->data = [
            'flags' => $flags,
            'internaldate' => $header->internaldate,
        ];

        fclose($fp);
    }

    /**
     * Fetch a list of folder items
     */
    public function fetchItemList(Folder $folder, $callback, ImporterInterface $importer): void
    {
        // Get existing messages' headers from the destination mailbox
        $existing = $importer->getItems($folder);

        $mailbox = $folder->fullname;

        // TODO: We should probably first use SEARCH/SORT to skip messages marked as \Deleted
        // TODO: fetchHeaders() fetches too many headers, we should slim-down, here we need
        // only UID FLAGS INTERNALDATE BODY.PEEK[HEADER.FIELDS (DATE FROM MESSAGE-ID)]
        $messages = $this->imap->fetchHeaders($mailbox, '1:*', true, false, ['Message-Id']);

        if ($messages === false) {
            throw new \Exception("Failed to get all IMAP message headers for {$mailbox}");
        }

        if (empty($messages)) {
            \Log::debug("Nothing to migrate for {$mailbox}");
            return;
        }

        $set = new ItemSet();

        foreach ($messages as $message) {
            // TODO: If Message-Id header does not exist create it based on internaldate/From/Date

            // Skip message that exists and did not change
            $exists = false;
            if (isset($existing[$message->messageID])) {
                // TODO: Compare flags (compare message size, internaldate?)
                continue;
            }

            $set->items[] = Item::fromArray([
                'id' => $message->uid . ':' . $message->messageID,
                'folder' => $folder,
                'existing' => $exists,
            ]);

            if (count($set->items) == self::CHUNK_SIZE) {
                $callback($set);
                $set = new ItemSet();
            }
        }

        if (count($set->items)) {
            $callback($set);
        }

        // TODO: Delete messages that do not exist anymore?
    }

    /**
     * Get folders hierarchy
     */
    public function getFolders($types = []): array
    {
        $folders = $this->imap->listMailboxes('', '');

        if ($folders === false) {
            throw new \Exception("Failed to get list of IMAP folders");
        }

        $result = [];

        foreach ($folders as $folder) {
            if ($this->shouldSkip($folder)) {
                \Log::debug("Skipping folder {$folder}.");
                continue;
            }

            $result[] = Folder::fromArray([
                'fullname' => $folder,
                'type' => 'mail'
            ]);
        }

        return $result;
    }

    /**
     * Get a list of folder items, limited to their essential propeties
     * used in incremental migration to skip unchanged items.
     */
    public function getItems(Folder $folder): array
    {
        $mailbox = $folder->fullname;

        // TODO: We should probably first use SEARCH/SORT to skip messages marked as \Deleted
        // TODO: fetchHeaders() fetches too many headers, we should slim-down, here we need
        // only UID FLAGS INTERNALDATE BODY.PEEK[HEADER.FIELDS (DATE FROM MESSAGE-ID)]
        $messages = $this->imap->fetchHeaders($mailbox, '1:*', true, false, ['Message-Id']);

        if ($messages === false) {
            throw new \Exception("Failed to get IMAP message headers in {$mailbox}");
        }

        $result = [];

        foreach ($messages as $message) {
            // Remove flags that we can't append (e.g. RECENT)
            $flags = $this->filterImapFlags(array_keys($message->flags));

            // TODO: Generate message ID if the header does not exist
            $result[$message->messageID] = [
                'uid' => $message->uid,
                'flags' => $flags,
            ];
        }

        return $result;
    }

    /**
     * Initialize IMAP connection and authenticate the user
     */
    private static function initIMAP(array $config, string $login_as = null): \rcube_imap_generic
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
     * Get IMAP configuration
     */
    private static function getConfig($user, $password, $uri): array
    {
        $uri = \parse_url($uri);
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
            'user' => $user,
            'password' => $password,
            'options' => [
                'port' => !empty($uri['port']) ? $uri['port'] : $default_port,
                'ssl_mode' => $ssl_mode,
                'socket_options' => [
                    'ssl' => [
                        // TODO: These configuration options make sense for "local" Kolab IMAP,
                        // but when connecting to external one we might want to just disable
                        // cert validation, or make it optional via Account URI parameters
                        'verify_peer' => \config('imap.verify_peer'),
                        'verify_peer_name' => \config('imap.verify_peer'),
                        'verify_host' => \config('imap.verify_host')
                    ],
                ],
            ],
        ];

        return $config;
    }

    /**
     * Limit IMAP flags to these that can be migrated
     */
    private function filterImapFlags($flags)
    {
        // TODO: Support custom flags migration

        return array_filter(
            $flags,
            function ($flag) {
                return in_array($flag, $this->imap->flags);
            }
        );
    }

    /**
     * Check if the folder should not be migrated
     */
    private function shouldSkip($folder): bool
    {
        // TODO: This should probably use NAMESPACE information
        // TODO: This should also skip other user folders

        if (preg_match("/Shared Folders\/.*/", $folder)) {
            return true;
        }

        return false;
    }
}
