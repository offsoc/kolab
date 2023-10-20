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
            "?Cmd={$cmd}&User={$user->email}&DeviceId={$deviceId}&DeviceType=iphone",
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
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}
