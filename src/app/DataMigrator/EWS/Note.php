<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * E-Mail object handler
 */
class Note extends Item
{
    const FOLDER_TYPE = 'IPF.Note';
    const TYPE        = 'IPM.Note';
    const FILE_EXT    = 'eml';

    /**
     * Get GetItem request parameters
     */
    protected function getItemRequest(): array
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
    protected function processItem(Type $item): bool
    {
        $email = base64_decode((string) $item->getMimeContent());

        // TODO: Maybe find less-hacky way
        $item->getMimeContent()->_ = $email;

        return true;
    }
}
