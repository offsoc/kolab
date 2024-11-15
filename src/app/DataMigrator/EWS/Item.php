<?php

namespace App\DataMigrator\EWS;

use App\DataMigrator\EWS;
use App\DataMigrator\Engine;
use App\DataMigrator\Interface\Folder as FolderInterface;
use App\DataMigrator\Interface\Item as ItemInterface;
use App\DataMigrator\Interface\ItemSet as ItemSetInterface;
use garethp\ews\API;
use garethp\ews\API\Type;

/**
 * Abstraction for object handlers
 */
abstract class Item
{
    /** @var EWS Data migrator object */
    protected $driver;

    /** @var FolderInterface Current folder */
    protected $folder;

    /** @var string Current item ID */
    protected $itemId;

    /** @var string Current item UID */
    protected $uid;


    /**
     * Object constructor
     */
    public function __construct(EWS $driver, FolderInterface $folder)
    {
        $this->driver = $driver;
        $this->folder = $folder;
    }

    /**
     * Lookup item handler by class
     *
     * Since classes are hierarchical it's possible to have more or less specific handlers, e.g.:
     * * IPM.Note
     * * IPM.Note.SMIME.MultipartSigned
     *
     * We fold reports into the same hierarchy, such as:
     * * REPORT.IPM.Note.NDR
     *
     * See also:
     * * https://learn.microsoft.com/en-us/openspecs/exchange_server_protocols/ms-asemail/51d84da6-a2da-41e9-8ca7-eb6c4e72c28d
     * * https://learn.microsoft.com/en-us/office/vba/outlook/concepts/forms/item-types-and-message-classes
     */
    private static function lookupClassHandler(string $classname)
    {
        $itemClass = str_replace('REPORT.IPM.', '', $classname);
        $itemClass = str_replace('IPM.', '', $itemClass);
        $classParts = explode('.', $itemClass);

        // Find the most specific handler
        while (!empty($classParts)) {
            $itemClass = implode('', $classParts);
            $itemClass = "\App\DataMigrator\EWS\\{$itemClass}";
            if (class_exists($itemClass)) {
                return $itemClass;
            }
            array_pop($classParts);
        }
        return null;
    }

    /**
     * Factory method.
     * Returns object suitable to handle specified item type.
     */
    public static function factory(EWS $driver, ItemInterface $item)
    {
        if ($itemClass = self::lookupClassHandler($item->class)) {
            return new $itemClass($driver, $item->folder);
        } else {
            \Log::warning("Encountered unhandled item class {$item->class} ");
        }
    }

    /**
     * Validate that specified EWS Item is of supported type
     */
    public static function isValidItem(Type $item): bool
    {
        return self::lookupClassHandler($item->getItemClass()) != null;
    }

    /**
     * Process an item (fetch data and convert it)
     */
    public function processItem(ItemInterface $item): void
    {
        $itemId = ['Id' => $item->id];

        \Log::debug("[EWS] Fetching item {$item->id} of class {$item->class}...");

        // Fetch the item
        $ewsItem = $this->driver->api->getItem($itemId, $this->getItemRequest());

        $uid = $this->getUID($ewsItem);

        \Log::debug("[EWS] Saving item {$uid}...");

        // Apply type-specific format converters
        $content = $this->convertItem($ewsItem, $item);

        if (!is_string($content)) {
            throw new \Exception("Failed to fetch EWS item {$this->itemId}");
        }

        $filename = $uid . '.' . $this->fileExtension();

        if (strlen($content) > Engine::MAX_ITEM_SIZE) {
            $location = $this->folder->tempFileLocation($filename);

            if (file_put_contents($location, $content) === false) {
                throw new \Exception("Failed to write to file at {$location}");
            }

            $item->filename = $location;
        } else {
            $item->content = $content;
            $item->filename = $filename;
        }
    }

    /**
     * Item conversion code
     */
    abstract protected function convertItem(Type $item, $targetItem);

    /**
     * Get GetItem request parameters
     */
    protected static function getItemRequest(): array
    {
        $request = [
            'ItemShape' => [
                // Reqest default set of properties
                'BaseShape' => 'Default',
                // Additional properties, e.g. LastModifiedTime
                'AdditionalProperties' => [
                    'FieldURI' => [
                        ['FieldURI' => 'item:LastModifiedTime'],
                    ],
                ],
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

        return $this->driver->api->getClient()->GetAttachment($request);
    }

    /**
     * Get Item UID (Generate a new one if needed)
     */
    protected function getUID(Type $item): string
    {
        $itemId = $item->getItemId()->toArray();

        if ($this->uid === null) {
            // Tasks, contacts, distlists do not have an UID. We have to generate one
            // and inject it into the output file.
            // We'll use the ItemId (excluding the ChangeKey part) as a base for the UID,
            // this way we can e.g. get distlist members references working.
            $this->uid = sha1($itemId['Id']);
            // $this->uid = \App\Utils::uuidStr();
        }

        $this->itemId = implode('!', $itemId);

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
