<?php

namespace App\DataMigrator\EWS;

use App\DataMigrator\EWS;

use garethp\ews\API;
use garethp\ews\API\Type;

/**
 * Abstraction for object handlers
 */
abstract class Item
{
    /** @var EWS Data migrator object */
    protected $engine;

    /** @var array Current folder data */
    protected $folder;

    /** @var string Current item UID */
    protected $uid;


    /**
     * Object constructor
     */
    public function __construct(EWS $engine, array $folder)
    {
        $this->engine = $engine;
        $this->folder = $folder;
    }

    /**
     * Factory method.
     * Returns object suitable to handle specified item type.
     */
    public static function factory(EWS $engine, Type $item, array $folder)
    {
        $item_class = str_replace('IPM.', '', $item->getItemClass());
        $item_class = "\App\DataMigrator\EWS\\{$item_class}";

        if (class_exists($item_class)) {
            return new $item_class($engine, $folder);
        }
    }

    /**
     * Synchronize specified object
     */
    public function syncItem(Type $item): void
    {
        // Fetch the item
        $item = $this->engine->api->getItem($item->getItemId(), $this->getItemRequest());

        $uid = $this->getUID($item);

        $this->engine->debug("* Saving item {$uid}...");

        // Apply type-specific format converters
        if ($this->processItem($item) === false) {
            return;
        }

        $uid = preg_replace('/[^a-zA-Z0-9_:@-]/', '', $uid);

        $location = $this->folder['location'];

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $location .= '/' . $uid . '.' . $this::FILE_EXT;

        file_put_contents($location, (string) $item->getMimeContent());
    }

    /**
     * Item conversion code
     */
    abstract protected function processItem(Type $item): bool;

    /**
     * Get GetItem request parameters
     */
    protected function getItemRequest(): array
    {
        $request = [
            'ItemShape' => [
                // Reqest default set of properties
                'BaseShape' => 'Default',
                // Additional properties, e.g. LastModifiedTime
                // FIXME: How to add multiple properties here?
                'AdditionalProperties' => [
                    'FieldURI' => ['FieldURI' => 'item:LastModifiedTime'],
                ]
            ]
        ];

        return $request;
    }

    /**
     * Fetch attachment object from Exchange
     */
    protected function getAttachment(Type\FileAttachmentType $attachment)
    {
        $request = [
            'AttachmentIds' => [
                $attachment->getAttachmentId()->toXmlObject()
            ],
            'AttachmentShape' => [
                'IncludeMimeContent' => true,
            ]
        ];

        return $this->engine->api->getClient()->GetAttachment($request);
    }

    /**
     * Get Item UID (Generate a new one if needed)
     */
    protected function getUID(Type $item): string
    {
        if ($this->uid === null) {
            // We should generate an UID for objects that do not have it
            // and inject it into the output file
            // FIXME: Should we use e.g. md5($itemId->getId()) instead?
            $this->uid = \App\Utils::uuidStr();
        }

        return $this->uid;
    }
}
