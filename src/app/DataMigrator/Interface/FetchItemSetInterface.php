<?php

namespace App\DataMigrator\Interface;

interface FetchItemSetInterface
{
    /**
     * Fetching a set of items in one operation
     *
     * @param ItemSet  $set      Set of items
     * @param callable $callback A callback to execute on every Item
     */
    public function fetchItemSet(ItemSet $set, $callback): void;
}
