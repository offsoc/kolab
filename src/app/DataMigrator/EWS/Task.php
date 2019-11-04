<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Task objects handler
 */
class Task extends Item
{
    const FOLDER_TYPE = 'IPF.Task';
    const TYPE        = 'IPM.Task';
    const FILE_EXT    = 'ics';

    /**
     * Get GetItem request parameters
     */
    protected function getItemRequest(): array
    {
        $request = parent::getItemRequest();

        // For tasks we need all properties
        $request['ItemShape']['BaseShape'] = 'AllProperties';

        return $request;
    }

    /**
     * Process task object
     */
    protected function processItem(Type $item): bool
    {
        // Tasks are exported as Email messages in useless format
        // (does not contain all relevant properties)
        // We'll build the iCalendar using properties directly
        // TODO: This probably should be done with sabre-vobject

        $data = [
            "UID" => [$this->getUID($item)],
            "DTSTAMP" => [$item->getLastModifiedTime(), ['VALUE' => 'DATE-TIME']],
            "CREATED" => [$item->getDateTimeCreated(), ['VALUE' => 'DATE-TIME']],
            "SEQUENCE" => ['0'], // TODO
            "SUMMARY" => [$item->getSubject()],
            "DESCRIPTION" => [(string) $item->getBody()],
            "PERCENT-COMPLETE" => [intval($item->getPercentComplete())],
            "STATUS" => [strtoupper($item->getStatus())],
        ];

        if ($dueDate = $item->getDueDate()) {
            $data["DUE"] = [$dueDate, ['VALUE' => 'DATE-TIME']];
        }

        if ($startDate = $item->getStartDate()) {
            $data["DTSTART"] = [$startDate, ['VALUE' => 'DATE-TIME']];
        }

        if (($categories = $item->getCategories()) && $categories->String) {
            $data["CATEGORIES"] = [$categories->String];
        }

        if ($sensitivity = $item->getSensitivity()) {
            $sensitivity_map = [
                'CONFIDENTIAL' => 'CONFIDENTIAL',
                'NORMAL' => 'PUBLIC',
                'PERSONAL' => 'PUBLIC',
                'PRIVATE' => 'PRIVATE',
            ];

            $data['CLASS'] = [$sensitivity_map[strtoupper($sensitivity)] ?: 'PUBLIC'];
        }

        // TODO: VTIMEZONE, VALARM, ORGANIZER, ATTENDEE, RRULE,
        // TODO: PRIORITY (Importance) - not used by Kolab
        // ReminderDueBy, ReminderIsSet, IsRecurring, Owner, Recurrence

        $ical = "BEGIN:VCALENDAR\r\nMETHOD:PUBLISH\r\nVERSION:2.0\r\nPRODID:Kolab EWS DataMigrator\r\nBEGIN:VTODO\r\n";

        foreach ($data as $key => $prop) {
            $ical .= $this->formatProp($key, $prop[0], isset($prop[1]) ? $prop[1] : []);
        }

        // Attachments
        if ($item->getHasAttachments()) {
            foreach ((array) $item->getAttachments()->getFileAttachment() as $attachment) {
                $_attachment = $this->getAttachment($attachment);

                $ctype = $_attachment->getContentType();
                $body = $_attachment->getContent();

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

                // Inject the attachment at the end of the VTODO block
                // TODO: We should not do it in memory to not exceed the memory limit
                $ical .= "ATTACH;VALUE=BINARY;ENCODING=BASE64;FMTTYPE={$ctype}:\r\n {$body}";
            }
        }

        $ical .= "END:VEVENT\r\n";
        $ical .= "END:VCALENDAR\r\n";

        // TODO: Maybe find less-hacky way
        $item->setMimeContent((new Type\MimeContentType)->set('_', $ical));

        return true;
    }
}
