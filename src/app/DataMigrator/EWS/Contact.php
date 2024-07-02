<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Contact object handler
 */
class Contact extends Item
{
    public const FOLDER_TYPE = 'IPF.Contact';
    public const TYPE        = 'IPM.Contact';
    public const FILE_EXT    = 'vcf';

    /**
     * Get GetItem request parameters
     */
    protected function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // Request IncludeMimeContent as it's not included by default
        $request['ItemShape']['IncludeMimeContent'] = true;

        return $request;
    }

    /**
     * Process contact object
     */
    protected function processItem(Type $item)
    {
        // Decode MIME content
        $vcard = base64_decode((string) $item->getMimeContent());

        // Remove empty properties that EWS is exporting
        $vcard = preg_replace('|\n[^:]+:;*\r|', '', $vcard);

        // Inject UID (and Exchange item ID) to the vCard
        $uid = $this->getUID($item);
        $itemId = implode("\r\n ", str_split($this->itemId, 75 - strlen('X-MS-ID:')));

        $vcard = str_replace("BEGIN:VCARD", "BEGIN:VCARD\r\nUID:{$uid}\r\nX-MS-ID:{$itemId}", $vcard);

        // Note: Looks like PHOTO property is exported properly, so we
        //       don't have to handle attachments as we do for calendar items

        return $vcard;
    }
}
