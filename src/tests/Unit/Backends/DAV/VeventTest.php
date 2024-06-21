<?php

namespace Tests\Unit\Backends\DAV;

use App\Backends\DAV\Vevent;
use Tests\TestCase;

class VeventTest extends TestCase
{
    /**
     * Test Vevent::fromDomElement()
     */
    public function testFromDomElement(): void
    {
        $uid = 'A8CCF090C66A7D4D805A8B897AE75AFD-8FE68B2E68E1B348';
        $ical = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
  <d:response>
    <d:href>/dav/calendars/user/test@test.com/Default/$uid.ics</d:href>
    <d:propstat>
      <d:prop>
        <d:getetag>"d27382e0b401384becb0d5b157d6b73a2c2084a2"</d:getetag>
        <c:calendar-data><![CDATA[BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Test//EN
CALSCALE:GREGORIAN
BEGIN:VEVENT
UID:$uid
DTSTAMP:20221016T103238Z
DTSTART;VALUE=DATE:20221013
DTEND;VALUE=DATE:20221014
SUMMARY:My summary
DESCRIPTION:desc
RRULE:FREQ=WEEKLY
TRANSP:OPAQUE
ORGANIZER:mailto:organizer@test.com
END:VEVENT
END:VCALENDAR
]]></c:calendar-data>
      </d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
</d:multistatus>
XML;

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($ical);
        $event = Vevent::fromDomElement($doc->getElementsByTagName('response')->item(0));

        $this->assertInstanceOf(Vevent::class, $event);
        $this->assertSame('d27382e0b401384becb0d5b157d6b73a2c2084a2', $event->etag);
        $this->assertSame("/dav/calendars/user/test@test.com/Default/{$uid}.ics", $event->href);
        $this->assertSame('text/calendar; charset=utf-8', $event->contentType);
        $this->assertSame($uid, $event->uid);
        $this->assertSame('My summary', $event->summary);
        $this->assertSame('desc', $event->description);
        $this->assertSame('OPAQUE', $event->transp);

        // TODO: Should we make these Sabre\VObject\Property\ICalendar\DateTime properties
        $this->assertSame('20221016T103238Z', (string) $event->dtstamp);
        $this->assertSame('20221013', (string) $event->dtstart);

        $organizer = [
            'rsvp' => false,
            'email' => 'organizer@test.com',
            'role' => 'ORGANIZER',
            'partstat' => 'ACCEPTED',
        ];
        $this->assertSame($organizer, $event->organizer);

        $recurrence = [
            'freq' => 'WEEKLY',
            'interval' => 1,
        ];
        $this->assertSame($recurrence, $event->recurrence);

        // TODO: Test all supported properties in detail
    }
}
