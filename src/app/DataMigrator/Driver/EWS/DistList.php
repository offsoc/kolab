<?php

namespace App\DataMigrator\Driver\EWS;

use garethp\ews\API\Type;

/**
 * Distribution List object handler
 */
class DistList extends Item
{
    public const FOLDER_TYPE = 'IPF.Contact';
    // public const TYPE        = 'IPM.DistList';
    public const FILE_EXT    = 'vcf';

    /**
     * Get GetItem request parameters
     */
    protected static function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // Get Body property, it's not included in the Default set
        $request['ItemShape']['AdditionalProperties']['FieldURI'] = ['FieldURI' => 'item:Body'];

        return $request;
    }

    /**
     * Convert distribution list object to vCard
     */
    protected function convertItem(Type $item, $targetItem)
    {
        // Groups (Distribution Lists) are not exported in vCard format, they use eml

        $data = [
            'UID' => [$this->getUID($item)],
            'KIND' => ['group'],
            'FN' => [$item->getDisplayName()],
            'REV' => [$item->getLastModifiedTime(), ['VALUE' => 'DATE-TIME']],
            'X-MS-ID' => [$this->itemId],
        ];

        $vcard = "BEGIN:VCARD\r\nVERSION:4.0\r\nPRODID:Kolab EWS Data Migrator\r\n";

        foreach ($data as $key => $prop) {
            $vcard .= $this->formatProp($key, $prop[0], isset($prop[1]) ? $prop[1] : []);
        }

        // Process list members
        if ($members = $item->getMembers()) {
            // The Member property is either array (multiple members) or Type\MemberType
            // object (a group with just a one member).
            if (!is_array($members->Member)) {
                $members->Member = [$members->Member];
            }

            foreach ($members->Member as $member) {
                $mailbox = $member->getMailbox();
                $mailto = $mailbox->getEmailAddress();
                $name = $mailbox->getName();
                $id = $mailbox->getItemId();

                // "mailto:" members are not fully supported by Kolab Webclient.
                // For members that are contacts (have ItemId specified) we use urn:uuid:<UID>
                // syntax that has good support.

                if ($id) {
                    $contactUID = sha1($id->toArray()['Id']);
                    $vcard .= $this->formatProp('MEMBER', "urn:uuid:{$contactUID}");
                } elseif ($mailto) {
                    if ($name && $name != $mailto) {
                        $mailto = urlencode(sprintf('"%s" <%s>', addcslashes($name, '"'), $mailto));
                    }

                    $vcard .= $this->formatProp('MEMBER', "mailto:{$mailto}");
                }
            }
        }

        // Note: Kolab Webclient does not make any use of the NOTE property for contact groups
        if ($body = (string) $item->getBody()) {
            $vcard .= $this->formatProp('NOTE', $body);
        }

        $vcard .= "END:VCARD\r\n";

        return $vcard;
    }
}
