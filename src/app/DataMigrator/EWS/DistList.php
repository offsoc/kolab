<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Distribution List object handler
 */
class DistList extends Item
{
    public const FOLDER_TYPE = 'IPF.Contact';
    public const TYPE        = 'IPM.DistList';
    public const FILE_EXT    = 'vcf';

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

        // TODO: The group description property in Exchange is not available via EWS XML,
        // at least not at outlook.office.com (Exchange 2010). It is available in the
        // MimeContent which is in email message format.

        // TODO: mailto: members are not supported by Kolab Webclient
        // We need to use MEMBER:urn:uuid:9bd97510-9dbb-4810-a144-6180962df5e0 syntax
        // But do not forget lists can have members that are not contacts

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

                if ($mailto) {
                    if ($name && $name != $mailto) {
                        $mailto = urlencode(sprintf('"%s" <%s>', addcslashes($name, '"'), $mailto));
                    }

                    $vcard .= $this->formatProp('MEMBER', "mailto:{$mailto}");
                }
            }
        }

        $vcard .= "END:VCARD\r\n";

        return $vcard;
    }
}
