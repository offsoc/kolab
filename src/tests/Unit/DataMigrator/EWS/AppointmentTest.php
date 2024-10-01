<?php

namespace Tests\Unit\DataMigrator\EWS;

use App\Backends\DAV\Vevent;
use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\EWS;
use App\DataMigrator\Interface\Folder;
use App\DataMigrator\Interface\Item;
use garethp\ews\API\Type;
use Tests\TestCase;

class AppointmentTest extends TestCase
{
    /**
     * Test appointment item processing
     */
    public function testConvertItem(): void
    {
        $account = new Account('ews://test:test@test');
        $engine = new Engine();
        $ews = new EWS($account, $engine);
        $folder = Folder::fromArray(['id' => 'test']);
        $targetItem = Item::fromArray(['id' => 'test']);
        $appointment = new EWS\Appointment($ews, $folder);

        $ical = file_get_contents(__DIR__ . '/../../../data/ews/event/1.ics');
        $ical = preg_replace('/\r?\n/', "\r\n", $ical);

        // FIXME: I haven't found a way to convert xml content into a Type instance
        // therefore we create it "manually", but it would be better to have both
        // vcard and xml in a single data file that we could just get content from.

        $item = Type::buildFromArray([
            'MimeContent' => base64_encode($ical),
            'ItemId' => new Type\ItemIdType(
                'AAMkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OABGAAAAAAC9tlDYSlG2TaxWBr'
                    . 'A1OzWtBwAs2ajhknXlRYN/pbC8JqblAAAAAAEOAAAs2ajhknXlRYN/pbC8JqblAAJnrWkBAAA=',
                'EQAAABYAAAAs2ajhknXlRYN/pbC8JqblAAJnqlKm',
            ),
            'UID' => '1F3C13D7E99642A75ABE23D50487B454-8FE68B2E68E1B348',
            'Subject' => 'test subject',
            'HasAttachments' => false,
            'IsAssociated' => false,
            'Start' => '2023-11-21T11:00:00Z',
            'End' => '2023-11-21T11:30:00Z',
            'LegacyFreeBusyStatus' => 'Tentative',
            'CalendarItemType' => 'Single',
            'Organizer' => [
                'Mailbox' => [
                    'Name' => 'Aleksander Machniak',
                    'EmailAddress' => 'test@kolab.org',
                    'RoutingType' => 'SMTP',
                    'MailboxType' => 'Contact',
                ],
            ],
            'RequiredAttendees' => (object) [
                'Attendee' => [
                    Type\AttendeeType::buildFromArray([
                        'Mailbox' => [
                            'Name' => 'Aleksander Machniak',
                            'EmailAddress' => 'test@kolab.org',
                            'RoutingType' => 'SMTP',
                            'MailboxType' => 'Contact',
                        ],
                        'ResponseType' => 'Unknown',
                    ]),
                    Type\AttendeeType::buildFromArray([
                        'Mailbox' => [
                            'Name' => 'Alec Machniak',
                            'EmailAddress' => 'test@outlook.com',
                            'RoutingType' => 'SMTP',
                            'MailboxType' => 'Mailbox',
                        ],
                        'ResponseType' => 'Unknown',
                    ]),
                ],
            ],
        ]);

        // Convert the Exchange item into iCalendar
        $ical = $this->invokeMethod($appointment, 'convertItem', [$item, $targetItem]);

        // Parse the iCalendar output
        $event = new Vevent();
        $this->invokeMethod($event, 'fromIcal', [$ical]);

        $msId = implode('!', $item->getItemId()->toArray());
        $this->assertSame($msId, $event->custom['X-MS-ID']);
        $this->assertSame($item->getUID(), $event->uid);
        $this->assertSame('test description', $event->description);
        $this->assertSame('test subject', $event->summary);
        $this->assertSame('CONFIRMED', $event->status);
        $this->assertSame('PUBLIC', $event->class);
        $this->assertSame('Microsoft Exchange Server 2010', $event->prodid);
        $this->assertSame('2023-11-20T14:50:05+00:00', $event->dtstamp->getDateTime()->format('c'));
        $this->assertSame('2023-11-21T12:00:00+01:00', $event->dtstart->getDateTime()->format('c'));
        $this->assertSame('2023-11-21T12:30:00+01:00', $event->dtend->getDateTime()->format('c'));

        // Organizer/attendees
        $this->assertSame('test@kolab.org', $event->organizer['email']);
        $this->assertSame('Aleksander Machniak', $event->organizer['cn']);
        $this->assertSame('ORGANIZER', $event->organizer['role']);
        $this->assertSame('ACCEPTED', $event->organizer['partstat']);
        $this->assertSame(false, $event->organizer['rsvp']);

        $this->assertCount(1, $event->attendees);
        $this->assertSame('alec@outlook.com', $event->attendees[0]['email']);
        $this->assertSame('Alec Machniak', $event->attendees[0]['cn']);
        $this->assertSame('REQ-PARTICIPANT', $event->attendees[0]['role']);
        $this->assertSame('NEEDS-ACTION', $event->attendees[0]['partstat']);
        $this->assertSame(true, $event->attendees[0]['rsvp']);
    }
}
