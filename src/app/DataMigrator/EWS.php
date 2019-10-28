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

    /** @var string Output location */
    protected $location;


    /**
     * Execute migration for the specified user
     */
    public function migrate(string $user, string $password): void
    {
        // Autodiscover and authenticate the user
        $this->authenticate($user, $password);

        // We'll store output in storage/<username> tree
        $this->location = storage_path('export/') . $user;

        if (!file_exists($this->location)) {
            mkdir($this->location, 0740, true);
        }

        $this->debug("Logged in. Fetching folders hierarchy...");

        $folders = $this->getFolders();

        foreach ($folders as $folder) {
            $this->debug("Syncing folder {$folder['fullname']}...");

            if ($folder['total'] > 0) {
                $this->syncItems($folder);
            }
        }

        $this->debug("Done.");
    }

    /**
     * Autodiscover the server and authenticate the user
     */
    protected function authenticate(string $user, string $password): void
    {
        // You should never run the Autodiscover more than once.
        // It can make between 1 and 5 calls before giving up, or before finding your server,
        // depending on how many different attempts it needs to make.

        $api = API\ExchangeAutodiscover::getAPI($user, $password);

        $server = $api->getClient()->getServer();
        $version = $api->getClient()->getVersion();

        $this->debug("Connected to $server ($version). Authenticating...");

        $this->api = API::withUsernameAndPassword($server, $user, $password, [
                'version' => $version
        ]);
    }

    /**
     * Get folders hierarchy
     */
    protected function getFolders(): array
    {
        // Get full folders hierarchy
        $options = [
            'Traversal' => 'Deep',
        ];

        $folders = $this->api->getChildrenFolders('root', $options);

        $result = [];

        foreach ($folders as $folder) {
            $class = $folder->getFolderClass();

            // Skip folder types we do not support
            if (!in_array($class, $this->folder_classes)) {
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
                    'FieldURI' => ['FieldURI' => API\FieldURIManager::getFieldUriByName('ItemClass', 'item')],
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
     * Print progress/debug information
     */
    public function debug($line)
    {
        // TODO: When not in console mode we should
        // not write to stdout, but to log
        $output = new \Symfony\Component\Console\Output\ConsoleOutput;
        $output->writeln($line);
    }
}
