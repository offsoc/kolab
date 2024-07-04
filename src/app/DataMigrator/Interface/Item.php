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

    /** @var false|string Identifier/Location of the item if exists in the destination folder */
    public $existing = false;

    /** @var ?string Exported object location in the local storage */
    public $filename;


    public static function fromArray(array $data = [])
    {
        $obj = new self();

        foreach ($data as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
