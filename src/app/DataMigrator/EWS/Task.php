<?php

namespace App\DataMigrator\EWS;

use garethp\ews\API\Type;

/**
 * Task objects handler
 */
class Task extends Item
{
    public const FOLDER_TYPE = 'IPF.Task';
    public const TYPE        = 'IPM.Task';
    public const FILE_EXT    = 'ics';

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
    protected function processItem(Type $item)
    {
        // Tasks are exported as Email messages in useless format
        // (does not contain all relevant properties)
        // We'll build the iCalendar using properties directly
        // TODO: This probably should be done with sabre-vobject

        // FIXME: Looks like tasks do not have timezone specified in EWS
        //        and dates are in UTC, shall we remove the 'Z' from dates to make them floating?

        $data = [
            'UID' => [$this->getUID($item)],
            'DTSTAMP' => [$this->formatDate($item->getLastModifiedTime()), ['VALUE' => 'DATE-TIME']],
            'CREATED' => [$this->formatDate($item->getDateTimeCreated()), ['VALUE' => 'DATE-TIME']],
            'SEQUENCE' => [intval($item->getChangeCount())],
            'SUMMARY' => [$item->getSubject()],
            'DESCRIPTION' => [(string) $item->getBody()],
            'PERCENT-COMPLETE' => [intval($item->getPercentComplete())],
            'STATUS' => [strtoupper($item->getStatus())],
            'X-MS-ID' => [$this->itemId],
        ];

        if ($dueDate = $item->getDueDate()) {
            $data['DUE'] = [$this->formatDate($dueDate), ['VALUE' => 'DATE-TIME']];
        }

        if ($startDate = $item->getStartDate()) {
            $data['DTSTART'] = [$this->formatDate($startDate), ['VALUE' => 'DATE-TIME']];
        }

        if (($categories = $item->getCategories()) && $categories->String) {
            $data['CATEGORIES'] = [$categories->String];
        }

        if ($sensitivity = $item->getSensitivity()) {
            $sensitivity_map = [
                'CONFIDENTIAL' => 'CONFIDENTIAL',
                'NORMAL' => 'PUBLIC',
                'PERSONAL' => 'PUBLIC',
                'PRIVATE' => 'PRIVATE',
            ];

            $data['CLASS'] = [$sensitivity_map[strtoupper($sensitivity)] ?? 'PUBLIC'];
        }

        if ($importance = $item->getImportance()) {
            $importance_map = [
                'HIGH' => '9',
                'NORMAL' => '5',
                'LOW' => '1',
            ];

            $data['PRIORITY'] = [$importance_map[strtoupper($importance)] ?? '0'];
        }

        $this->setTaskOrganizer($data, $item);
        $this->setTaskRecurrence($data, $item);

        $ical = "BEGIN:VCALENDAR\r\nMETHOD:PUBLISH\r\nVERSION:2.0\r\nPRODID:Kolab EWS Data Migrator\r\nBEGIN:VTODO\r\n";

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

        $ical .= $this->getVAlarm($item);
        $ical .= "END:VTODO\r\n";
        $ical .= "END:VCALENDAR\r\n";

        return $ical;
    }

    /**
     * Set task organizer/attendee
     */
    protected function setTaskOrganizer(array &$data, Type $task)
    {
        // FIXME: Looks like the owner might be an email address or just a full user name
        $owner = $task->getOwner();
        $source = $this->driver->getSourceAccount();
        $destination = $this->driver->getDestinationAccount();

        if (strpos($owner, '@') && $owner != $source->email) {
            // Task owned by another person
            $data['ORGANIZER'] = ["mailto:{$owner}"];

            // FIXME: Because attendees are not specified in EWS, assume the user is an attendee
            if ($destination->email) {
                $params = ['ROLE' => 'REQ-PARTICIPANT', 'CUTYPE' => 'INDIVIDUAL'];
                $data['ATTENDEE'] = ["mailto:{$destination->email}", $params];
            }

            return;
        }

        // Otherwise it must be owned by the user
        if ($destination->email) {
            $data['ORGANIZER'] = ["mailto:{$destination->email}"];
        }
    }

