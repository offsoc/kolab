<?php

namespace App\DataMigrator;

use App\DataMigrator\Interface\ExporterInterface;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Item;
use Illuminate\Support\Str;

/**
 * Data migration engine
 */
class Engine
{
    public const TYPE_CONTACT = 'contact';
    public const TYPE_EVENT = 'event';
    public const TYPE_GROUP = 'group';
    public const TYPE_MAIL = 'mail';
    public const TYPE_NOTE = 'note';
    public const TYPE_TASK = 'task';

    /** @var Account Source account */
    public $source;

    /** @var Account Destination account */
    public $destination;

    /** @var ?Queue Data migration queue (session) */
    public $queue;

    /** @var ExporterInterface Data exporter */
    protected $exporter;

    /** @var ImporterInterface Data importer */
    protected $importer;

    /** @var array Data migration options */
    protected $options = [];


    /**
     * Execute migration for the specified user
     */
    public function migrate(Account $source, Account $destination, array $options = [])
    {
        $this->source = $source;
        $this->destination = $destination;
        $this->options = $options;

        // Create a unique identifier for the migration request
        $queue_id = md5(strval($source) . strval($destination) . $options['type']);

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

        // Initialize the source
        $this->exporter = $this->initDriver($source, ExporterInterface::class);
        $this->exporter->authenticate();

        // Initialize the destination
        $this->importer = $this->initDriver($destination, ImporterInterface::class);
        $this->importer->authenticate();

        // $this->debug("Source/destination user credentials verified.");
        $this->debug("Fetching folders hierarchy...");

        // Create a queue
        $this->createQueue($queue_id);

        // We'll store output in storage/<username> tree
        $location = storage_path('export/') . $source->email;

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $types = preg_split('/\s*,\s*/', strtolower($this->options['type'] ?? ''));

        $folders = $this->exporter->getFolders($types);
        $count = 0;

        foreach ($folders as $folder) {
            $this->debug("Processing folder {$folder->fullname}...");

            $folder->queueId = $queue_id;
            $folder->location = $location;

            // Dispatch the job (for async execution)
            Jobs\FolderJob::dispatch($folder);
            $count++;
        }

        $this->queue->bumpJobsStarted($count);

        $this->debug(sprintf('Done. %d %s created in queue: %s.', $count, Str::plural('job', $count), $queue_id));
    }

    /**
     * Processing of a folder synchronization
     */
    public function processFolder(Folder $folder): void
    {
        // Job processing - initialize environment
        $this->envFromQueue($folder->queueId);

        // Create the folder on the destination server
        $this->importer->createFolder($folder);

        $count = 0;

        // Fetch items from the source
        $this->exporter->fetchItemList(
            $folder,
            function (Item $item) use (&$count) {
                // Dispatch the job (for async execution)
                Jobs\ItemJob::dispatch($item);
                $count++;
            },
            $this->importer
        );

        if ($count) {
            $this->queue->bumpJobsStarted($count);
        }

        $this->queue->bumpJobsFinished();
    }

    /**
     * Processing of item synchronization
     */
    public function processItem(Item $item): void
    {
        // Job processing - initialize environment
        $this->envFromQueue($item->folder->queueId);

        if ($filename = $this->exporter->fetchItem($item)) {
            $item->filename = $filename;
            $this->importer->createItem($item);
            // TODO: remove the file
        }

        $this->queue->bumpJobsFinished();
    }

    /**
     * Print progress/debug information
     */
    public function debug($line)
    {
        if (!empty($this->options['stdout'])) {
            $output = new \Symfony\Component\Console\Output\ConsoleOutput();
            $output->writeln("$line");
        } else {
            \Log::debug("[DataMigrator] $line");
        }
    }

    /**
     * Set migration queue option. Use this if you need to pass
     * some data between queue processes.
     */
    public function setOption(string $name, $value): void
    {
        $this->options[$name] = $value;

        if ($this->queue) {
            $this->queue->data = $this->queueData();
            $this->queue->save();
        }
    }

    /**
     * Create a queue for the request
     *
     * @param string $queue_id Unique queue identifier
     */
    protected function createQueue(string $queue_id): void
    {
        $this->queue = new Queue();
        $this->queue->id = $queue_id;
        $this->queue->data = $this->queueData();
        $this->queue->save();
    }

    /**
     * Prepare queue data
     */
    protected function queueData()
    {
        $options = $this->options;
        unset($options['stdout']); // jobs aren't in stdout anymore

        // TODO: data should be encrypted
        return [
            'source' => (string) $this->source,
            'destination' => (string) $this->destination,
            'options' => $options,
        ];
    }

    /**
     * Initialize environment for job execution
     *
     * @param string $queueId Queue identifier
     */
    protected function envFromQueue(string $queueId): void
    {
        $this->queue = Queue::findOrFail($queueId);

        $this->source = new Account($this->queue->data['source']);
        $this->destination = new Account($this->queue->data['destination']);
        $this->options = $this->queue->data['options'];

        $this->importer = $this->initDriver($this->destination, ImporterInterface::class);
        $this->exporter = $this->initDriver($this->source, ExporterInterface::class);
    }

    /**
     * Initialize (and select) migration driver
     */
    protected function initDriver(Account $account, string $interface)
    {
        switch ($account->scheme) {
            case 'ews':
                $driver = new EWS($account, $this);
                break;

            case 'dav':
            case 'davs':
                $driver = new DAV($account, $this);
                break;
            /*
            case 'imap':
            case 'imaps':
                $driver = new IMAP($account, $this);
                break;
            */

            default:
                throw new \Exception("Failed to init driver for '{$account->scheme}'");
        }

        // Make sure driver is used in the direction it supports
        if (!is_a($driver, $interface)) {
            throw new \Exception(sprintf(
                "'%s' driver does not implement %s",
                class_basename($driver),
                class_basename($interface)
            ));
        }

        return $driver;
    }
}
