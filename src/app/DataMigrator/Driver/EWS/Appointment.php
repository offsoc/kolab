<?php

namespace App\DataMigrator\Driver\EWS;

use garethp\ews\API\Type;

/**
 * Appointment (calendar event) object handler
 */
class Appointment extends Item
{
    public const FOLDER_TYPE = 'IPF.Appointment';
    // public const TYPE        = 'IPM.Appointment';
    public const FILE_EXT = 'ics';

    /**
     * Get GetItem request parameters
     */
    protected static function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // Request IncludeMimeContent as it's not included by default
        $request['ItemShape']['IncludeMimeContent'] = true;

        // Get UID property, it's not included in the Default set
        $request['ItemShape']['AdditionalProperties']['FieldURI'][] = ['FieldURI' => 'calendar:UID'];

        return $request;
    }

    /**
     * Process event object
     */
    protected function convertItem(Type $item, $targetItem)
    {
        // Initialize $this->itemId (for some unit tests)
        $this->getUID($item);

        // Decode MIME content
        $ical = base64_decode((string) $item->getMimeContent());

        $itemId = implode("\r\n ", str_split($this->itemId, 75 - strlen('X-MS-ID:')));

        $ical = preg_replace('/\r\nBEGIN:VEVENT\r\n/', "\r\nBEGIN:VEVENT\r\nX-MS-ID:{$itemId}\r\n", $ical, 1);

        // TODO: replace source email with destination email address in ORGANIZER/ATTENDEE

        // Inject attachment bodies into the iCalendar content
        // Calendar event attachments are exported as:
        // ATTACH:CID:81490FBA13A3DC2BF071B894C96B44BA51BEAAED@eurprd05.prod.outlook.com
        if ($item->getHasAttachments()) {
            // FIXME: I've tried hard and no matter what ContentId property is always empty
            //        This means we can't match the CID from iCalendar with the attachment.
            //        That's why we'll just remove all ATTACH:CID:... occurrences
            //        and inject attachments to the main event
            $ical = preg_replace('/\r\nATTACH:CID:[^\r]+\r\n(\r\n [^\r\n]*)?/', '', $ical);
            // We seem to get some weird ATTACH parts as part of ORGANIZER sometimes.
            // Looks like this (when printing $ical to console):
            // ORGANIZER;CN="Doe, John":MAILTO:John.Doe@example.comATTACH:CID:72ACF2D192043D418FD86B8@example.com
            // DESCRIPTION;LANGUAGE=de-DE:@ ...
            //
            // FIXME: Investigate the previous preg-replace again, to make sure that doesn't introduce the problem.
            // It might be if there are two consecutive ATTACH:CID properties?
            $ical = preg_replace('/ATTACH:CID:[^\r]+\r\n/', "\r\n", $ical);

            foreach ((array) $item->getAttachments()->getFileAttachment() as $attachment) {
                $_attachment = $this->getAttachment($attachment);

                $ctype = $_attachment->getContentType();
                $body = $_attachment->getContent();
                $name = $_attachment->getName();

                // It looks like Exchange may have an issue with plain text files.
                // We'll skip empty files
                if (!strlen($body)) {
                    continue;
                }

                // FIXME: This is imo inconsistence on php-ews side that MimeContent
                //        is base64 encoded, but Content isn't
                // TODO: We should not do it in memory to not exceed the memory limit
                $body = base64_encode($body);
                $body = rtrim(chunk_split($body, 74, "\r\n "), ' ');

                // Inject the attachment at the end of the first VEVENT block
                // TODO: We should not do it in memory to not exceed the memory limit
                $append = "ATTACH;VALUE=BINARY;ENCODING=BASE64;X-LABEL={$name};FMTTYPE={$ctype}:\r\n {$body}";
                $pos = strpos($ical, "\r\nEND:VEVENT");
                $ical = substr_replace($ical, $append, $pos + 2, 0);
            }
        }

        return $ical;
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
