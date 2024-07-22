<?php

namespace App\DataMigrator\Interface;

/**
 * Data object representing a set of data items
 */
class ItemSet implements \Serializable
{
    /** @var array<Item> Items list */
    public $items = [];

    /**
     * Create an ItemSet instance
     */
    public static function set(array $items = []): ItemSet
    {
        $obj = new self();
        $obj->items = $items;

        return $obj;
    }

    public function serialize(): ?string
    {
        // Every item has a Folder property, this makes the set
        // needlesly big when serialized. Make the size more compact.
        $folder = count($this->items) ? $this->items[0]->folder : null;

        foreach ($this->items as $item) {
            $item->folder = null;
        }

        return serialize([$folder, $this->items]);
    }

    public function unserialize(string $data): void
    {
        [$folder, $this->items] = unserialize($data);

        foreach ($this->items as $item) {
            $item->folder = $folder;
        }
    }
}
