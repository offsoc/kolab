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
     * @param string $filename File location
     * @param Folder $folder   Folder object
     *
     * @throws \Exception
     */
    public function createItemFromFile(string $filename, Folder $folder): void;

    /**
     * Create a folder.
     *
     * @param Folder $folder Folder object
     *
     * @throws \Exception
     */
    public function createFolder(Folder $folder): void;
}
