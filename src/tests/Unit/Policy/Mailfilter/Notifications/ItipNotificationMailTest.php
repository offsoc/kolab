<?php

namespace Tests\Unit\Policy\Mailfilter\Notifications;

use App\Policy\Mailfilter\Notifications\ItipNotificationMail;
use App\Policy\Mailfilter\Notifications\ItipNotificationParams;
use App\User;
use Tests\TestCase;

class ItipNotificationMailTest extends TestCase
{
    /**
     * Test CANCEL notification
     */
    public function testCancel(): void
    {
        $params = new ItipNotificationParams('cancel');
        $params->user = new User(['email' => 'john@kolab.org']);
        $params->senderEmail = 'jack@kolab.org';
        $params->senderName = 'Jack Strong';
        $params->summary = 'Test Meeting';
        $params->start = '2024-01-01';
        $params->comment = 'Attendee comment';
        $params->recurrenceId = '2024-01-01';

        $mail = $this->renderMail(new ItipNotificationMail($params));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $expected = [
            "The event \"Test Meeting\" at 2024-01-01 has been canceled by the organizer.",
            "The copy in your calendar has been removed accordingly.",
            "Jack Strong provided comment: Attendee comment",
            "NOTE: This only refers to this single occurrence!",
            "*** This is an automated message. Please do not reply. ***",
        ];

        $this->assertSame("\"Test Meeting\" has been canceled", $mail['subject']);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        foreach ($expected as $line) {
            $this->assertStringContainsString($line, $html);
            $this->assertStringContainsString($line, $plain);
        }

        // TODO: Test with some properties unset
    }

    /**
     * Test REPLY notification
     */
    public function testReply(): void
    {
        $params = new ItipNotificationParams('reply');
        $params->user = new User(['email' => 'john@kolab.org']);
        $params->senderEmail = 'jack@kolab.org';
        $params->senderName = 'Jack Strong';
        $params->partstat = 'ACCEPTED';
        $params->summary = 'Test Meeting';
        $params->start = '2024-01-01';
        $params->comment = 'Attendee comment';
        $params->recurrenceId = '2024-01-01';

        $mail = $this->renderMail(new ItipNotificationMail($params));

        $html = $mail['html'];
        $plain = $mail['plain'];

        $expected = [
            "The event \"Test Meeting\" at 2024-01-01 has been updated in your calendar",
            "Jack Strong accepted the invitation.",
            "Jack Strong provided comment: Attendee comment",
            "NOTE: This only refers to this single occurrence!",
            "*** This is an automated message. Please do not reply. ***",
        ];

        $this->assertSame("\"Test Meeting\" has been updated", $mail['subject']);
        $this->assertStringStartsWith('<!DOCTYPE html>', $html);
        foreach ($expected as $line) {
            $this->assertStringContainsString($line, $html);
            $this->assertStringContainsString($line, $plain);
        }

        // TODO: Test with some properties unset
    }
}
