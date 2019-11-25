<?php

namespace App\DataMigrator;

use App\DataMigratorQueue;
use App\Jobs\DataMigratorEWSFolder;
use App\Jobs\DataMigratorEWSItem;

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

    /** @var \App\DataMigratorQueue Migrator jobs queue */
    protected $queue;

    /** @var array EWS server setup (after autodiscovery) */
    protected $ews = [];


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
        // Create a unique identifier for the migration request
        $queue_id = md5(strval($source).strval($destination).$options['type']);

        // If queue exists, we'll display the progress only
        if ($queue = DataMigratorQueue::find($queue_id)) {
            // If queue contains no jobs, assume invalid
            // TODO: An better API to manage (reset) queues
            if (!$queue->jobs_started || !empty($options['force'])) {
                $queue->delete();
            } else {
                while (true) {
                    printf("Progress [%d of %d]\n", $queue->jobs_finished, $queue->jobs_started);

                    if ($queue->jobs_started == $queue->jobs_finished) {
                        break;
                    }

                    sleep(1);
                    $queue->refresh();
                }

                return;
            }
        }

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

        // Also check user credentials for Kolab destination
        $this->importer = new DAVClient($destination);
        $this->importer->authenticate();

        $this->debug("Source/destination user credentials verified.");
        $this->debug("Fetching folders hierarchy...");

        // Create a queue
        $this->createQueue($queue_id);

        $folders = $this->getFolders();
        $count = 0;

        foreach ($folders as $folder) {
            // Only supported folder types
            if ($folder['type']) {
                $this->debug("Processing folder {$folder['fullname']}...");

                // Dispatch the job (for async execution)
                DataMigratorEWSFolder::dispatch($folder);
                $count++;
            }
        }

        $this->queue->bumpJobsStarted($count);

        $this->debug("Done. {$count} jobs created in queue: {$queue_id}.");
    }

    /**
     * Autodiscover the server and authenticate the user
     */
    protected function authenticate(string $user, string $password, string $loginas = null): void
    {
        // You should never run the Autodiscover more than once.
        // It can make between 1 and 5 calls before giving up, or before finding your server,
        // depending on how many different attempts it needs to make.

        // TODO: After 2020-10-13 EWS at Office365 will require OAuth

        $api = API\ExchangeAutodiscover::getAPI($user, $password);

        $server = $api->getClient()->getServer();
        $version = $api->getClient()->getVersion();
        $options = ['version' => $version];

        if ($loginas) {
            $options['impersonation'] = $loginas;
        }

        $this->debug("Connected to $server ($version). Authenticating...");

        $this->ews = [
            'options' => $options,
            'server' => $server,
        ];

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
                'queue_id' => $this->queue->id,
            ];
        }

        return $result;
    }

    /**
     * Processing of a folder synchronization
     */
    public function processFolder(array $folder): void
    {
        // Job processing - initialize environment
        if (!empty($folder['queue_id'])) {
            $this->initEnv($folder['queue_id']);
        }

        // Create the folder on destination server
        $this->importer->createFolder($folder['fullname'], $folder['type']);

        // The folder is empty, we can stop here
        if (empty($folder['total'])) {
            $this->queue->bumpJobsFinished();
            return;
        }

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
        $count = 0;

        foreach ($response as $item) {
            $count += (int) $this->syncItem($item, $folder);
        }

        $this->queue->bumpJobsStarted($count);

        // Request other pages until we got all
        while (!$response->isIncludesLastItemInRange()) {
            $response = $this->api->getNextPage($response);
            $count = 0;

            foreach ($response as $item) {
                $count += (int) $this->syncItem($item, $folder);
            }

            $this->queue->bumpJobsStarted($count);
        }

        $this->queue->bumpJobsFinished();
    }

    /**
     * Processing of item synchronization
     */
    public function processItem(array $item): void
    {
        // Job processing - initialize environment
        if (!empty($item['queue_id'])) {
            $this->initEnv($item['queue_id']);
        }

        if ($driver = EWS\Item::factory($this, $item['item'], $item)) {
            if ($file = $driver->syncItem($item['item'])) {
                $this->importer->createObjectFromFile($file, $item['fullname']);
                // TODO: remove the file
            }
        }

        $this->queue->bumpJobsFinished();
    }

    /**
     * Synchronize specified object
     */
    protected function syncItem(Type $item, array $folder): bool
    {
        if ($driver = EWS\Item::factory($this, $item, $folder)) {
            // TODO: This object could probably be streamlined down to save some space
            //       All we need is item ID and class.
            $folder['item'] = $item;

            // Dispatch the job (for async execution)
            DataMigratorEWSItem::dispatch($folder);

            return true;
        }

        // TODO IPM.Note (email) and IPM.StickyNote
        // Note: iTip messages in mail folders may have different class assigned
        // https://docs.microsoft.com/en-us/office/vba/outlook/Concepts/Forms/item-types-and-message-classes
        $this->debug("Unsupported object type: {$item->getItemClass()}. Skiped.");

        return false;
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
*/
                    case 'mail':
                        $result[] = EWS\Note::FOLDER_TYPE;
                        break;

                    default:
                        throw new \Exception("Unsupported type: {$type}");
                }
            }

            return $result;
        }

        return $this->folder_classes;
    }

    /**
     * Create a queue for the request
     *
     * @param string $queue_id Unique queue identifier
     */
    protected function createQueue(string $queue_id): void
    {
        $this->queue = new DataMigratorQueue;
        $this->queue->id = $queue_id;

        // TODO: data should be encrypted
        $this->queue->data = [
            'source' => (string) $this->source,
            'destination' => (string) $this->destination,
            'options' => $this->options,
            'ews' => $this->ews,
        ];

        $this->queue->save();
    }

    /**
     * Initialize environment for job execution
     *
     * @param string $queue_id Queue identifier
     */
    protected function initEnv(string $queue_id): void
    {
        $this->queue = DataMigratorQueue::findOrFail($queue_id);
        $this->source = new Account($this->queue->data['source']);
        $this->destination = new Account($this->queue->data['destination']);
        $this->options = $this->queue->data['options'];
        $this->importer = new DAVClient($this->destination);
        $this->api = API::withUsernameAndPassword(
            $this->queue->data['ews']['server'],
            $this->source->username,
            $this->source->password,
            $this->queue->data['ews']['options']
        );
    }
}