    /**
     * Set task recurrence rule
     */
    protected function setTaskRecurrence(array &$data, Type $task)
    {
        if (empty($task->getIsRecurring()) || empty($task->getRecurrence())) {
            return;
        }

        $r = $task->getRecurrence();
        $rrule = [];

        if ($recurrence = $r->getDailyRecurrence()) {
            $rrule['FREQ'] = 'DAILY';
            $rrule['INTERVAL'] = $recurrence->getInterval() ?: 1;
        } elseif ($recurrence = $r->getWeeklyRecurrence()) {
            $rrule['FREQ'] = 'WEEKLY';
            $rrule['INTERVAL'] = $recurrence->getInterval() ?: 1;
            $rrule['BYDAY'] = $this->mapDays($recurrence->getDaysOfWeek());
            $rrule['WKST'] = $this->mapDays($recurrence->getFirstDayOfWeek());
        } elseif ($recurrence = $r->getAbsoluteMonthlyRecurrence()) {
            $rrule['FREQ'] = 'MONTHLY';
            $rrule['INTERVAL'] = $recurrence->getInterval() ?: 1;
            $rrule['BYMONTHDAY'] = $recurrence->getDayOfMonth();
        } elseif ($recurrence = $r->getRelativeMonthlyRecurrence()) {
            $rrule['FREQ'] = 'MONTHLY';
            $rrule['INTERVAL'] = $recurrence->getInterval() ?: 1;
            $rrule['BYDAY'] = $this->mapDays($recurrence->getDaysOfWeek(), $recurrence->getDayOfWeekIndex());
        } elseif ($recurrence = $r->getAbsoluteYearlyRecurrence()) {
            $rrule['FREQ'] = 'YEARLY';
            $rrule['BYMONTH'] = $this->mapMonths($recurrence->getMonth());
            $rrule['BYMONTHDAY'] = $recurrence->getDayOfMonth();
        } elseif ($recurrence = $r->getRelativeYearlyRecurrence()) {
            $rrule['FREQ'] = 'YEARLY';
            $rrule['BYMONTH'] = $this->mapMonths($recurrence->getMonth());
            $rrule['BYDAY'] = $this->mapDays($recurrence->getDaysOfWeek(), $recurrence->getDayOfWeekIndex());
        } else {
            // There might be *Regeneration rules that we don't support
            \Log::debug("[EWS] Unsupported Recurrence property value. Ignored.");
        }

        if (!empty($rrule)) {
            if ($recurrence = $r->getNumberedRecurrence()) {
                $rrule['COUNT'] = $recurrence->getNumberOfOccurrences();
            } elseif ($recurrence = $r->getEndDateRecurrence()) {
                $rrule['UNTIL'] = $this->formatDate($recurrence->getEndDate());
            }

            $rrule = array_filter($rrule);
            $rrule = trim(array_reduce(
                array_keys($rrule),
                function ($carry, $key) use ($rrule) {
                    return $carry . ';' . $key . '=' . $rrule[$key];
                }
            ), ';');

            $data['RRULE'] = [$rrule];
        }
    }

    /**
     * Get VALARM block for the task Reminder
     */
    protected function getVAlarm(Type $task): string
    {
        // FIXME: To me it looks like ReminderMinutesBeforeStart property is not used

        $date = $this->formatDate($task->getReminderDueBy());

        if (empty($task->getReminderIsSet()) || empty($date)) {
            return '';
        }

        return "BEGIN:VALARM\r\nACTION:DISPLAY\r\n"
            . "TRIGGER;VALUE=DATE-TIME:{$date}\r\n"
            . "END:VALARM\r\n";
    }

    /**
     * Convert EWS representation of recurrence days to iCal
     */
    protected function mapDays(string $days, string $index = ''): string
    {
        if (preg_match('/(Day|Weekday|WeekendDay)/', $days)) {
            // not supported
            return '';
        }

        $days_map = [
            'Sunday' => 'SU',
            'Monday' => 'MO',
            'Tuesday' => 'TU',
            'Wednesday' => 'WE',
            'Thursday' => 'TH',
            'Friday' => 'FR',
            'Saturday' => 'SA',
        ];

        $index_map = [
            'First' => 1,
            'Second' => 2,
            'Third' => 3,
            'Fourth' => 4,
            'Last' => -1,
        ];

        $days = explode(' ', $days);
        $days = array_map(
            function ($day) use ($days_map, $index_map, $index) {
                return ($index ? $index_map[$index] : '') . $days_map[$day];
            },
            $days
        );

        return implode(',', $days);
    }

    /**
     * Convert EWS representation of recurrence month to iCal
     */
    protected function mapMonths(string $months): string
    {
        $months_map = ['January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'];

        $months = explode(' ', $months);
        $months = array_map(
            function ($month) use ($months_map) {
                return array_search($month, $months_map) + 1;
            },
            $months
        );

        return implode(',', $months);
    }

    /**
     * Format EWS date-time into a iCalendar date-time
     */
    protected function formatDate($datetime)
    {
        if (empty($datetime)) {
            return null;
        }

        return str_replace(['Z', '-', ':'], '', $datetime);
    }
}
