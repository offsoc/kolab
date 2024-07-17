<?php

namespace Tests\Unit\DataMigrator\EWS;

use App\Backends\DAV\Vcard;
use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\EWS;
use App\DataMigrator\Interface\Folder;
use garethp\ews\API\Type;
use Tests\TestCase;

class ContactTest extends TestCase
{
    /**
     * Test contact item processing
     */
    public function testProcessItem(): void
    {
        $account = new Account('ews://test:test@test');
        $engine = new Engine();
        $ews = new EWS($account, $engine);
        $folder = Folder::fromArray(['id' => 'test']);
        $contact = new EWS\Contact($ews, $folder);

        $vcard = file_get_contents(__DIR__ . '/../../../data/ews/contact/1.vcf');

        // FIXME: I haven't found a way to convert xml content into a Type instance
        // therefore we create it "manually", but it would be better to have both
        // vcard and xml in a single data file that we could just get content from.

        $item = Type::buildFromArray([
            'MimeContent' => base64_encode($vcard),
            'ItemId' => new Type\ItemIdType(
                'AAMkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OABGAAAAAAC9tlDYSlG2TaxWBr'
                    . 'A1OzWtBwAs2ajhknXlRYN/pbC8JqblAAAAAAEOAAAs2ajhknXlRYN/pbC8JqblAAJnrWkBAAA=',
                'EQAAABYAAAAs2ajhknXlRYN/pbC8JqblAAJnqlKm',
            ),
            'HasAttachments' => false,
            'LastModifiedTime' => '2024-07-15T11:17:39,701Z',
            'DisplayName' => 'Nowy Nazwisko',
            'GivenName' => 'Nowy',
            'Surname' => 'Nazwisko',
            'EmailAddresses' => (object) [
                'Entry' => [
                    Type\EmailAddressDictionaryEntryType::buildFromArray([
                        'Key' => 'EmailAddress1',
                        'Name' => 'test1@outlook.com',
                        'RoutingType' => 'SMTP',
                        'MailboxType' => 'Contact',
                        '_value' => 'christian1@outlook.com',
                    ]),
                    Type\EmailAddressDictionaryEntryType::buildFromArray([
                        'Key' => 'EmailAddress2',
                        'Name' => 'test2@outlook.com',
                        'RoutingType' => 'SMTP',
                        'MailboxType' => 'Contact',
                        '_value' => 'test2@outlook.com',
                    ]),
                ],
            ],
            /*
              <t:Attachments>
                <t:FileAttachment>
                  <t:AttachmentId Id="AAMkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OABGAAAAAAC9tlDYSlG2TaxWBrA1OzWtBwAs2ajhknXlRYN/pbC8JqblAAAAAAEOAAAs2ajhknXlRYN/pbC8JqblAAJtnpOaAAABEgAQAEnbgcNDXkBJvckRd0GZtVc="/>
                  <t:Name>ContactPicture.jpg</t:Name>
                  <t:ContentType>image/jpeg</t:ContentType>
                  <t:Size>2081</t:Size>
                  <t:LastModifiedTime>2024-07-15T11:17:38</t:LastModifiedTime>
                  <t:IsInline>false</t:IsInline>
                  <t:IsContactPhoto>true</t:IsContactPhoto>
                </t:FileAttachment>
              </t:Attachments>
              <t:Categories>
                <t:String>category</t:String>
              </t:Categories>
              <t:Culture>en-US</t:Culture>
              <t:FileAs/>
              <t:CompleteName>
                <t:Title>Mr</t:Title>
                <t:FirstName>Nowy</t:FirstName>
                <t:MiddleName>Bartosz</t:MiddleName>
                <t:LastName>Nazwisko</t:LastName>
                <t:Suffix>Jr.</t:Suffix>
                <t:FullName>Nowy Nazwisko</t:FullName>
                <t:Nickname>alec</t:Nickname>
              </t:CompleteName>
              <t:CompanyName>Company</t:CompanyName>
              <t:PhysicalAddresses>
                <t:Entry Key="Home">
                  <t:Street>Testowa</t:Street>
                  <t:City>Warsaw</t:City>
                  <t:State>mazowickie</t:State>
                  <t:CountryOrRegion>Poland</t:CountryOrRegion>
                  <t:PostalCode>00-001</t:PostalCode>
                </t:Entry>
              </t:PhysicalAddresses>
              <t:PhoneNumbers>
                <t:Entry Key="CarPhone"/>
                <t:Entry Key="HomePhone">home123456</t:Entry>
                <t:Entry Key="MobilePhone">1234556679200</t:Entry>
                <t:Entry Key="OtherTelephone"/>
                <t:Entry Key="Pager"/>
              </t:PhoneNumbers>
              <t:Birthday>2014-10-11T11:59:00Z</t:Birthday>
              <t:Department>IT</t:Department>
              <t:JobTitle>Developer</t:JobTitle>
              <t:OfficeLocation>Office Location</t:OfficeLocation>
              <t:WeddingAnniversary>2020-11-12T11:59:00Z</t:WeddingAnniversary>
              <t:HasPicture>true</t:HasPicture>
            */
        ]);

        // Convert the Exchange item into vCard
        $vcard = $this->invokeMethod($contact, 'processItem', [$item]);

        // Parse the vCard
        $contact = new Vcard();
        $this->invokeMethod($contact, 'fromVcard', [$vcard]);

        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $contact->uid);
        $this->assertSame('PUBLIC', $contact->class);
        $this->assertSame('Nowy Nazwisko', $contact->fn);
        $this->assertSame(null, $contact->kind);
        $this->assertSame('Microsoft Exchange', $contact->prodid);
        $this->assertSame('2024-07-15T11:17:39,701Z', $contact->rev);
        $this->assertSame('Notatki do kontaktu', $contact->note);

        // EWS Properties with special handling
        $msId = implode('!', $item->getItemId()->toArray());
        $this->assertSame($msId, $contact->custom['X-MS-ID']);
        $this->assertSame('Partner Name', $contact->custom['X-SPOUSE']);
        $this->assertSame('2020-11-12', $contact->custom['X-ANNIVERSARY']);
        $this->assertCount(2, $contact->email);
        $this->assertSame('internet', $contact->email[0]['type']);
        $this->assertSame('christian1@outlook.com', $contact->email[0]['email']);
        $this->assertSame('internet', $contact->email[1]['type']);
        $this->assertSame('test2@outlook.com', $contact->email[1]['email']);
    }
}
