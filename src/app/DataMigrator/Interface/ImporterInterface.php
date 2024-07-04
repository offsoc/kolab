<?php

namespace App\DataMigrator\Interface;

use App\DataMigrator\Account;
use App\DataMigrator\Engine;

interface ImporterInterface
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
     * Create an item in a folder.
     *
     * @param Item $item Item to import
     *
     * @throws \Exception
     */
    public function createItem(Item $item): void;

    /**
     * Create a folder.
     *
     * @param Folder $folder Folder object
     *
     * @throws \Exception
     */
    public function createFolder(Folder $folder): void;

    /**
     * Get a list of folder items, limited to their essential propeties
     * used in incremental migration to skip unchanged items.
     */
    public function getItems(Folder $folder): array;
}
