<?php

namespace App\DataMigrator;

use App\Jobs\DataMigrator\EWSFolderJob;
use App\Jobs\DataMigrator\EWSItemJob;
use garethp\ews\API;
use garethp\ews\API\Type;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Data migration from Exchange (EWS)
 */
class EWS
{
    /** @var API EWS API object */
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
        'AllContacts',
        'AllPersonMetadata',
        'AllTodoTasks',
        'Document Centric Conversations',
        'ExternalContacts',
        'Favorites',
        'Flagged Emails',
        'GraphFilesAndWorkingSetSearchFolder',
        'My Contacts',
        'MyContactsExtended',
        'Orion Notes',
        'Outbox',
        'PersonMetadata',
        'People I Know',
        'RelevantContacts',
        'SharedFilesSearchFolder',
        'Sharing',
        'To-Do Search',
        'UserCuratedContacts',
        'XrmActivityStreamSearch',
        'XrmCompanySearch',
        'XrmDealSearch',
        'XrmSearch',
        'Folder Memberships',
        // TODO: These are different depending on a user locale
        'Calendar/United States holidays',
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

    /** @var Queue Migrator jobs queue */
    protected $queue;

    /** @var array EWS server setup (after autodiscovery) */
    protected $ews = [];


    /**
     * Print progress/debug information
     */
    public function debug($line)
    {
        if (!empty($this->options['stdout'])) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            $output->writeln("$line");
        } else {
            \Log::debug("[EWS] $line");
        }
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

        // Create a unique identifier for the migration request
        $queue_id = md5(strval($source).strval($destination).$options['type']);

        // If queue exists, we'll display the progress only
        if ($queue = Queue::find($queue_id)) {
            // If queue contains no jobs, assume invalid
            // TODO: An better API to manage (reset) queues
            if (!$queue->jobs_started || !empty($options['force'])) {
                $queue->delete();
            } else {
                while (true) {
                    $this->debug(sprintf("Progress [%d of %d]\n", $queue->jobs_finished, $queue->jobs_started));

                    if ($queue->jobs_started == $queue->jobs_finished) {
                        break;
                    }

                    sleep(1);
                    $queue->refresh();
                }

                return;
            }
        }

        // We'll store output in storage/<username> tree
        $this->location = storage_path('export/') . $source->email;

        if (!file_exists($this->location)) {
            mkdir($this->location, 0740, true);
        }

        // Initialize the source
        $this->api = $this->authenticate($source);

        // Initialize the destination
        $this->importer = new DAVClient($destination);
        // $this->importer->authenticate();

