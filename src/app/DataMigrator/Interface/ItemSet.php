<?php

namespace App\DataMigrator\Interface;

/**
 * Data object representing a set of data items
 */
class ItemSet
{
    /** @var array<Item> Items list */
    public $items = [];

    // TODO: Every item has a $folder property, this makes the set
    // needlesly big when serialized. We should probably store $folder
    // once with the set and remove it from an item on serialize
    // and back in unserialize.

    /**
     * Create an ItemSet instance
     */
    public static function set(array $items = []): ItemSet
    {
        $obj = new self();
        $obj->items = $items;

        return $obj;
    }
}
