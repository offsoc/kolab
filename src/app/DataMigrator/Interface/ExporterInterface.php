<?php

namespace App\DataMigrator\Interface;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;

interface ExporterInterface
{
    /**
     * Object constructor
     */
    public function __construct(Account $account, Engine $engine);

    /**
     * Check user credentials.
     *
     * @throws \Exception
     */
    public function authenticate();

    /**
     * Get folders hierarchy
     */
    public function getFolders($types = []): array;

    /**
     * Fetch a list of folder items
     */
    public function fetchItemList(Folder $folder, $callback, ImporterInterface $importer): void;

    /**
     * Fetching an item
     */
    public function fetchItem(Item $item): void;
}
