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

    /** @var string Current item ID */
    protected $itemId;

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
    public static function factory(EWS $engine, string $item_class, array $folder)
    {
        $item_class = str_replace('IPM.', '', $item_class);
        $item_class = "\App\DataMigrator\EWS\\{$item_class}";

        if (class_exists($item_class)) {
            return new $item_class($engine, $folder);
        }
    }

    /**
     * Fetch the specified object and put into a file
     */
    public function fetchItem(array $itemId)
    {
        // Fetch the item
        $item = $this->engine->api->getItem($itemId, $this->getItemRequest());

        $this->itemId = implode('!', $itemId);

        $uid = $this->getUID($item);

        $this->engine->debug("Saving item {$uid}...");

        // Apply type-specific format converters
        $content = $this->processItem($item);

        if (!is_string($content)) {
            return;
        }

        $uid = preg_replace('/[^a-zA-Z0-9_:@-]/', '', $uid);

        $location = $this->folder['location'];

        if (!file_exists($location)) {
            mkdir($location, 0740, true);
        }

        $location .= '/' . $uid . '.' . $this->fileExtension();

        file_put_contents($location, $content);

        return $location;
    }

    /**
     * Item conversion code
     */
    abstract protected function processItem(Type $item);

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

    /**
     * Filename extension for cached file in-processing
     */
    protected function fileExtension(): string
    {
        return constant(static::class . '::FILE_EXT') ?: 'txt';
    }

    /**
     * VCard/iCal property formatting
     */
    protected function formatProp($name, $value, array $params = []): string
    {
        $cal = new \Sabre\VObject\Component\VCalendar();
        $prop = new \Sabre\VObject\Property\Text($cal, $name, $value, $params);

        $value = $prop->serialize();

        // Revert escaping for some props
        if ($name == 'RRULE') {
            $value = str_replace("\\", '', $value);
        }

        return $value;
    }
}
