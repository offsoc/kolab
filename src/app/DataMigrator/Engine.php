<?php

namespace App\DataMigrator;

use App\DataMigrator\Interface\ExporterInterface;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\ImporterInterface;
use App\DataMigrator\Interface\Item;
use App\DataMigrator\Interface\ItemSet;
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
        $queue_id = md5(strval($source) . strval($destination) . ($options['type'] ?? ''));

        // TODO: When running in 'sync' mode we shouldn't create a queue at all

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

        // Create a queue
        $this->createQueue($queue_id);

        // We'll store output in storage/<username> tree
        $location = storage_path('export/') . $source->email;

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $types = preg_split('/\s*,\s*/', strtolower($options['type'] ?? ''));

        $this->debug("Fetching folders hierarchy...");

        $folders = $this->exporter->getFolders($types);
        $count = 0;
        $async = empty($options['sync']);

        foreach ($folders as $folder) {
            $this->debug("Processing folder {$folder->fullname}...");

            $folder->queueId = $queue_id;
            $folder->location = $location;

            if ($async) {
                // Dispatch the job (for async execution)
                Jobs\FolderJob::dispatch($folder);
                $count++;
            } else {
                $this->processFolder($folder);
            }
        }

        if ($count) {
            $this->queue->bumpJobsStarted($count);
        }

        if ($async) {
            $this->debug(sprintf('Done. %d %s created in queue: %s.', $count, Str::plural('job', $count), $queue_id));
        } else {
            $this->debug(sprintf('Done (queue: %s).', $queue_id));
        }
    }

    /**
     * Processing of a folder synchronization
     */
    public function processFolder(Folder $folder): void
    {
        // Job processing - initialize environment
        if (!$this->queue) {
            $this->envFromQueue($folder->queueId);
        }

        // Create the folder on the destination server
        $this->importer->createFolder($folder);

        $count = 0;
        $async = empty($this->options['sync']);

        // Fetch items from the source
        $this->exporter->fetchItemList(
            $folder,
            function ($item_or_set) use (&$count, $async) {
                if ($async) {
                    // Dispatch the job (for async execution)
                    if ($item_or_set instanceof ItemSet) {
                        Jobs\ItemSetJob::dispatch($item_or_set);
                    } else {
                        Jobs\ItemJob::dispatch($item_or_set);
                    }
                    $count++;
                } else {
                    if ($item_or_set instanceof ItemSet) {
                        $this->processItemSet($item_or_set);
                    } else {
                        $this->processItem($item_or_set);
                    }
                }
            },
            $this->importer
        );

        if ($count) {
            $this->queue->bumpJobsStarted($count);
        }

        if ($async) {
            $this->queue->bumpJobsFinished();
        }
    }

    /**
     * Processing of item synchronization
     */
    public function processItem(Item $item): void
    {
        // Job processing - initialize environment
        if (!$this->queue) {
            $this->envFromQueue($item->folder->queueId);
        }

        $this->exporter->fetchItem($item);
        $this->importer->createItem($item);

        if (!empty($item->filename)) {
            unlink($item->filename);
        }

        if (empty($this->options['sync'])) {
            $this->queue->bumpJobsFinished();
        }
    }

    /**
     * Processing of item-set synchronization
     */
    public function processItemSet(ItemSet $set): void
    {
        // Job processing - initialize environment
        if (!$this->queue) {
            $this->envFromQueue($set->items[0]->folder->queueId);
        }

        // TODO: Some exporters, e.g. DAV, might optimize fetching multiple items in one go,
        // we'll need a new API to do that

        foreach ($set->items as $item) {
            $this->exporter->fetchItem($item);
            $this->importer->createItem($item);

            if (!empty($item->filename)) {
                unlink($item->filename);
            }
        }

        // TODO: We should probably also track number of items migrated
        if (empty($this->options['sync'])) {
            $this->queue->bumpJobsFinished();
        }
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
     * Get migration option value.
     */
    public function getOption(string $name)
    {
        return $this->options[$name] ?? null;
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

            case 'imap':
            case 'imaps':
            case 'tls':
            case 'ssl':
                $driver = new IMAP($account, $this);
                break;

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
