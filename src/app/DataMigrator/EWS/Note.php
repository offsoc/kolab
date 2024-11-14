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
    protected function convertItem(Type $item, $targetItem)
    {
        $email = base64_decode((string) $item->getMimeContent());

        $flags = [];
        if ($item->getIsRead()) {
            $flags[] = 'SEEN';
        }

        //low/normal/high are exist
        if (strtolower($item->getImportance()) == "high") {
            $flags[] = 'FLAGGED';
        }

        // Other things to potentially migrate (but we don't currently have that in imap)
        // * Sensitivity: normal/company-confidential/confidential/private

        $internaldate = null;
        if ($internaldate = $item->getDateTimeReceived()) {
            $internaldate = (new \DateTime($internaldate))->format('d-M-Y H:i:s O');
        }

        $targetItem->data = [
            'flags' => $flags,
            'internaldate' => $internaldate,
        ];

        return $email;
    }
}
