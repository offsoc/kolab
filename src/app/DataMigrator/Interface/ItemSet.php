<?php

namespace App\DataMigrator\Interface;

/**
 * Data object representing a set of data items
 */
class ItemSet
{
    /** @var array<Item> Items list */
    public $items = [];

    /**
     * Create an ItemSet instance
     */
    public static function set(array $items = []): self
    {
        $obj = new self();
        $obj->items = $items;

        return $obj;
    }

    public function __serialize(): array
    {
        // Every item has a Folder property, this makes the set
        // needlesly big when serialized. Make the size more compact.
        $folder = count($this->items) ? $this->items[0]->folder : null;

        foreach ($this->items as $item) {
            $item->folder = null;
        }

        return [$folder, $this->items];
    }

    public function __unserialize(array $data): void
    {
        [$folder, $this->items] = $data;

        foreach ($this->items as $item) {
            $item->folder = $folder;
        }
    }
}