        // $this->debug("Source/destination user credentials verified.");
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
                EWSFolderJob::dispatch($folder);
                $count++;
            }
        }

        $this->queue->bumpJobsStarted($count);

        $this->debug(sprintf('Done. %d %s created in queue: %s.', $count, Str::plural('job', $count), $queue_id));
    }

    /**
     * Server autodiscovery
     */
    public static function autodiscover(string $user, string $password): ?string
    {
        // You should never run the Autodiscover more than once.
        // It can make between 1 and 5 calls before giving up, or before finding your server,
        // depending on how many different attempts it needs to make.

        // TODO: Autodiscovery may fail with an exception thrown. Handle this nicely.
        // TODO: Looks like this autodiscovery also does not work w/Basic Auth?

        $api = API\ExchangeAutodiscover::getAPI($user, $password);

        $server = $api->getClient()->getServer();
        $version = $api->getClient()->getVersion();

        return sprintf('ews://%s:%s@%s', urlencode($user), urlencode($password), $server);
    }

    /**
     * Authenticate to EWS
     */
    protected function authenticate(Account $source)
    {
        if (!empty($source->params['client_id'])) {
            return $this->authenticateWithOAuth2(
                $source->host,
                $source->username,
                $source->params['client_id'],
                $source->params['client_secret'],
                $source->params['tenant_id']
            );
        }

        return $this->authenticateWithPassword(
            $source->host,
            $source->username,
            $source->password,
            $source->loginas
        );
    }

    /**
     * Autodiscover the server and authenticate the user
     */
    protected function authenticateWithPassword(string $server, string $user, string $password, string $loginas = null)
    {
        // Note: Since 2023-01-01 EWS at Office365 requires OAuth2, no way back to basic auth.

        $this->debug("Using basic authentication on $server...");

        $version = API\ExchangeWebServices::VERSION_2013;
        $options = [
            'version' => $version,
            // 'httpClient' => $client ?? null, // If you want to inject your own GuzzleClient for the requests
        ];

        if ($loginas) {
            $options['impersonation'] = $loginas;
        }

        // In debug mode record all responses
        /*
        if (\config('app.debug')) {
            $options['httpPlayback'] = [
                'mode' => 'record',
                'recordLocation' => \storage_path('ews'),
            ];
        }
        */

        $this->ews = [
            'options' => $options,
            'server' => $server,
        ];

        return API::withUsernameAndPassword($server, $user, $password, $options);
    }

    /**
     * Authenticate with a token (Office365)
     */
    protected function authenticateWithToken(string $server, string $user, string $token, $expires_at = null)
    {
        $this->debug("Using token authentication on $server...");

        $version = API\ExchangeWebServices::VERSION_2013;
        $options = [
            'version' => $version,
            // 'httpClient' => $client, // If you want to inject your own GuzzleClient for the requests
            'impersonation' => $user,
        ];

        // In debug mode record all responses
        /*
        if (\config('app.debug')) {
            $options['httpPlayback'] = [
                'mode' => 'record',
                'recordLocation' => \storage_path('ews'),
            ];
        }
        */

        $this->ews = [
            'options' => $options,
            'server' => $server,
            'token' => $token,
            'expires_at' => $expires_at,
        ];

        return API::withCallbackToken($server, $token, $options);
    }

    /**
     * Authenticate with OAuth2 (Office365) - get the token
     */
    protected function authenticateWithOAuth2(string $server, string $user, string $client_id,
        string $client_secret, string $tenant_id)
    {
        // See https://github.com/Garethp/php-ews/blob/master/examples/basic/authenticatingWithOAuth.php
        // See https://github.com/Garethp/php-ews/issues/236#issuecomment-1292521527
        // To register OAuth2 app goto https://entra.microsoft.com > Applications > App registrations

        $this->debug("Fetching OAuth2 token from $server...");

        $scope = 'https://outlook.office365.com/.default';
        $token_uri = "https://login.microsoftonline.com/{$tenant_id}/oauth2/v2.0/token";
        // $authUri = "https://login.microsoftonline.com/{$tenant_id}/oauth2/authorize";

        $response = Http::asForm()
            ->timeout(5)
            ->post($token_uri, [
                'client_id' => $client_id,
                'client_secret' => $client_secret,
                'scope' => $scope,
                'grant_type' => 'client_credentials',
            ])
            ->throwUnlessStatus(200);

        $token = $response->json('access_token');

        // Note: Office365 default token expiration time is ~1h,
        $expires_in = $response->json('expires_in');
        $expires_at = now()->addSeconds($expires_in)->toDateTimeString();

        return $this->authenticateWithToken($server, $user, $token, $expires_at);
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

            // Note: Folder names are localized
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
                'type' => $this->type_map[$class] ?? null,
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

        // @phpstan-ignore-next-line
        foreach ($response as $item) {
            $count += (int) $this->syncItem($item, $folder);
        }

        $this->queue->bumpJobsStarted($count);

        // Request other pages until we got all
        while (!$response->isIncludesLastItemInRange()) {
            // @phpstan-ignore-next-line
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
            EWSItemJob::dispatch($folder);

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

    /**
     * Create a queue for the request
     *
     * @param string $queue_id Unique queue identifier
     */
    protected function createQueue(string $queue_id): void
    {
        $this->queue = new Queue;
        $this->queue->id = $queue_id;

        $options = $this->options;
        unset($options['stdout']); // jobs aren't in stdout anymore

        // TODO: data should be encrypted
        $this->queue->data = [
            'source' => (string) $this->source,
            'destination' => (string) $this->destination,
            'options' => $options,
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
        $this->queue = Queue::findOrFail($queue_id);
        $this->source = new Account($this->queue->data['source']);
        $this->destination = new Account($this->queue->data['destination']);
        $this->options = $this->queue->data['options'];
        $this->importer = new DAVClient($this->destination);
        $this->ews = $this->queue->data['ews'];

        if (!empty($this->ews['token'])) {
            // TODO: Refresh the token if needed
            $this->api = API::withCallbackToken(
                $this->ews['server'],
                $this->ews['token'],
                $this->ews['options']
            );
        } else {
            $this->api = API::withUsernameAndPassword(
                $this->ews['server'],
                $this->source->username,
                $this->source->password,
                $this->ews['options']
            );
        }
    }
}
