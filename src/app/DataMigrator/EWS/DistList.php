<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Distribution List object handler
 */
class DistList extends Item
{
    const FOLDER_TYPE = 'IPF.Contact';
    const TYPE        = 'IPM.DistList';
    const FILE_EXT    = 'vcf';

    /**
     * Convert distribution list object to vCard
     */
    protected function processItem(Type $item)
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
        // Note: The fact that getMembers() returns stdClass is probably a bug in php-ews
        foreach ($item->getMembers()->Member as $member) {
            $mailbox = $member->getMailbox();
            $mailto = $mailbox->getEmailAddress();
            $name = $mailbox->getName();

            // FIXME: Investigate if mailto: members are handled properly by Kolab
            //        or we need to use MEMBER:urn:uuid:9bd97510-9dbb-4810-a144-6180962df5e0 syntax
            //        But do not forget lists can have members that are not contacts

            if ($mailto) {
                if ($name && $name != $mailto) {
                    $mailto = urlencode(sprintf('"%s" <%s>', addcslashes($name, '"'), $mailto));
                }

                $vcard .= $this->formatProp('MEMBER', "mailto:{$mailto}");
            }
        }

        $vcard .= "END:VCARD\r\n";

        return $vcard;
    }
}
