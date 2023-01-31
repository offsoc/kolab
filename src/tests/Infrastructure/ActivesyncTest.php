<?php

namespace Tests\Infrastructure;

use Tests\TestCase;
use Illuminate\Support\Str;

class ActivesyncTest extends TestCase
{
    private static ?\GuzzleHttp\Client $client = null;
    private static ?\App\User $user = null;
    private static ?string $deviceId = null;

    private static function toWbxml($xml)
    {
        $outputStream = fopen("php://temp", 'r+');
        $encoder = new \Syncroton_Wbxml_Encoder($outputStream, 'UTF-8', 3);
        $dom = new \DOMDocument();
        $dom->loadXML($xml);
        $encoder->encode($dom);
        rewind($outputStream);
        return stream_get_contents($outputStream);
    }

    private static function fromWbxml($binary)
    {
        $stream = fopen('php://memory', 'r+');
        fwrite($stream, $binary);
        rewind($stream);
        $decoder = new \Syncroton_Wbxml_Decoder($stream);
        return $decoder->decode();
    }

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!self::$deviceId) {
            // By always creating a new device we force syncroton to initialize.
            // Otherwise we work against uninitialized metadata (subscription states),
            // because the account has been removed, but syncroton doesn't reinitalize the metadata for known devices.
            self::$deviceId = (string) Str::uuid();
        }

        $deviceId = self::$deviceId;
        \config(['imap.default_folders' => [
            'Drafts' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'mail.drafts',
                    '/private/vendor/kolab/activesync' => "{\"FOLDER\":{\"{$deviceId}\":{\"S\":1}}}"
                ],
            ],
            'Calendar' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'event.default',
                    '/private/vendor/kolab/activesync' => "{\"FOLDER\":{\"{$deviceId}\":{\"S\":1}}}"
                ],
            ],
            'Contacts' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'contact.default',
                    '/private/vendor/kolab/activesync' => "{\"FOLDER\":{\"{$deviceId}\":{\"S\":1}}}"
                ],
            ],
        ]]);

        if (!self::$user) {
            self::$user = $this->getTestUser('activesynctest@kolab.org', ['password' => 'simple123'], true);
        }
        if (!self::$client) {
            self::$client = new \GuzzleHttp\Client([
                    'http_errors' => false, // No exceptions
                    'base_uri' => \config("services.activesync.uri"),
                    'verify' => false,
                    'auth' => [self::$user->email, 'simple123'],
                    'connect_timeout' => 10,
                    'timeout' => 10,
                    'headers' => [
                        "Content-Type" => "application/xml; charset=utf-8",
                        "Depth" => "1",
                    ]
            ]);
        }
    }

    public function testOptions()
    {
        $response = self::$client->request('OPTIONS', '');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('14', $response->getHeader('MS-Server-ActiveSync')[0]);
        $this->assertStringContainsString('14.1', $response->getHeader('MS-ASProtocolVersions')[0]);
        $this->assertStringContainsString('FolderSync', $response->getHeader('MS-ASProtocolCommands')[0]);
    }

    public function testList()
    {
        $user = self::$user;
        $deviceId = self::$deviceId;
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <FolderSync xmlns="uri:FolderHierarchy">
            <SyncKey>0</SyncKey>
        </FolderSync>
        EOF;
        $body = self::toWbxml($request);
        $response = self::$client->request(
            'POST',
            "?Cmd=FolderSync&User={$user->email}&DeviceId={$deviceId}&DeviceType=iphone",
            [
                'headers' => [
                    "Content-Type" => "application/vnd.ms-sync.wbxml",
                    'MS-ASProtocolVersion' => "14.0"
                ],
                'body' => $body
            ]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $dom = self::fromWbxml($response->getBody());
        $xml = $dom->saveXML();

        $this->assertStringContainsString('INBOX', $xml);
        // The hash is based on the name, so it's always the same
        $inboxId = '38b950ebd62cd9a66929c89615d0fc04';
        $this->assertStringContainsString($inboxId, $xml);
        $this->assertStringContainsString('Drafts', $xml);
        $this->assertStringContainsString('Calendar', $xml);
        $this->assertStringContainsString('Contacts', $xml);

        // Find the inbox for the next step
        // $collectionIds = $dom->getElementsByTagName('ServerId');
        // $inboxId = $collectionIds[0]->nodeValue;

        return $inboxId;
    }

    /**
    * @depends testList
    */
    public function testInitialSync($inboxId)
    {
        $user = self::$user;
        $deviceId = self::$deviceId;
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$inboxId}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <GetChanges>0</GetChanges>
                    <WindowSize>512</WindowSize>
                    <Options>
                        <FilterType>0</FilterType>
                        <BodyPreference xmlns="uri:AirSyncBase">
                            <Type>1</Type>
                            <AllOrNone>1</AllOrNone>
                        </BodyPreference>
                    </Options>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $body = self::toWbxml($request);
        $response = self::$client->request(
            'POST',
            "?Cmd=Sync&User={$user->email}&DeviceId={$deviceId}&DeviceType=iphone",
            [
                'headers' => [
                    "Content-Type" => "application/vnd.ms-sync.wbxml",
                    'MS-ASProtocolVersion' => "14.0"
                ],
                'body' => $body
            ]
        );
        $this->assertEquals(200, $response->getStatusCode());
        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Email", $collection->childNodes->item(0)->nodeValue);
        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(1)->nodeValue);
        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}
