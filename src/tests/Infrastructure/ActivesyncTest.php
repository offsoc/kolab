<?php

namespace Tests\Infrastructure;

use App\Backends\Roundcube;
use Tests\TestCase;
use Illuminate\Support\Str;

class ActivesyncTest extends TestCase
{
    private static ?\GuzzleHttp\Client $client = null;
    private static ?\App\User $user = null;
    private static ?string $deviceId = null;
    private static ?string $deviceId2 = null;

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

    private function request($request, $cmd, $deviceId = null)
    {
        $user = self::$user;
        if (!$deviceId) {
            $deviceId = self::$deviceId;
        }
        $body = self::toWbxml($request);
        return self::$client->request(
            'POST',
            "?Cmd={$cmd}&User={$user->email}&DeviceId={$deviceId}&DeviceType=WindowsOutlook15",
            [
                'headers' => [
                    "Content-Type" => "application/vnd.ms-sync.wbxml",
                    'MS-ASProtocolVersion' => "14.0"
                ],
                'body' => $body
            ]
        );
    }

    private function xpath($dom)
    {
        $xpath = new \DOMXpath($dom);
        $xpath->registerNamespace("ns", $dom->documentElement->namespaceURI);
        $xpath->registerNamespace("Tasks", "uri:Tasks");
        $xpath->registerNamespace("Calendar", "uri:Calendar");
        $xpath->registerNamespace("Email", "uri:Email");
        $xpath->registerNamespace("Email2", "uri:Email2");
        return $xpath;
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
            self::$deviceId2 = (string) Str::uuid();
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
            'Tasks' => [
                'metadata' => [
                    '/private/vendor/kolab/folder-type' => 'task.default',
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
            //FIXME this shouldn't be required, but it seems to be.
            Roundcube::dbh()->table('kolab_cache_task')->truncate();
            Roundcube::dbh()->table('syncroton_folder')->truncate();
            // Roundcube::dbh()->table('syncroton_content')->truncate();
            // Roundcube::dbh()->table('syncroton_device')->truncate();
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

    public function testPartialCommand()
    {
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <FolderSync xmlns="uri:FolderHierarchy">
            <SyncKey>0</SyncKey>
        </FolderSync>
        EOF;

        $body = self::toWbxml($request);
        $deviceId = self::$deviceId;
        $user = self::$user;
        $response =  self::$client->request(
            'POST',
            "?Cmd=FolderSync&User={$user->email}&DeviceId={$deviceId}&DeviceType=WindowsOutlook15",
            [
                'headers' => [
                    "Content-Type" => "application/vnd.ms-sync.wbxml",
                    'MS-ASProtocolVersion' => "14.0"
                ],
                //Truncated body
                'body' => substr($body, 0, strlen($body) / 2)
            ]
        );

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testList()
    {
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <FolderSync xmlns="uri:FolderHierarchy">
            <SyncKey>0</SyncKey>
        </FolderSync>
        EOF;

        $response = $this->request($request, 'FolderSync');
        $this->assertEquals(200, $response->getStatusCode());
        $dom = self::fromWbxml($response->getBody());
        $xml = $dom->saveXML();

        $this->assertStringContainsString('INBOX', $xml);
        // The hash is based on the name, so it's always the same
        $inboxId = '38b950ebd62cd9a66929c89615d0fc04';
        $this->assertStringContainsString($inboxId, $xml);
        $this->assertStringContainsString('Drafts', $xml);
        $this->assertStringContainsString('Calendar', $xml);
        $this->assertStringContainsString('Tasks', $xml);
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

        $response = $this->request($request, 'Sync');
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
        return $inboxId;
    }

    /**
    * @depends testInitialSync
    */
    public function testAdd($inboxId)
    {
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());
        // We expect an empty response without a change
        $this->assertEquals(0, $response->getBody()->getSize());
    }

    /**
    * @depends testList
    */
    public function testSyncTasks()
    {
        $tasksId = "90335880f65deff6e521acea2b71a773";
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
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
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        return $tasksId;
    }

    /**
    * @depends testSyncTasks
    */
    public function testAddTask($tasksId)
    {
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId1</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task1</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Tasks", $collection->childNodes->item(0)->nodeValue);

        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("2", $collection->childNodes->item(1)->nodeValue);

        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);

        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(1, $add->length);
        $this->assertEquals("clientId1", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(0)->nodeValue);
        $this->assertEquals(0, $xpath->query("//ns:Commands")->length);
        return [
            'collectionId' => $tasksId,
            'serverId1' => $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(0)->nodeValue
        ];
    }

    /**
    * Re-issuing the same command should not result in the sync key being invalidated.
    *
    * @depends testAddTask
    */
    public function testReAddTask($result)
    {
        $tasksId = $result['collectionId'];
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId1</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task1</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Tasks", $collection->childNodes->item(0)->nodeValue);

        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("2", $collection->childNodes->item(1)->nodeValue);

        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);

        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(1, $add->length);
        $this->assertEquals("clientId1", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(0)->nodeValue);
        $this->assertEquals(0, $xpath->query("//ns:Commands")->length);
        return [
            'collectionId' => $tasksId,
            'serverId1' => $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(0)->nodeValue
        ];
    }

    /**
    * Make sure we can continue with the sync after the previous hickup, also include a modification.
    *
    * @depends testAddTask
    */
    public function testAddTaskContinued($result)
    {
        $tasksId = $result['collectionId'];
        $serverId = $result['serverId1'];
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>2</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId2</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task2</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                        <Add>
                            <ClientId>clientId3</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task3</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                        <Change>
                            <ServerId>{$serverId}</ServerId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task4</Subject>
                            </ApplicationData>
                        </Change>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Tasks", $collection->childNodes->item(0)->nodeValue);

        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("3", $collection->childNodes->item(1)->nodeValue);

        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);

        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(2, $add->length);
        $this->assertEquals("clientId2", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(0)->nodeValue);
        $this->assertEquals("clientId3", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(1)->nodeValue);
        $this->assertEquals(0, $xpath->query("//ns:Commands")->length);

        // The server does not have to inform about a successful change
        $change = $xpath->query("//ns:Responses/ns:Change");
        $this->assertEquals(0, $change->length);

        return [
            'collectionId' => $tasksId,
            'serverId1' => $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(0)->nodeValue,
            'serverId2' => $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(1)->nodeValue
        ];
    }

