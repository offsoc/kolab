<?php

namespace Tests\Unit\Backends\DAV;

use App\Backends\DAV\Folder;
use Tests\TestCase;

class FolderTest extends TestCase
{
    /**
     * Test Folder::fromDomElement()
     */
    public function testFromDomElement(): void
    {
        $xml = <<<XML
<?xml version="1.0" encoding="utf-8"?>
        <d:multistatus xmlns:d="DAV:"
        xmlns:cs="http://calendarserver.org/ns/"
        xmlns:c="urn:ietf:params:xml:ns:caldav"
        xmlns:a="http://apple.com/ns/ical/"
        xmlns:k="Kolab:">
  <d:response>
    <d:href>/dav/calendars/user/alec@aphy.io/Default/</d:href>
    <d:propstat>
      <d:prop>
        <d:resourcetype>
          <d:collection/>
          <c:calendar/>
        </d:resourcetype>
        <d:displayname><![CDATA[personal]]></d:displayname>
        <cs:getctag>1665578572-16</cs:getctag>
        <c:supported-calendar-component-set>
          <c:comp name="VEVENT"/>
          <c:comp name="VTODO"/>
          <c:comp name="VJOURNAL"/>
        </c:supported-calendar-component-set>
        <a:calendar-color>#cccccc</a:calendar-color>
      </d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
</d:multistatus>
XML;

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($xml);
        $folder = Folder::fromDomElement($doc->getElementsByTagName('response')->item(0));

        $this->assertInstanceOf(Folder::class, $folder);
        $this->assertSame("/dav/calendars/user/alec@aphy.io/Default/", $folder->href);
        $this->assertSame('1665578572-16', $folder->ctag);
        $this->assertSame('personal', $folder->name);
        $this->assertSame('cccccc', $folder->color);
        $this->assertSame(['collection', 'calendar'], $folder->types);
        $this->assertSame(['VEVENT', 'VTODO', 'VJOURNAL'], $folder->components);
    }
}
