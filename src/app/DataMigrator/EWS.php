<?php

namespace App\DataMigrator;

use garethp\ews\API;
use garethp\ews\API\Type;

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
        self::TYPE_TASK    => 'ics',
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

            $this->syncItems($folder);
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
            // Exchange's maximum is 1000, use it
            'IndexedPageViewType' => new Type\IndexedPageViewType(1000, 0),
            'ParentFolderIds' => $folder['id']->toArray(true),
            'Traversal' => 'Shallow',
            'ItemShape' => [
                'BaseShape' => 'IdOnly'
            ],
        ];

        // Request additional fields, e.g. UID for calendar items.
        // Just so we can print it before fetching the item.
        // Note: Only calendar items have UIDs in Exchange
        if ($folder['class'] == self::TYPE_EVENT) {
            $request['ItemShape']['AdditionalProperties'] = [
                'FieldURI' => ['FieldURI' => API\FieldURIManager::getFieldUriByName('UID', 'calendar')],
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
    protected function syncItem(Type $item, array $folder): void
    {
        $itemId = $item->getItemId();

        if ($folder['class'] == self::TYPE_EVENT) {
            $uid = $item->getUID();
        } else {
            // We should generate an UID for objects that do not have it
            // and inject it into the output file
            // FIXME: Should we use e.g. md5($itemId->getId()) instead?
            $uid = \App\Utils::uuidStr();
        }

        $uid = preg_replace('/[^a-zA-Z0-9_:@-]/', '', $uid);

        $this->debug("* Saving item {$uid}...");

        // Fetch the item
        $item = $this->api->getItem($itemId, $this->getItemRequest($folder));

        // Apply type-specific format converters
        if ($this->processItem($item, $uid) === false) {
            return;
        }

        $location = $this->location . '/' . $folder['fullname'];

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $location .= '/' . $uid . '.' . $this->extensions[$folder['class']];

        file_put_contents($location, (string) $item->getMimeContent());
    }

    /**
     * Get GetItem request parameters
     */
    protected function getItemRequest(array $folder): array
    {
        $request = [
            'ItemShape' => [
                // Reqest default set of properties
                'BaseShape' => 'Default',
                // Additional properties, e.g. LastModifiedTime
                'AdditionalProperties' => [
                    'FieldURI' => ['FieldURI' => API\FieldURIManager::getFieldUriByName('LastModifiedTime', 'item')],
                ]
            ]
        ];

        // Request IncludeMimeContent as it's not included by default
        // Note: Only for these object type that return useful MIME content
        if ($folder['class'] != self::TYPE_TASK) {
            $request['ItemShape']['IncludeMimeContent'] = true;
        }

        return $request;
    }

    /**
     * Post-process GetItem() result
     */
    protected function processItem(&$item, string $uid): bool
    {
        // Decode MIME content
        if (!($item instanceof Type\TaskType) && !($item instanceof Type\DistributionListType)) {
            // TODO: Maybe find less-hacky way
            $content = $item->getMimeContent();
            $content->_ = base64_decode((string) $content);
        }

        // Get object's class name (remove namespace and unwanted parts)
        $item_class = preg_replace('/(Type|Item|.*\\\)/', '', get_class($item));

        // Execute type-specific item processor
        switch ($item_class) {
            case 'DistributionList':
            case 'Contact':
            case 'Calendar':
            case 'Task':
            // case 'Message':
            // case 'Note':
                return $this->{'process' . $item_class . 'Item'}($item, $uid);

            default:
                $this->debug("Unsupported object type: {$item_class}. Skiped.");
                return false;
        }
    }

    /**
     * Convert distribution list object to vCard
     */
    protected function processDistributionListItem(&$item, string $uid): bool
    {
        // Groups (Distribution Lists) are not exported in vCard format, they use eml

        $vcard = "BEGIN:VCARD\r\nVERSION:4.0\r\nPRODID:Kolab EWS DataMigrator\r\n";
        $vcard .= "UID:{$uid}\r\n";
        $vcard .= "KIND:group\r\n";
        $vcard .= "FN:" . $item->getDisplayName() . "\r\n";
        $vcard .= "REV;VALUE=DATE-TIME:" . $item->getLastModifiedTime() . "\r\n";

        // Process list members
        // Note: The fact that getMembers() returns stdClass is probably a bug in php-ews
        foreach ($item->getMembers()->Member as $member) {
            $mailbox = $member->getMailbox();
            $mailto = $mailbox->getEmailAddress();
            $name = $mailbox->getName();

            // FIXME: Investigate if mailto: members are handled properly by Kolab
            //        or we need to use MEMBER:urn:uuid:9bd97510-9dbb-4810-a144-6180962df5e0 syntax
            //        But do not forget lists can have members that are not contacts

            if ($mailto) {
                if ($name && $name != $mailto) {
                    $mailto = urlencode(sprintf('"%s" <%s>', addcslashes($name, '"'), $mailto));
                }

                $vcard .= "MEMBER:mailto:{$mailto}\r\n";
            }
        }

        $vcard .= "END:VCARD";

        // TODO: Maybe find less-hacky way
        $item->getMimeContent()->_ = $vcard;

        return true;
    }

    /**
     * Process contact object
     */
    protected function processContactItem(&$item, string $uid): bool
    {
        $vcard = (string) $item->getMimeContent();

        // Inject UID to the vCard
        $vcard = str_replace("BEGIN:VCARD", "BEGIN:VCARD\r\nUID:{$uid}", $vcard);

        // Note: Looks like PHOTO property is exported properly, so we
        //       don't have to handle attachments as we do for calendar items

        // TODO: Maybe find less-hacky way
        $item->getMimeContent()->_ = $vcard;

        return true;
    }

    /**
     * Process event object
     */
    protected function processCalendarItem(&$item, string $uid): bool
    {
        // Inject attachment bodies into the iCalendar content
        // Calendar event attachments are exported as:
        // ATTACH:CID:81490FBA13A3DC2BF071B894C96B44BA51BEAAED@eurprd05.prod.outlook.com
        if ($item->getHasAttachments()) {
            $ical = (string) $item->getMimeContent();

            // FIXME: I've tried hard and no matter what ContentId property is always empty
            //        This means we can't match the CID from iCalendar with the attachment.
            //        That's why we'll just remove all ATTACH:CID:... occurrences
            //        and inject attachments to the main event
            $ical = preg_replace('/\r\nATTACH:CID:[^\r]+\r\n(\r\n [^\r\n]*)?/', '', $ical);

            foreach ((array) $item->getAttachments()->getFileAttachment() as $attachment) {
                $_attachment = $this->getAttachment($attachment);

                // FIXME: This is imo inconsistence on php-ews side that MimeContent
                //        is base64 encoded, but Content isn't
                // TODO: We should not do it in memory to not exceed the memory limit
                $body = base64_encode($_attachment->getContent());
                $body = rtrim(chunk_split($body, 74, "\r\n "), ' ');

                $ctype = $_attachment->getContentType();

                // Inject the attachment at the end of the first VEVENT block
                // TODO: We should not do it in memory to not exceed the memory limit
                $append = "ATTACH;VALUE=BINARY;ENCODING=BASE64;FMTTYPE={$ctype}:\r\n {$body}";
                $pos = strpos($ical, "\r\nEND:VEVENT");
                $ical = substr_replace($ical, $append, $pos + 2, 0);
            }

            // TODO: Maybe find less-hacky way
            $item->getMimeContent()->_ = $ical;
        }

        return true;
    }

    /**
     * Process task object
     */
    protected function processTaskItem(&$item, string $uid): bool
    {
        // TODO: convert to iCalendar

        return false;
    }

    /**
     * Fetch attachment object from Exchange
     */
    protected function getAttachment(Type\FileAttachmentType $attachment)
    {
        $request = [
            'AttachmentIds' => [
                $attachment->getAttachmentId()->toXmlObject()
            ],
            'AttachmentShape' => [
                'IncludeMimeContent' => true,
            ]
        ];

        return $this->api->getClient()->GetAttachment($request);
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