    /**
    * Perform another duplicate request.
    *
    * @depends testAddTaskContinued
    */
    public function testAddTaskContinuedAgain($result)
    {
        $tasksId = $result['collectionId'];
        $serverId = $result['serverId1'];
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>2</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId2</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task2</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                        <Add>
                            <ClientId>clientId3</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task3</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                        <Change>
                            <ServerId>{$serverId}</ServerId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task4</Subject>
                            </ApplicationData>
                        </Change>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Tasks", $collection->childNodes->item(0)->nodeValue);

        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("3", $collection->childNodes->item(1)->nodeValue);

        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);

        $xpath = $this->xpath($dom);
        print($dom->saveXML());
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(2, $add->length);

        $this->assertEquals("clientId2", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(0)->nodeValue);
        $this->assertEquals(
            $result['serverId1'],
            $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(0)->nodeValue
        );

        $this->assertEquals("clientId3", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(1)->nodeValue);
        $this->assertEquals(
            $result['serverId2'],
            $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(1)->nodeValue
        );

        // The server does not have to inform about a successful change
        $change = $xpath->query("//ns:Responses/ns:Change");
        $this->assertEquals(0, $change->length);

        $this->assertEquals(0, $xpath->query("//ns:Commands")->length);

        return [
            'collectionId' => $tasksId,
            'serverId2' => $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(1)->nodeValue
        ];
    }

    /**
    * Test a sync key that shouldn't exist yet.
    * @depends testSyncTasks
    */
    public function testInvalidSyncKey($tasksId)
    {
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>4</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId999</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task1</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("3", $status[0]->nodeValue);
        //After this we have to start from scratch
    }

    /**
    * Test fetching changes with a second device
    * @depends testAddTaskContinuedAgain
    */
    public function testFetchTasks($result)
    {
        $tasksId = $result['collectionId'];
        $serverId = $result['serverId2'];
        // Initialize the second device
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <FolderSync xmlns="uri:FolderHierarchy">
            <SyncKey>0</SyncKey>
        </FolderSync>
        EOF;

        $response = $this->request($request, 'FolderSync', self::$deviceId2);
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <WindowSize>512</WindowSize>
                    <Options>
                        <FilterType>0</FilterType>
                        <MIMESupport>2</MIMESupport>
                        <MIMETruncation>8</MIMETruncation>
                        <BodyPreference xmlns="uri:AirSyncBase">
                            <Type>4</Type>
                            <AllOrNone>1</AllOrNone>
                        </BodyPreference>
                    </Options>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync', self::$deviceId2);
        $this->assertEquals(200, $response->getStatusCode());
        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);

        // Fetch the content
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync', self::$deviceId2);
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Commands/ns:Add");
        $this->assertEquals(3, $add->length);


        //Resend the same command
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync', self::$deviceId2);
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Commands/ns:Add");
        $this->assertEquals(3, $add->length);

        // Add another entry, delete an entry, with the original device (we have to init first again)
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <WindowSize>512</WindowSize>
                    <Options>
                        <FilterType>0</FilterType>
                        <MIMESupport>2</MIMESupport>
                        <MIMETruncation>8</MIMETruncation>
                        <BodyPreference xmlns="uri:AirSyncBase">
                            <Type>4</Type>
                            <AllOrNone>1</AllOrNone>
                        </BodyPreference>
                    </Options>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>2</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId4</ClientId>
                            <ApplicationData>
                                <Subject xmlns="uri:Tasks">task4</Subject>
                                <Complete xmlns="uri:Tasks">0</Complete>
                                <DueDate xmlns="uri:Tasks">2020-11-04T00:00:00.000Z</DueDate>
                                <UtcDueDate xmlns="uri:Tasks">2020-11-03T23:00:00.000Z</UtcDueDate>
                            </ApplicationData>
                        </Add>
                        <Delete>
                            <ServerId>{$serverId}</ServerId>
                        </Delete>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());
        $dom = self::fromWbxml($response->getBody());

        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(1, $add->length);
        // Delete does not have to be confirmed according to spec
        $delete = $xpath->query("//ns:Responses/ns:Delete");
        $this->assertEquals(0, $delete->length);

        // And fetch the changes
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>2</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync', self::$deviceId2);
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        // print("=====\n");
        // print($dom->saveXML());
        // print("=====\n");
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Commands/ns:Add");
        $this->assertEquals(1, $add->length);
        $delete = $xpath->query("//ns:Commands/ns:Delete");
        $this->assertEquals(1, $delete->length);

        // and finally refetch the same changes
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>2</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync', self::$deviceId2);
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        // print("=====\n");
        // print($dom->saveXML());
        // print("=====\n");
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Commands/ns:Add");
        $this->assertEquals(1, $add->length);
        //FIXME we currently miss deletions.
        $delete = $xpath->query("//ns:Commands/ns:Delete");
        $this->assertEquals(0, $delete->length);
    }

    /**
    * @depends testList
    */
    public function testSyncCalendar()
    {
        $tasksId = "cca1b81c734abbcd669bea90d23e08ae";
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
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
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        return $tasksId;
    }

    /**
    * @depends testSyncCalendar
    */
    public function testAddEvent($tasksId)
    {
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId1</ClientId>
                            <ApplicationData>
                                <StartTime xmlns="uri:Calendar">20230719T200032Z</StartTime>
                                <BusyStatus xmlns="uri:Calendar">2</BusyStatus>
                                <DtStamp xmlns="uri:Calendar">20230719T194232Z</DtStamp>
                                <EndTime xmlns="uri:Calendar">20230719T203032Z</EndTime>
                                <UID xmlns="uri:Calendar">046f2e01-e8d0-47c6-a607-ba360251761d</UID>
                                <OrganizerEmail xmlns="uri:Calendar">activesynctest@kolab.org</OrganizerEmail>
                                <MeetingStatus xmlns="uri:Calendar">0</MeetingStatus>
                                <ResponseRequested xmlns="uri:Calendar">0</ResponseRequested>
                            </ApplicationData>
                        </Add>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Calendar", $collection->childNodes->item(0)->nodeValue);

        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("2", $collection->childNodes->item(1)->nodeValue);

        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);

        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(1, $add->length);
        $this->assertEquals("clientId1", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(0)->nodeValue);
        $this->assertEquals("1", $xpath->query("//ns:Responses/ns:Add/ns:Status")->item(0)->nodeValue);
        // $this->assertEquals(0, $xpath->query("//ns:Commands")->length);
        return [
            'collectionId' => $tasksId,
            'serverId1' => $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(0)->nodeValue
        ];
    }

    /**
    * @depends testAddEvent
    */
    public function testReaddEvent($result)
    {
        $tasksId = $result['collectionId'];
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>2</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Add>
                            <ClientId>clientId1</ClientId>
                            <ApplicationData>
                                <StartTime xmlns="uri:Calendar">20230719T200032Z</StartTime>
                                <BusyStatus xmlns="uri:Calendar">2</BusyStatus>
                                <DtStamp xmlns="uri:Calendar">20230719T194232Z</DtStamp>
                                <EndTime xmlns="uri:Calendar">20230719T203032Z</EndTime>
                                <UID xmlns="uri:Calendar">046f2e01-e8d0-47c6-a607-ba360251761d</UID>
                                <OrganizerEmail xmlns="uri:Calendar">activesynctest@kolab.org</OrganizerEmail>
                                <MeetingStatus xmlns="uri:Calendar">0</MeetingStatus>
                                <ResponseRequested xmlns="uri:Calendar">0</ResponseRequested>
                            </ApplicationData>
                        </Add>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        $collections = $dom->getElementsByTagName('Collection');
        $this->assertEquals(1, $collections->length);
        $collection = $collections->item(0);
        $this->assertEquals("Class", $collection->childNodes->item(0)->nodeName);
        $this->assertEquals("Calendar", $collection->childNodes->item(0)->nodeValue);

        $this->assertEquals("SyncKey", $collection->childNodes->item(1)->nodeName);
        $this->assertEquals("3", $collection->childNodes->item(1)->nodeValue);

        $this->assertEquals("Status", $collection->childNodes->item(3)->nodeName);
        $this->assertEquals("1", $collection->childNodes->item(3)->nodeValue);

        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(1, $add->length);
        $this->assertEquals("clientId1", $xpath->query("//ns:Responses/ns:Add/ns:ClientId")->item(0)->nodeValue);
        $this->assertEquals("5", $xpath->query("//ns:Responses/ns:Add/ns:Status")->item(0)->nodeValue);
        $this->assertEquals(0, $xpath->query("//ns:Commands")->length);

        return $result;
    }

    /**
    * @depends testReaddEvent
    */
    public function testDeleteEvent($result)
    {
        $tasksId = $result['collectionId'];
        $serverId = $result['serverId1'];
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Tasks="uri:Tasks">
            <Collections>
                <Collection>
                    <SyncKey>3</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <Commands>
                        <Delete>
                            <ServerId>{$serverId}</ServerId>
                        </Delete>
                    </Commands>
                </Collection>
            </Collections>
            <WindowSize>16</WindowSize>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);
        return $tasksId;
    }

    /**
    * @depends testDeleteEvent
    */
    public function testMeetingResponse($tasksId)
    {
        $inboxId = '38b950ebd62cd9a66929c89615d0fc04';

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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        //Add the invitation to inbox

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:Syncroton="uri:Syncroton" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Email="uri:Email" xmlns:Email2="uri:Email2" xmlns:Tasks="uri:Tasks" xmlns:Calendar="uri:Calendar">
        <Collections>
            <Collection xmlns:default="uri:Email" xmlns:default1="uri:AirSyncBase">
            <SyncKey>1</SyncKey>
            <CollectionId>{$inboxId}</CollectionId>
            <Commands xmlns:default="uri:Email" xmlns:default1="uri:AirSyncBase">
                <Add xmlns:default="uri:Email" xmlns:default1="uri:AirSyncBase">
                    <ClientId>clientid1</ClientId>
                    <ApplicationData>
                        <Email:DateReceived xmlns="uri:Email">2023-07-24T06:53:57.000Z</Email:DateReceived>
                        <Email:From xmlns="uri:Email">"Doe, John" &lt;doe@kolab1.mkpf.ch&gt;</Email:From>
                        <Email:InternetCPID xmlns="uri:Email">65001</Email:InternetCPID>
                        <Email:Subject xmlns="uri:Email">You've been invited to "event1"</Email:Subject>
                        <Email:To xmlns="uri:Email">admin@kolab1.mkpf.ch</Email:To>
                        <Email:Read xmlns="uri:Email">1</Email:Read>
                        <Email:Flag xmlns="uri:Email"/>
                        <AirSyncBase:Body xmlns="uri:AirSyncBase">
                        <AirSyncBase:Type>4</AirSyncBase:Type>
                        <AirSyncBase:Data>MIME-Version: 1.0&#13;
        From: "Doe, John" &lt;doe@kolab1.mkpf.ch&gt;&#13;
        Date: Mon, 24 Jul 2023 08:53:55 +0200&#13;
        Message-ID: &lt;9cd8885d1339b976c7cb15db086e7bbc@kolab1.mkpf.ch&gt;&#13;
        To: admin@kolab1.mkpf.ch&#13;
        Subject: You've been invited to "event1"&#13;
        Content-Type: multipart/alternative;&#13;
            boundary="=_a32392e5fc9266e3eeba97347cbfc147"&#13;
        &#13;
        --=_a32392e5fc9266e3eeba97347cbfc147&#13;
        Content-Transfer-Encoding: quoted-printable&#13;
        Content-Type: text/plain; charset=UTF-8;&#13;
        format=flowed&#13;
        &#13;
        *event1*&#13;
        &#13;
        When: 2023-07-24 11:00 - 11:30 (Europe/Vaduz)&#13;
        &#13;
        Invitees: Doe, John &lt;doe@kolab1.mkpf.ch&gt;,&#13;
        admin@kolab1.mkpf.ch&#13;
        &#13;
        Please find attached an iCalendar file with all the event details which you=&#13;
        =20&#13;
        can import to your calendar application.&#13;
        --=_a32392e5fc9266e3eeba97347cbfc147&#13;
        Content-Transfer-Encoding: 8bit&#13;
        Content-Type: text/calendar; charset=UTF-8; method=REQUEST;&#13;
        name=event.ics&#13;
        &#13;
        BEGIN:VCALENDAR&#13;
        VERSION:2.0&#13;
        PRODID:-//Roundcube 1.5.3//Sabre VObject 4.5.3//EN&#13;
        CALSCALE:GREGORIAN&#13;
        METHOD:REQUEST&#13;
        BEGIN:VTIMEZONE&#13;
        TZID:Europe/Vaduz&#13;
        BEGIN:STANDARD&#13;
        DTSTART:20221030T010000&#13;
        TZOFFSETFROM:+0200&#13;
        TZOFFSETTO:+0100&#13;
        TZNAME:CET&#13;
        END:STANDARD&#13;
        BEGIN:STANDARD&#13;
        DTSTART:20231029T010000&#13;
        TZOFFSETFROM:+0200&#13;
        TZOFFSETTO:+0100&#13;
        TZNAME:CET&#13;
        END:STANDARD&#13;
        BEGIN:DAYLIGHT&#13;
        DTSTART:20230326T010000&#13;
        TZOFFSETFROM:+0100&#13;
        TZOFFSETTO:+0200&#13;
        TZNAME:CEST&#13;
        END:DAYLIGHT&#13;
        END:VTIMEZONE&#13;
        BEGIN:VEVENT&#13;
        UID:CC54191F656DFBB294BE0AC18E709315-529CBBDD47ACDDC2&#13;
        DTSTAMP:20230724T065355Z&#13;
        CREATED:20230724T065354Z&#13;
        LAST-MODIFIED:20230724T065354Z&#13;
        DTSTART;TZID=Europe/Vaduz:20230724T110000&#13;
        DTEND;TZID=Europe/Vaduz:20230724T113000&#13;
        SUMMARY:event1&#13;
        SEQUENCE:0&#13;
        TRANSP:OPAQUE&#13;
        ATTENDEE;PARTSTAT=NEEDS-ACTION;ROLE=REQ-PARTICIPANT;CUTYPE=INDIVIDUAL;RSVP=&#13;
        TRUE:mailto:admin@kolab1.mkpf.ch&#13;
        ORGANIZER;CN="Doe, John":mailto:doe@kolab1.mkpf.ch&#13;
        END:VEVENT&#13;
        END:VCALENDAR&#13;
        &#13;
        --=_a32392e5fc9266e3eeba97347cbfc147--&#13;
                        </AirSyncBase:Data>
                        </AirSyncBase:Body>
                        <AirSyncBase:NativeBodyType xmlns="uri:AirSyncBase">1</AirSyncBase:NativeBodyType>
                        <Email:MessageClass xmlns="uri:Email">IPM.Note</Email:MessageClass>
                        <Email:ContentClass xmlns="uri:Email">urn:content-classes:message</Email:ContentClass>
                    </ApplicationData>
                </Add>
            </Commands>
            </Collection>
        </Collections>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $status = $dom->getElementsByTagName('Status');
        $this->assertEquals("1", $status[0]->nodeValue);

        $xpath = $this->xpath($dom);
        $add = $xpath->query("//ns:Responses/ns:Add");
        $this->assertEquals(1, $add->length);
        $this->assertEquals("1", $xpath->query("//ns:Responses/ns:Add/ns:Status")->item(0)->nodeValue);
        $serverId = $xpath->query("//ns:Responses/ns:Add/ns:ServerId")->item(0)->nodeValue;


        //List the MeetingRequest
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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$inboxId}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <GetChanges>1</GetChanges>
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
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());


        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $xpath = $this->xpath($dom);

        $this->assertEquals('IPM.Schedule.Meeting.Request', $xpath->query("//ns:Add/ns:ApplicationData/Email:MessageClass")->item(0)->nodeValue);
        $this->assertEquals('urn:content-classes:calendarmessage', $xpath->query("//ns:Add/ns:ApplicationData/Email:ContentClass")->item(0)->nodeValue);
        $this->assertEquals('BAAAAIIA4AB0xbcQGoLgCAAAAAAAAAAAAAAAAAAAAAAAAAAAPgAAAHZDYWwtVWlkAQAAAENDNTQxOTFGNjU2REZCQjI5NEJFMEFDMThFNzA5MzE1LTUyOUNCQkRENDdBQ0REQzIA', $xpath->query("//ns:Add/ns:ApplicationData/Email:MeetingRequest/Email:GlobalObjId")->item(0)->nodeValue);
        $this->assertEquals('1', $xpath->query("//ns:Add/ns:ApplicationData/Email:MeetingRequest/Email2:MeetingMessageType")->item(0)->nodeValue);

        //Send a meeting response to accept

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <MeetingResponse xmlns="uri:MeetingResponse">
        <Request>
            <CollectionId>{$inboxId}</CollectionId>
            <UserResponse>1</UserResponse>
            <RequestId>$serverId</RequestId>
        </Request>
        </MeetingResponse>
        EOF;
        $response = $this->request($request, 'MeetingResponse');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $xpath = $this->xpath($dom);

        $this->assertEquals("1", $xpath->query("//ns:MeetingResponse/ns:Result/ns:Status")->item(0)->nodeValue);
        $this->assertStringContainsString("CC54191F656DFBB294BE0AC18E709315-529CBBDD47ACDDC2", $xpath->query("//ns:MeetingResponse/ns:Result/ns:CalendarId")->item(0)->nodeValue);


        //Fetch the event and validate

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <GetChanges>1</GetChanges>
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
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());


        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $xpath = $this->xpath($dom);

        $this->assertEquals("CC54191F656DFBB294BE0AC18E709315-529CBBDD47ACDDC2", $xpath->query("//ns:Add/ns:ApplicationData/Calendar:UID")->item(0)->nodeValue);
        $this->assertEquals('activesynctest@kolab.org', $xpath->query("//ns:Add/ns:ApplicationData/Calendar:Attendees/Calendar:Attendee/Calendar:Email")->item(0)->nodeValue);
        $this->assertEquals('3', $xpath->query("//ns:Add/ns:ApplicationData/Calendar:Attendees/Calendar:Attendee/Calendar:AttendeeStatus")->item(0)->nodeValue);
        $serverId = $xpath->query("//ns:Add/ns:ServerId")->item(0)->nodeValue;

        $add = $xpath->query("//ns:Add");
        $this->assertEquals(1, $add->length);


        //Send a dummy event with an invalid attendeestatus (just like outlook does)
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Calendar="uri:Calendar">
        <Collections>
            <Collection>
            <SyncKey>2</SyncKey>
            <CollectionId>{$tasksId}</CollectionId>
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
            <Commands>
                <Add>
                <Class>Calendar</Class>
                <ClientId>{B94F1272-ED5F-4613-90D6-731491596147}</ClientId>
                <ApplicationData>
                    <Timezone xmlns="uri:Calendar">AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==</Timezone>
                    <DtStamp xmlns="uri:Calendar">20230724T173929Z</DtStamp>
                    <StartTime xmlns="uri:Calendar">20230724T090000Z</StartTime>
                    <Subject xmlns="uri:Calendar">event1</Subject>
                    <UID xmlns="uri:Calendar">CC54191F656DFBB294BE0AC18E709315-529CBBDD47ACDDC2</UID>
                    <OrganizerName xmlns="uri:Calendar">doe@kolab1.mkpf.ch</OrganizerName>
                    <OrganizerEmail xmlns="uri:Calendar">doe@kolab1.mkpf.ch</OrganizerEmail>
                    <Attendees xmlns="uri:Calendar">
                    <Attendee>
                        <Email>activesynctest@kolab.org</Email>
                        <Name>activesynctest@kolab.org</Name>
                        <AttendeeStatus>0</AttendeeStatus>
                        <AttendeeType>1</AttendeeType>
                    </Attendee>
                    </Attendees>
                    <EndTime xmlns="uri:Calendar">20230724T093000Z</EndTime>
                    <Sensitivity xmlns="uri:Calendar">0</Sensitivity>
                    <BusyStatus xmlns="uri:Calendar">2</BusyStatus>
                    <AllDayEvent xmlns="uri:Calendar">0</AllDayEvent>
                    <Reminder xmlns="uri:Calendar">15</Reminder>
                    <MeetingStatus xmlns="uri:Calendar">3</MeetingStatus>
                    <ResponseRequested xmlns="uri:Calendar">1</ResponseRequested>
                    <DisallowNewTimeProposal xmlns="uri:Calendar">0</DisallowNewTimeProposal>
                </ApplicationData>
                </Add>
            </Commands>
            </Collection>
        </Collections>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $xpath = $this->xpath($dom);

        $this->assertEquals("5", $xpath->query("//ns:Add/ns:Status")->item(0)->nodeValue);


        //Fetch the event and validate again

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>0</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
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

        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase">
            <Collections>
                <Collection>
                    <SyncKey>1</SyncKey>
                    <CollectionId>{$tasksId}</CollectionId>
                    <DeletesAsMoves>0</DeletesAsMoves>
                    <GetChanges>1</GetChanges>
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
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());


        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $xpath = $this->xpath($dom);

        $this->assertEquals("CC54191F656DFBB294BE0AC18E709315-529CBBDD47ACDDC2", $xpath->query("//ns:Add/ns:ApplicationData/Calendar:UID")->item(0)->nodeValue);
        $this->assertEquals('activesynctest@kolab.org', $xpath->query("//ns:Add/ns:ApplicationData/Calendar:Attendees/Calendar:Attendee/Calendar:Email")->item(0)->nodeValue);
        $this->assertEquals('3', $xpath->query("//ns:Add/ns:ApplicationData/Calendar:Attendees/Calendar:Attendee/Calendar:AttendeeStatus")->item(0)->nodeValue);


        //Send a dummy event to change to tentative (just like outlook does
        $request = <<<EOF
        <?xml version="1.0" encoding="utf-8"?>
        <!DOCTYPE AirSync PUBLIC "-//AIRSYNC//DTD AirSync//EN" "http://www.microsoft.com/">
        <Sync xmlns="uri:AirSync" xmlns:AirSyncBase="uri:AirSyncBase" xmlns:Calendar="uri:Calendar">
        <Collections>
            <Collection>
            <SyncKey>2</SyncKey>
            <CollectionId>{$tasksId}</CollectionId>
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
            <Commands>
                <Change>
                <Class>Calendar</Class>
                <ServerId>{$serverId}</ServerId>
                <ApplicationData>
                    <Timezone xmlns="uri:Calendar">AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA==</Timezone>
                    <DtStamp xmlns="uri:Calendar">20230724T173929Z</DtStamp>
                    <StartTime xmlns="uri:Calendar">20230724T090000Z</StartTime>
                    <Subject xmlns="uri:Calendar">event1</Subject>
                    <UID xmlns="uri:Calendar">CC54191F656DFBB294BE0AC18E709315-529CBBDD47ACDDC2</UID>
                    <OrganizerName xmlns="uri:Calendar">doe@kolab1.mkpf.ch</OrganizerName>
                    <OrganizerEmail xmlns="uri:Calendar">doe@kolab1.mkpf.ch</OrganizerEmail>
                    <Attendees xmlns="uri:Calendar">
                    <Attendee>
                        <Email>activesynctest@kolab.org</Email>
                        <Name>activesynctest@kolab.org</Name>
                        <AttendeeStatus>0</AttendeeStatus>
                        <AttendeeType>1</AttendeeType>
                    </Attendee>
                    </Attendees>
                    <EndTime xmlns="uri:Calendar">20230724T093000Z</EndTime>
                    <Sensitivity xmlns="uri:Calendar">0</Sensitivity>
                    <BusyStatus xmlns="uri:Calendar">1</BusyStatus>
                    <AllDayEvent xmlns="uri:Calendar">0</AllDayEvent>
                    <Reminder xmlns="uri:Calendar">15</Reminder>
                    <MeetingStatus xmlns="uri:Calendar">3</MeetingStatus>
                    <ResponseRequested xmlns="uri:Calendar">1</ResponseRequested>
                    <DisallowNewTimeProposal xmlns="uri:Calendar">0</DisallowNewTimeProposal>
                </ApplicationData>
                </Change>
            </Commands>
            </Collection>
        </Collections>
        </Sync>
        EOF;
        $response = $this->request($request, 'Sync');
        $this->assertEquals(200, $response->getStatusCode());

        $dom = self::fromWbxml($response->getBody());
        print($dom->saveXML());
        $xpath = $this->xpath($dom);

        $this->assertEquals("1", $xpath->query("//ns:Collection/ns:Status")->item(0)->nodeValue);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}
