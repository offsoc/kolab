<?php

namespace App\DataMigrator;

use garethp\ews\API;
use garethp\ews\API\Type;

/**
 * Data migration from Exchange (EWS)
 */
class EWS
{
    /** @var garethp\ews\API EWS API object */
    public $api;

    /** @var array Supported folder types */
    protected $folder_classes = [
        EWS\Appointment::FOLDER_TYPE,
        EWS\Contact::FOLDER_TYPE,
        EWS\Task::FOLDER_TYPE,
        // TODO: mail and sticky notes are exported as eml files.
        //       We could use imapsync to synchronize mail, but for notes
        //       the only option will be to convert them to Kolab format here
        //       and upload to Kolab via IMAP, I guess
        // EWS\Note::FOLDER_TYPE,
        // EWS\StickyNote::FOLDER_TYPE,
    ];

    /** @var array Interal folders to skip */
    protected $folder_exceptions = [
        'Sharing',
        'Outbox',
        'Calendar/United States holidays',
        'ExternalContacts',
        'AllContacts',
        'AllPersonMetadata',
        'Document Centric Conversations',
        'Favorites',
        'GraphFilesAndWorkingSetSearchFolder',
        'My Contacts',
        'MyContactsExtended',
        'PersonMetadata',
        'People I Know',
        'RelevantContacts',
        'SharedFilesSearchFolder',
        'To-Do Search',
        'UserCuratedContacts',
        'XrmActivityStreamSearch',
        'XrmCompanySearch',
        'XrmDealSearch',
        'XrmSearch',
        'Folder Memberships',
        'Orion Notes',
    ];

    /** @var array Map of EWS folder types to Kolab types */
    protected $type_map = [
        EWS\Appointment::FOLDER_TYPE => DAVClient::TYPE_EVENT,
        EWS\Contact::FOLDER_TYPE => DAVClient::TYPE_CONTACT,
        EWS\Task::FOLDER_TYPE => DAVClient::TYPE_TASK,
    ];

    /** @var string Output location */
    protected $location;

    /** @var Account Source account */
    protected $source;

    /** @var Account Destination account */
    protected $destination;

    /** @var array Migration options */
    protected $options = [];

    /** @var DAVClient Data importer */
    protected $importer;


    /**
     * Print progress/debug information
     */
    public function debug($line)
    {
        // TODO: When not in console mode we should
        // not write to stdout, but to log
        $output = new \Symfony\Component\Console\Output\ConsoleOutput;
        $output->writeln($line);
    }

    /**
     * Return destination account
     */
    public function getDestination()
    {
        return $this->destination;
    }

    /**
     * Return source account
     */
    public function getSource()
    {
        return $this->source;
    }

    /**
     * Execute migration for the specified user
     */
    public function migrate(Account $source, Account $destination, array $options = []): void
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->options = $options;

        // We'll store output in storage/<username> tree
        $this->location = storage_path('export/') . $source->email;

        if (!file_exists($this->location)) {
            mkdir($this->location, 0740, true);
        }

        // Autodiscover and authenticate the user
        $this->authenticate($source->username, $source->password, $source->loginas);

        $this->debug("Logged in. Fetching folders hierarchy...");

        $folders = $this->getFolders();

        if (empty($options['import-only'])) {
            foreach ($folders as $folder) {
                $this->debug("Syncing folder {$folder['fullname']}...");

                if ($folder['total'] > 0) {
                    $this->syncItems($folder);
                }
            }

            $this->debug("Done.");
        }

