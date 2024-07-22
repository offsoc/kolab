<?php

namespace Tests\Unit\DataMigrator\EWS;

use App\Backends\DAV\Vcard;
use App\DataMigrator\Account;
use App\DataMigrator\Engine;
use App\DataMigrator\EWS;
use App\DataMigrator\Interface\Folder;
use garethp\ews\API\Type;
use Tests\TestCase;

class DistListTest extends TestCase
{
    /**
     * Test contact item processing
     */
    public function testConvertItem(): void
    {
        $account = new Account('ews://test:test@test');
        $engine = new Engine();
        $ews = new EWS($account, $engine);
        $folder = Folder::fromArray(['id' => 'test']);
        $distlist = new EWS\DistList($ews, $folder);

        // FIXME: I haven't found a way to convert xml content into a Type instance
        // therefore we create it "manually", but it would be better to have both
        // vcard and xml in a single data file that we could just get content from.

        $item = Type::buildFromArray([
            'ItemId' => new Type\ItemIdType(
                'AAMkAGEzOGRlODRiLTBkN2ItNDgwZS04ZDJmLTM5NDEyY2Q0NGQ0OABGAAAAAAC9tlDYSlG2TaxWBr'
                    . 'A1OzWtBwAs2ajhknXlRYN/pbC8JqblAAAAAAEOAAAs2ajhknXlRYN/pbC8JqblAAJnrWkBAAA=',
                'EQAAABYAAAAs2ajhknXlRYN/pbC8JqblAAJnqlKm',
            ),
            'Subject' => 'subject list',
            'LastModifiedTime' => '2024-06-27T13:44:32Z',
            'DisplayName' => 'Lista',
            'FileAs' => 'lista',
            'Body' => [
                'BodyType' => 'Text',
                'IsTruncated' => false,
                '_value' => 'distlist body',
            ],
            'Members' => (object) [
                'Member' => [
                    Type\MemberType::buildFromArray([
                        'Key' => 'AAAAAIErH6S+oxAZnW4A3QEPVAIAAAGAYQBsAGUAYwBAAGEAbABlAGMALgBw'
                            . 'AGwAAABTAE0AVABQAAAAYQBsAGUAYwBAAGEAbABlAGMALgBwAGwAAAA=',
                        'Mailbox' => Type\Mailbox::buildFromArray([
                            'Name' => 'Alec',
                            'EmailAddress' => 'alec@kolab.org',
                            'RoutingType' => 'SMTP',
                            'MailboxType' => 'OneOff',
                        ]),
                        'Status' => 'Normal',
                    ]),
                    Type\MemberType::buildFromArray([
                        'Key' => 'AAAAAIErH6S+oxAZnW4A3QEPVAIAAAGAYQBsAGUAYwBAAGEAbABlAGMALgBw'
                            . 'AGwAAABTAE0AVABQAAAAYQBsAGUAYwBAAGEAbABlAGMALgBwAGwAAAB=',
                        'Mailbox' => Type\Mailbox::buildFromArray([
                            'Name' => 'Christian',
                            'EmailAddress' => 'christian@kolab.org',
                            'RoutingType' => 'SMTP',
                            'MailboxType' => 'OneOff',
                            'ItemId' => new Type\ItemIdType('AAA', 'BBB'),
                        ]),
                        'Status' => 'Normal',
                    ]),
                ],
            ],
        ]);

        // Convert the Exchange item into vCard
        $vcard = $this->invokeMethod($distlist, 'convertItem', [$item]);

        // Parse the vCard
        $distlist = new Vcard();
        $this->invokeMethod($distlist, 'fromVcard', [$vcard]);

        $msId = implode('!', $item->getItemId()->toArray());
        $this->assertSame(['X-MS-ID' => $msId], $distlist->custom);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{40}$/', $distlist->uid);
        $this->assertSame('group', $distlist->kind);
        $this->assertSame('Lista', $distlist->fn);
        $this->assertSame('distlist body', $distlist->note);
        $this->assertSame('Kolab EWS Data Migrator', $distlist->prodid);
        $this->assertSame('2024-06-27T13:44:32Z', $distlist->rev);

        $members = [
            'mailto:%22Alec%22+%3Calec%40kolab.org%3E',
            'urn:uuid:' . sha1('AAA'),
        ];
        $this->assertSame($members, $distlist->member);
    }
}
