<?php

namespace Tests\Unit\Backends\DAV;

use App\Backends\DAV\Vcard;
use Tests\TestCase;

class VcardTest extends TestCase
{
    /**
     * Test Vcard::fromDomElement()
     */
    public function testFromDomElement(): void
    {
        $uid = 'A8CCF090C66A7D4D805A8B897AE75AFD-8FE68B2E68E1B348';
        $vcard = <<<XML
<?xml version="1.0" encoding="utf-8"?>
<d:multistatus xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:carddav">
  <d:response>
    <d:href>/dav/addressbooks/user/test@test.com/Default/$uid.vcf</d:href>
    <d:propstat>
      <d:prop>
        <d:getetag>"d27382e0b401384becb0d5b157d6b73a2c2084a2"</d:getetag>
        <c:address-data><![CDATA[BEGIN:VCARD
VERSION:3.0
FN:Test Test
N:;;;;
UID:$uid
END:VCARD
]]></c:address-data>
      </d:prop>
      <d:status>HTTP/1.1 200 OK</d:status>
    </d:propstat>
  </d:response>
</d:multistatus>
XML;

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($vcard);
        $contact = Vcard::fromDomElement($doc->getElementsByTagName('response')->item(0));

        $this->assertInstanceOf(Vcard::class, $contact);
        $this->assertSame('d27382e0b401384becb0d5b157d6b73a2c2084a2', $contact->etag);
        $this->assertSame("/dav/addressbooks/user/test@test.com/Default/{$uid}.vcf", $contact->href);
        $this->assertSame('text/vcard; charset=utf-8', $contact->contentType);
        $this->assertSame($uid, $contact->uid);

        // TODO: Test all supported properties in detail
    }
}
