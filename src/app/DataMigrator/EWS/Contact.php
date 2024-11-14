<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Contact object handler
 */
class Contact extends Item
{
    public const FOLDER_TYPE = 'IPF.Contact';
    // public const TYPE        = 'IPM.Contact';
    public const FILE_EXT    = 'vcf';

    /**
     * Get GetItem request parameters
     */
    protected static function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // Request IncludeMimeContent as it's not included by default
        $request['ItemShape']['IncludeMimeContent'] = true;

        return $request;
    }

    /**
     * Process contact object
     */
    protected function convertItem(Type $item, $targetItem)
    {
        // Decode MIME content
        $vcard = base64_decode((string) $item->getMimeContent());

        // Remove empty properties that EWS is exporting
        $vcard = preg_replace('|\n[^:]+:;*\r|', '', $vcard);

        // Inject UID (and Exchange item ID) to the vCard
        $uid = $this->getUID($item);
        $itemId = implode("\r\n ", str_split($this->itemId, 75 - strlen('X-MS-ID:')));

        // TODO: Use DAV\Vcard instead of string matching and replacement

        $vcard = str_replace("BEGIN:VCARD", "BEGIN:VCARD\r\nUID:{$uid}\r\nX-MS-ID:{$itemId}", $vcard);

        // Note: Looks like PHOTO property is exported properly, so we
        //       don't have to handle attachments as we do for calendar items

        // TODO: Use vCard v4 for anniversary and spouse? Roundcube works with what's below

        // Spouse: X-MS-SPOUSE;TYPE=N:Partner Name
        if (preg_match('/(X-MS-SPOUSE[;:][^\r\n]+)/', $vcard, $matches)) {
            $spouse = preg_replace('/^[^:]+:/', '', $matches[1]);
            $vcard = str_replace($matches[1], "X-SPOUSE:{$spouse}", $vcard);
        }

        // Anniversary: X-MS-ANNIVERSARY;VALUE=DATE:2020-11-12
        if (preg_match('/(X-MS-ANNIVERSARY[;:][^\r\n]+)/', $vcard, $matches)) {
            $date = preg_replace('/^[^:]+:/', '', $matches[1]);
            $vcard = str_replace($matches[1], "X-ANNIVERSARY:{$date}", $vcard);
        }

        // Exchange 2010 for some reason do not include email addresses in the vCard
        if (!preg_match('/\nEMAIL[^:]*:[^\r\n]+/', $vcard) && ($emailEntries = $item->getEmailAddresses())) {
            $emails = [];

            // Note that the Entry property is either an array (multiple addresses)
            // or an object (single address). Not a great API design.
            if (!is_array($emailEntries->Entry)) {
                $emailEntries->Entry = [$emailEntries->Entry];
            }

            foreach ($emailEntries->Entry as $email) {
                $emails[] = 'EMAIL;TYPE=internet:' . strval($email);
            }

            if ($emails) {
                $vcard = str_replace("BEGIN:VCARD\r\n", "BEGIN:VCARD\r\n" . implode("\r\n", $emails) . "\r\n", $vcard);
            }
        }

        return $vcard;
    }
}
