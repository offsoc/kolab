<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * E-Mail object handler
 */
class Note extends Item
{
    public const FOLDER_TYPE = 'IPF.Note';
    public const TYPE        = 'IPM.Note';
    public const FILE_EXT    = 'eml';

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
    protected function convertItem(Type $item)
    {
        $email = base64_decode((string) $item->getMimeContent());

        return $email;
    }
}
