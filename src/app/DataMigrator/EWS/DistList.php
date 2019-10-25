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
    protected function processItem(Type &$item): bool
    {
        // Groups (Distribution Lists) are not exported in vCard format, they use eml

        $data = [
            "UID" => $this->getUID($item),
            "KIND" => "group",
            "FN" => $item->getDisplayName(),
            "REV;VALUE=DATE-TIME" => $item->getLastModifiedTime(),
        ];

        $vcard = "BEGIN:VCARD\r\nVERSION:4.0\r\nPRODID:Kolab EWS DataMigrator\r\n";

        foreach ($data as $key => $value) {
            // TODO: value wrapping/escaping
            $vcard .= "{$key}:{$value}\r\n";
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

                $vcard .= "MEMBER:mailto:{$mailto}\r\n";
            }
        }

        $vcard .= "END:VCARD";

        // TODO: Maybe find less-hacky way
        $item->setMimeContent((new Type\MimeContentType)->set('_', $vcard));

        return true;
    }
}
