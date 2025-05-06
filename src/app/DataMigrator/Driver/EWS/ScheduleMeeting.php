<?php

namespace App\DataMigrator\Driver\EWS;

use garethp\ews\API\Type;

/**
 * Schedule Meeting object handler
 */
class ScheduleMeeting extends Item
{
    public const FOLDER_TYPE = 'IPF.Note';
    // public const TYPE        = 'IPM.Schedule.Meeting.Request';
    // public const TYPE        = 'IPM.Schedule.Meeting.Canceled';
    public const FILE_EXT = 'eml';

    /**
     * Get GetItem request parameters
     */
    protected static function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // Request IncludeMimeContent as it's not included by default
        $request['ItemShape']['IncludeMimeContent'] = true;

        // For email we need all properties
        $request['ItemShape']['BaseShape'] = 'AllProperties';

        return $request;
    }

    /**
     * Process contact object
     */
    protected function convertItem(Type $item, $targetItem)
    {
        return (new Note($this->driver, $this->folder))->convertItem($item, $targetItem);
    }
}
