<?php

namespace App\Policy\Mailfilter\Notifications;

use App\User;
use Sabre\VObject\Component;

class ItipNotificationParams
{
    /** @var ?string $comment iTip COMMENT property */
    public ?string $comment;

    /** @var ?string $mode Notification mode (iTip method) */
    public ?string $mode;

    /** @var ?string $partstat Attendee PARTSTAT property in an iTip REPLY */
    public ?string $partstat;

    /** @var ?string $recurrenceId Recurrence identifier of an event/task occurence */
    public ?string $recurrenceId;

    /** @var ?string $summary iTip sender (attendee or organizer) email address */
    public ?string $senderEmail;

    /** @var ?string $summary iTip sender (attendee or organizer) name */
    public ?string $senderName;

    /** @var ?string $start Event/task start date or date-time */
    public ?string $start;

    /** @var ?string $summary Event/Task summary */
    public ?string $summary;

    /** @var ?User $user The recipient of the notification */
    public ?User $user;

    /**
     * Object constructor
     *
     * @param string     $mode   Notification mode (request, cancel, reply)
     * @param ?Component $object Event/task object
     */
    public function __construct(string $mode, ?Component $object = null)
    {
        $this->mode = $mode;

        if ($object) {
            $this->recurrenceId = (string) $object->{'RECURRENCE-ID'};
            $this->summary = (string) $object->SUMMARY;
            // TODO: Format date-time according to the user locale
            $this->start = $object->DTSTART->getDateTime()->format($object->DTSTART->hasTime() ? 'Y-m-d H:i' : 'Y-m-d');

            if (empty($this->summary) && !empty($this->recurrenceId)) {
                // TODO: Get the 'summary' from the master event
            }
        }
    }
}
