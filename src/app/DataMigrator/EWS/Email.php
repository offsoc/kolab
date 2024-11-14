<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Email object handler
 */
class Email extends Item
{
    public const FOLDER_TYPE = 'IPF.Note';
    // public const TYPE        = 'IPM.Email';
    public const FILE_EXT    = 'mime';


    /**
     * Get GetItem request parameters
     */
    protected static function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // Request IncludeMimeContent as it's not included by default
        $request['ItemShape']['IncludeMimeContent'] = true;

        // Get UID property, it's not included in the Default set
        // $request['ItemShape']['AdditionalProperties']['FieldURI'][] = ['FieldURI' => 'calendar:UID'];

        return $request;
    }

    /**
     * Process event object
     */
    protected function convertItem(Type $item, $targetItem)
    {
        // This is not actually called, emails are migrate with type Note, because of the FOLDER_TYPE
        return null;
    }

    /**
     * Get Item UID (Generate a new one if needed)
     */
    protected function getUID(Type $item): string
    {
        // Only appointments have UID property
        $this->uid = $item->getUID();

        // This also sets $this->itemId;
        return parent::getUID($item);
    }
}
