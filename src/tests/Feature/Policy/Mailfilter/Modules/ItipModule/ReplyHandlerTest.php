<?php

namespace Tests\Feature\Policy\Mailfilter\Modules\ItipModule;

use App\DataMigrator\Account;
use App\Policy\Mailfilter\Modules\ItipModule;
use App\Policy\Mailfilter\Notifications\ItipNotification;
use App\Policy\Mailfilter\Result;
use Illuminate\Support\Facades\Notification;
use Tests\BackendsTrait;
use Tests\TestCase;
use Tests\Unit\Policy\Mailfilter\MailParserTest;

/**
 * @todo Mock the DAV server to make these tests faster
 */
class ReplyHandlerTest extends TestCase
{
    use BackendsTrait;

    /**
     * Test REPLY method
     *
     * @group @dav
     */
    public function testItipReply(): void
    {
        Notification::fake();

        $user = $this->getTestUser('jack@kolab.org');
        $uri = preg_replace('|^http|', 'dav', \config('services.dav.uri'));
        $account = new Account(preg_replace('|://|', '://jack%40kolab.org:simple123@', $uri));

        $this->davEmptyFolder($account, 'Calendar', 'event');

        // John's response to the invitation, but there's no event in Jack's (organizer's) calendar
        $parser = MailParserTest::getParserForFile('mailfilter/itip1_reply.eml', $user->email);
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertNull($result);

        Notification::assertNothingSent();

        // John's response to the invitation, the Jack's event exists now
        $this->davAppend($account, 'Calendar', ['mailfilter/event1.ics'], 'event');

        $parser = MailParserTest::getParserForFile('mailfilter/itip1_reply.eml', $user->email);
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertSame(Result::STATUS_DISCARD, $result->getStatus());

        $list = $this->davList($account, 'Calendar', 'event');
        $this->assertCount(1, $list);
        $this->assertSame('5463F1DDF6DA264A3FC70E7924B729A5-D9F1889254B163F5', $list[0]->uid);
        $this->assertCount(2, $attendees = $list[0]->attendees);
        $this->assertSame('john@kolab.org', $attendees[0]['email']);
        $this->assertSame('ACCEPTED', $attendees[0]['partstat']);
        $this->assertSame('ned@kolab.org', $attendees[1]['email']);
        $this->assertSame('NEEDS-ACTION', $attendees[1]['partstat']);
        $this->assertSame('jack@kolab.org', $list[0]->organizer['email']);

        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            function (ItipNotification $notification, array $channels, object $notifiable) use ($user) {
                return $notifiable->id == $user->id
                    && $notification->params->mode == 'reply'
                    && $notification->params->senderEmail == 'john@kolab.org'
                    && $notification->params->senderName == 'John'
                    && $notification->params->comment == 'a reply from John'
                    && $notification->params->partstat == 'ACCEPTED'
                    && $notification->params->start == '2024-07-10 10:30'
                    && $notification->params->summary == 'Test Meeting'
                    && empty($notification->params->recurrenceId);
            }
        );

        // TODO: Test corner cases, spoofing case, etc.
    }

    /**
     * Test REPLY method with recurrence
     *
     * @group @dav
     */
    public function testItipReplyRecurrence(): void
    {
        Notification::fake();

        $user = $this->getTestUser('jack@kolab.org');
        $uri = preg_replace('|^http|', 'dav', \config('services.dav.uri'));
        $account = new Account(preg_replace('|://|', '://jack%40kolab.org:simple123@', $uri));

        $this->davEmptyFolder($account, 'Calendar', 'event');
        $this->davAppend($account, 'Calendar', ['mailfilter/event3.ics'], 'event');

        // John's response to the invitation, but there's no exception in Jack's event
        $parser = MailParserTest::getParserForFile('mailfilter/itip2_reply.eml', 'jack@kolab.org');
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertNull($result);

        Notification::assertNothingSent();

        $list = $this->davList($account, 'Calendar', 'event');
        $this->assertCount(1, $list);
        $this->assertSame('5463F1DDF6DA264A3FC70E7924B729A5-222222', $list[0]->uid);
        $this->assertCount(2, $attendees = $list[0]->attendees);
        $this->assertSame('john@kolab.org', $attendees[0]['email']);
        $this->assertSame('ACCEPTED', $attendees[0]['partstat']);
        $this->assertSame('ned@kolab.org', $attendees[1]['email']);
        $this->assertSame('TENTATIVE', $attendees[1]['partstat']);
        $this->assertSame('jack@kolab.org', $list[0]->organizer['email']);
        $this->assertCount(0, $list[0]->exceptions);

        $this->davEmptyFolder($account, 'Calendar', 'event');
        $this->davAppend($account, 'Calendar', ['mailfilter/event4.ics'], 'event');

        // John's response to the invitation, the Jack's event exists now
        $parser = MailParserTest::getParserForFile('mailfilter/itip2_reply.eml', 'jack@kolab.org');
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertSame(Result::STATUS_DISCARD, $result->getStatus());

        $list = $this->davList($account, 'Calendar', 'event');
        $this->assertCount(1, $list);
        $this->assertSame('5463F1DDF6DA264A3FC70E7924B729A5-222222', $list[0]->uid);
        $this->assertCount(2, $attendees = $list[0]->attendees);
        $this->assertSame('john@kolab.org', $attendees[0]['email']);
        $this->assertSame('ACCEPTED', $attendees[0]['partstat']);
        $this->assertSame('ned@kolab.org', $attendees[1]['email']);
        $this->assertSame('TENTATIVE', $attendees[1]['partstat']);
        $this->assertSame('jack@kolab.org', $list[0]->organizer['email']);
        $this->assertCount(1, $list[0]->exceptions);
        $this->assertCount(2, $attendees = $list[0]->exceptions[0]->attendees);
        $this->assertSame('john@kolab.org', $attendees[0]['email']);
        $this->assertSame('ACCEPTED', $attendees[0]['partstat']);
        $this->assertSame('ned@kolab.org', $attendees[1]['email']);
        $this->assertSame('NEEDS-ACTION', $attendees[1]['partstat']);

        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            function (ItipNotification $notification, array $channels, object $notifiable) use ($user) {
                return $notifiable->id == $user->id
                    && $notification->params->mode == 'reply'
                    && $notification->params->senderEmail == 'john@kolab.org'
                    && $notification->params->senderName == 'John'
                    && $notification->params->comment == ''
                    && $notification->params->partstat == 'ACCEPTED'
                    && $notification->params->start == '2024-07-17 12:30'
                    && $notification->params->summary == 'Test Meeting'
                    && $notification->params->recurrenceId == '20240717T123000';
            }
        );

        // TODO: Test corner cases, etc.
    }
}
