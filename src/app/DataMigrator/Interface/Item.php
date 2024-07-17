<?php

namespace App\DataMigrator\Interface;

/**
 * Data object representing a data item
 */
class Item
{
    /** @var mixed Item identifier */
    public $id;

    /** @var Folder Folder */
    public $folder;

    /** @var string Object class */
    public $class;

    /**
     * Identifier/Location of the item if exists in the destination folder.
     * And/or some metadata on the existing item. This information is driver specific.
     *
     * @var string|array|null
     */
    public $existing;

    /** @var ?string Exported object location in the local storage */
    public $filename;

    /** @var array Extra data to migrate (like email flags, internaldate, etc.) */
    public $data = [];


    public static function fromArray(array $data = []): Item
    {
        $obj = new self();

        foreach ($data as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
