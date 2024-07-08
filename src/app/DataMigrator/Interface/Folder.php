<?php

namespace App\DataMigrator\Interface;

use App\DataMigrator\Queue;

/**
 * Data object representing a folder
 */
class Folder
{
    /** @var mixed Folder identifier */
    public $id;

    /** @var int Number of items in the folder */
    public $total;

    /** @var string Folder class */
    public $class;

    /** @var string Folder Kolab object type */
    public $type;

    /** @var string Folder name */
    public $name;

    /** @var string Folder name with path */
    public $fullname;

    /** @var string Storage location (for temporary data) */
    public $location;

    /** @var string Migration queue identifier */
    public $queueId;


    public static function fromArray(array $data = []): Folder
    {
        $obj = new self();

        foreach ($data as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }
}
