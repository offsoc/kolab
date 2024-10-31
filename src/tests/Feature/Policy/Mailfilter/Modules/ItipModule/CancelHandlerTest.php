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
class CancelHandlerTest extends TestCase
{
    use BackendsTrait;

    /**
     * Test CANCEL method
     *
     * @group @dav
     */
    public function testItipCancel(): void
    {
        Notification::fake();

        $user = $this->getTestUser('john@kolab.org');
        $uri = preg_replace('|^http|', 'dav', \config('services.dav.uri'));
        $account = new Account(preg_replace('|://|', '://john%40kolab.org:simple123@', $uri));

        $this->davEmptyFolder($account, 'Calendar', 'event');

        // Jack cancelled the meeting, but there's no event in John's calendar
        $parser = MailParserTest::getParserForFile('mailfilter/itip1_cancel.eml', 'john@kolab.org');
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertNull($result);

        Notification::assertNothingSent();

        // Jack cancelled the meeting, and now the event exists in John's calendar
        $this->davAppend($account, 'Calendar', ['mailfilter/event2.ics'], 'event');

        $list = $this->davList($account, 'Calendar', 'event');
        $this->assertCount(1, $list);
        $this->assertSame('5463F1DDF6DA264A3FC70E7924B729A5-D9F1889254B163F5', $list[0]->uid);

        $parser = MailParserTest::getParserForFile('mailfilter/itip1_cancel.eml', 'john@kolab.org');
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertSame(Result::STATUS_DISCARD, $result->getStatus());

        $list = $this->davList($account, 'Calendar', 'event');
        $this->assertCount(0, $list);

        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            function (ItipNotification $notification, array $channels, object $notifiable) use ($user) {
                return $notifiable->id == $user->id
                    && $notification->params->mode == 'cancel'
                    && $notification->params->senderEmail == 'jack@kolab.org'
                    && $notification->params->senderName == 'Jack'
                    && $notification->params->comment == 'event canceled'
                    && $notification->params->start == '2024-07-10 10:30'
                    && $notification->params->summary == 'Test Meeting'
                    && empty($notification->params->recurrenceId);
            }
        );
    }

    /**
     * Test CANCEL method with recurrence
     *
     * @group @dav
     */
    public function testItipCancelRecurrence(): void
    {
        Notification::fake();

        $user = $this->getTestUser('john@kolab.org');
        $uri = preg_replace('|^http|', 'dav', \config('services.dav.uri'));
        $account = new Account(preg_replace('|://|', '://john%40kolab.org:simple123@', $uri));

        $this->davEmptyFolder($account, 'Calendar', 'event');
        $this->davAppend($account, 'Calendar', ['mailfilter/event4.ics'], 'event');

        // Jack cancelled the meeting, and the event exists in John's calendar
        $parser = MailParserTest::getParserForFile('mailfilter/itip2_cancel.eml', 'john@kolab.org');
        $module = new ItipModule();
        $result = $module->handle($parser);

        $this->assertSame(Result::STATUS_DISCARD, $result->getStatus());

        $list = $this->davList($account, 'Calendar', 'event');
        $this->assertSame('5463F1DDF6DA264A3FC70E7924B729A5-222222', $list[0]->uid);
        $this->assertCount(2, $list[0]->attendees);
        $this->assertCount(0, $list[0]->exceptions);
        $this->assertCount(1, $list[0]->exdate);
        $this->assertSame('20240717', (string) $list[0]->exdate[0]);
        $this->assertFalse($list[0]->exdate[0]->hasTime());

        Notification::assertCount(1);
        Notification::assertSentTo(
            $user,
            function (ItipNotification $notification, array $channels, object $notifiable) use ($user) {
                return $notifiable->id == $user->id
                    && $notification->params->mode == 'cancel'
                    && $notification->params->senderEmail == 'jack@kolab.org'
                    && $notification->params->senderName == 'Jack'
                    && $notification->params->comment == ''
                    && $notification->params->start == '2024-07-17 12:30'
                    && $notification->params->summary == 'Test Meeting'
                    && $notification->params->recurrenceId == '20240717T123000';
            }
        );
    }
}
