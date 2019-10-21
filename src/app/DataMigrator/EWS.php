<?php

namespace App\DataMigrator;

use garethp\ews\API;
use garethp\ews\API\ExchangeAutodiscover;
use garethp\ews\API\Type;
use garethp\ews\API\Type\IndexedPageViewType;

/**
 * Data migration factory
 */
class EWS
{
    const TYPE_EVENT   = 'IPF.Appointment';
    const TYPE_CONTACT = 'IPF.Contact';
    const TYPE_MAIL    = 'IPF.Note';
    const TYPE_NOTE    = 'IPF.StickyNote';
    const TYPE_TASK    = 'IPF.Task';

    /** @var garethp\ews\API EWS API object */
    protected $api;

    /** @var array File extensions by type */
    protected $extensions = [
        self::TYPE_EVENT   => 'ics',
        self::TYPE_CONTACT => 'vcf',
        self::TYPE_MAIL    => 'eml',
        self::TYPE_NOTE    => 'note',
        // TODO: Surprise, surprise, tasks aren't exported in iCal format, we'll have to convert them
        self::TYPE_TASK    => 'eml',
    ];

    /** @var array Supported folder types */
    protected $folder_classes = [
        self::TYPE_EVENT,
        self::TYPE_CONTACT,
        // self::TYPE_MAIL,
        // self::TYPE_NOTE,
        // self::TYPE_TASK,
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
    public function migrate($user, $password)
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

            $this->syncItems($folder);
        }

        $this->debug("Done.");
    }

    /**
     * Autodiscover the server and authenticate the user
     */
    protected function authenticate($user, $password)
    {
        // You should never run the Autodiscover more than once.
        // It can make between 1 and 5 calls before giving up, or before finding your server,
        // depending on how many different attempts it needs to make.

        $api = ExchangeAutodiscover::getAPI($user, $password);

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
            ];
        }

        return $result;
    }

    /**
     * Synchronize specified folder
     */
    protected function syncItems($folder)
    {
        $request = [
            // Exchange's maximum is 1000, use it
            'IndexedPageViewType' => new IndexedPageViewType(1000, 0),
            'ParentFolderIds' => $folder['id']->toArray(true),
            'Traversal' => 'Shallow',
            'ItemShape' => [
                'BaseShape' => 'IdOnly'
            ],
        ];

        // Request additional fields, e.g. UID for calendar items.
        // Just so we can print it before fetching the item.
        if ($folder['class'] == self::TYPE_EVENT) {
            $request['ItemShape']['AdditionalProperties'] = [
                'FieldURI' => array('FieldURI' => API\FieldURIManager::getFieldUriByName('UID', 'calendar')),
            ];
        }

        // Note: It is not possible to get mimeContent with FindItem request
        //       That's why we first get the list of object identifiers and
        //       then call GetItem on each separately.

        $response = $this->api->getClient()->FindItem($request);

        foreach ($response as $item) {
            $this->syncItem($item, $folder);
        }

        // FIXME: For some reason paging does not work, the initial request contains all items
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
    protected function syncItem($item, $folder)
    {
        $itemId = $item->getItemId();

        if ($folder['class'] == self::TYPE_EVENT) {
            $id = $item->getUID();
        } else {
            // TODO: We should we generate an UID for objects that do not have it
            //       and inject it into the output file
            $id = $itemId->getId();
        }

        $id = preg_replace('/[^a-zA-Z0-9_:@-]/', '', $id);

        $this->debug("* Saving item {$id}...");

        // Request IncludeMimeContent as it's not included by default
        $options = [
            'ItemShape' => [
                'BaseShape' => 'Default',
                'IncludeMimeContent' => true,
            ]
        ];

        // Fetch the item
        $item = $this->api->getItem($itemId, $options);

        // TODO: Groups are not exported in vCard format, they use eml
        //       What's more the output does not include members, so
        //       we'll have to ask for 'Members' attribute and create a vCard
        if ($item instanceof API\Type\DistributionListType) {
            return;
        }

        $location = $this->location . '/' . $folder['fullname'];

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $location .= '/' . $id . '.' . $this->extensions[$folder['class']];
        $content = base64_decode((string) $item->getMimeContent());

        // TODO: calendar event attachments are exported as:
        //       ATTACH:CID:81490FBA13A3DC2BF071B894C96B44BA51BEAAED@eurprd05.prod.outlook.com
        //       I.e. we have to handle them separately

        file_put_contents($location, $content);
    }

    /**
     * Print progress/debug information
     */
    protected function debug($line)
    {
        // TODO: When not in console mode we should
        // not write to stdout, but to log
        $output = new \Symfony\Component\Console\Output\ConsoleOutput;
        $output->writeln($line);
    }
}
