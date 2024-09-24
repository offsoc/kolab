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

    /** @var string Target folder name with path */
    public $targetname;

    /** @var string Storage location (for temporary data) */
    public $location;

    /** @var string Migration queue identifier */
    public $queueId;


    /**
     * Create Folder instance from an array
     */
    public static function fromArray(array $data = []): Folder
    {
        $obj = new self();

        foreach ($data as $key => $value) {
            $obj->{$key} = $value;
        }

        return $obj;
    }

    /**
     * Returns location of a temp file for an Item content
     */
    public function tempFileLocation(string $filename): string
    {
        $filename = preg_replace('/[^a-zA-Z0-9_:@.-]/', '', $filename);

        $location = $this->location;

        // TODO: What if parent folder not yet exists?
        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $location .= '/' . $filename;

        return $location;
    }
}