        if (empty($options['export-only'])) {
            $this->debug("Importing to Kolab account...");

            $this->importer = new DAVClient($destination);

            // TODO: If we were to stay with this storage solution and need still
            //       the import mode, it should not require connecting again to
            //       Exchange. Now we do this for simplicity.
            foreach ($folders as $folder) {
                $this->debug("Syncing folder {$folder['fullname']}...");

                $this->importer->createFolder($folder['fullname'], $folder['type']);

                if ($folder['total'] > 0) {
                    $files = array_diff(scandir($folder['location']), ['.', '..']);
                    foreach ($files as $file) {
                        $this->debug("* Pushing item {$file}...");
                        $this->importer->createObjectFromFile($folder['location'] . '/' . $file, $folder['fullname']);
                        // TODO: remove the file/folder?
                    }
                }
            }

            $this->debug("Done.");
        }
    }

    /**
     * Autodiscover the server and authenticate the user
     */
    protected function authenticate(string $user, string $password, string $loginas = null): void
    {
        // You should never run the Autodiscover more than once.
        // It can make between 1 and 5 calls before giving up, or before finding your server,
        // depending on how many different attempts it needs to make.

        $api = API\ExchangeAutodiscover::getAPI($user, $password);

        $server = $api->getClient()->getServer();
        $version = $api->getClient()->getVersion();
        $options = ['version' => $version];

        if ($loginas) {
            $options['impersonation'] = $loginas;
        }

        $this->debug("Connected to $server ($version). Authenticating...");

        $this->api = API::withUsernameAndPassword($server, $user, $password, $options);
    }

    /**
     * Get folders hierarchy
     */
    protected function getFolders(): array
    {
        // Folder types we're ineterested in
        $folder_classes = $this->folderClasses();

        // Get full folders hierarchy
        $options = [
            'Traversal' => 'Deep',
        ];

        $folders = $this->api->getChildrenFolders('root', $options);

        $result = [];

        foreach ($folders as $folder) {
            $class = $folder->getFolderClass();

            // Skip folder types we do not support
            if (!in_array($class, $folder_classes)) {
                continue;
            }

            $name = $fullname = $folder->getDisplayName();
            $id = $folder->getFolderId()->getId();
            $parentId = $folder->getParentFolderId()->getId();

            // Create folder name with full path
            if ($parentId && !empty($result[$parentId])) {
                $fullname = $result[$parentId]['fullname'] . '/' . $name;
            }

            // Top-level folder, check if it's a special folder we should ignore
            // FIXME: Is there a better way to distinguish user folders from system ones?
            if (in_array($fullname, $this->folder_exceptions)
                || strpos($fullname, 'OwaFV15.1All') === 0
            ) {
                continue;
            }

            $result[$id] = [
                'id' => $folder->getFolderId(),
                'total' => $folder->getTotalCount(),
                'class' => $class,
                'type' => array_key_exists($class, $this->type_map) ? $this->type_map[$class] : null,
                'name' => $name,
                'fullname' => $fullname,
                'location' => $this->location . '/' . $fullname,
            ];
        }

        return $result;
    }

    /**
     * Synchronize specified folder
     */
    protected function syncItems(array $folder): void
    {
        $request = [
            // Exchange's maximum is 1000
            'IndexedPageItemView' => ['MaxEntriesReturned' => 100, 'Offset' => 0, 'BasePoint' => 'Beginning'],
            'ParentFolderIds' => $folder['id']->toArray(true),
            'Traversal' => 'Shallow',
            'ItemShape' => [
                'BaseShape' => 'IdOnly',
                'AdditionalProperties' => [
                    'FieldURI' => ['FieldURI' => 'item:ItemClass'],
                ],
            ],
        ];

        $request = Type::buildFromArray($request);

        // Note: It is not possible to get mimeContent with FindItem request
        //       That's why we first get the list of object identifiers and
        //       then call GetItem on each separately.

        // TODO: It might be feasible to get all properties for object types
        //       for which we don't use MimeContent, for better performance.

        // Request first page
        $response = $this->api->getClient()->FindItem($request);

        foreach ($response as $item) {
            $this->syncItem($item, $folder);
        }

        // Request other pages until we got all
        while (!$response->isIncludesLastItemInRange()) {
            $response = $this->api->getNextPage($response);
            foreach ($response as $item) {
                $this->syncItem($item, $folder);
            }
        }
    }

    /**
     * Synchronize specified object
     */
    protected function syncItem(Type $item, array $folder): void
    {
        if ($driver = EWS\Item::factory($this, $item, $folder)) {
            $driver->syncItem($item);
            return;
        }

        // TODO IPM.Note (email) and IPM.StickyNote
        // Note: iTip messages in mail folders may have different class assigned
        // https://docs.microsoft.com/en-us/office/vba/outlook/Concepts/Forms/item-types-and-message-classes
        $this->debug("Unsupported object type: {$item->getItemClass()}. Skiped.");
    }

    /**
     * Return list of folder classes for current migrate operation
     */
    protected function folderClasses(): array
    {
        if (!empty($this->options['type'])) {
            $types = preg_split('/\s*,\s*/', strtolower($this->options['type']));
            $result = [];

            foreach ($types as $type) {
                switch ($type) {
                    case 'event':
                        $result[] = EWS\Appointment::FOLDER_TYPE;
                        break;
                    case 'contact':
                        $result[] = EWS\Contact::FOLDER_TYPE;
                        break;
                    case 'task':
                        $result[] = EWS\Task::FOLDER_TYPE;
                        break;
/*
                    case 'note':
                        $result[] = EWS\StickyNote::FOLDER_TYPE;
                        break;
                    case 'mail':
                        $result[] = EWS\Note::FOLDER_TYPE;
                        break;
*/
                    default:
                        throw new \Exception("Unsupported type: {$type}");
                }
            }

            return $result;
        }

        return $this->folder_classes;
    }
}
