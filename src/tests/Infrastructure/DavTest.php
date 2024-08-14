<?php

namespace Tests\Infrastructure;

use Tests\TestCase;

/**
 * @group dav
 */
class DavTest extends TestCase
{
    private ?\GuzzleHttp\Client $client = null;
    private ?\App\User $user = null;
    private bool $isCyrus;
    private string $path;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!$this->user) {
            $this->user = $this->getTestUser('davtest@kolab.org', ['password' => 'simple123'], true);
        }

        $baseUri = \config('services.dav.uri');
        $this->isCyrus = strpos($baseUri, '/iRony') === false;
        $this->path = $this->isCyrus ? '/dav' : '/iRony';

        if (!$this->client) {
            $this->client = new \GuzzleHttp\Client([
                'http_errors' => false, // No exceptions
                'base_uri' => $baseUri,
                'verify' => false,
                'auth' => [$this->user->email, 'simple123'],
                'connect_timeout' => 10,
                'timeout' => 10,
                'headers' => [
                    'Content-Type' => 'application/xml; charset=utf-8',
                    'Depth' => '1',
                ]
            ]);
        }
    }

    public function testDiscoverPrincipal(): void
    {
        $body = "<d:propfind xmlns:d='DAV:'><d:prop><d:current-user-principal/></d:prop></d:propfind>";

        $response = $this->client->request('PROPFIND', '', ['body' => $body]);
        $this->assertEquals(207, $response->getStatusCode());

        $data = $response->getBody();
        $email = $this->user->email;

        if ($this->isCyrus) {
            $this->assertStringContainsString("<d:href>{$this->path}/principals/user/{$email}/</d:href>", $data);
        } else {
            $this->assertStringContainsString("<d:href>{$this->path}/principals/{$email}/</d:href>", $data);
        }
    }

    /**
     * This codepath is triggerd by MacOS CalDAV when it tries to login.
     * Verify we don't crash and end up with a 500 status code.
     */
    public function testFailingLogin(): void
    {
        $body = "<d:propfind xmlns:d='DAV:'><d:prop><d:current-user-principal/></d:prop></d:propfind>";
        $params = [
            'Content-Type' => 'application/xml; charset=utf-8',
            'Depth' => '1',
            'body' => $body,
            'auth' => ['invaliduser@kolab.org', 'invalid']
        ];

        $response = $this->client->request('PROPFIND', '', $params);

        $this->assertSame($this->isCyrus ? 401 : 403, $response->getStatusCode());
    }

    /**
     * This codepath is triggerd by MacOS CardDAV when it tries to login.
     * NOTE: This depends on the username_domain roundcube config option.
     */
    public function testShortlogin(): void
    {
        $this->markTestSkipped('Shortlogins dont work with the nginx proxy.');

        // @phpstan-ignore-next-line "Code above always terminates"
        $body = "<d:propfind xmlns:d='DAV:'><d:prop><d:current-user-principal/></d:prop></d:propfind>";
        $response = $this->client->request('PROPFIND', '', [
            'body' => $body,
            'auth' => ['davtest', 'simple123']
        ]);

        $this->assertEquals(207, $response->getStatusCode());
    }

    public function testDiscoverCalendarHomeset(): void
    {
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <c:calendar-home-set />
                </d:prop>
            </d:propfind>
        EOF;

        $email = $this->user->email;
        $href = $this->isCyrus ? "principals/user/{$email}" : '';

        $response = $this->client->request('PROPFIND', $href, ['body' => $body]);
        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();

        if ($this->isCyrus) {
            $this->assertStringContainsString("<d:href>{$this->path}/calendars/user/{$email}/</d:href>", $data);
        } else {
            $this->assertStringContainsString("<d:href>{$this->path}/calendars/{$email}/</d:href>", $data);
        }
    }

    public function testDiscoverCalendars(): string
    {
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <d:resourcetype />
                    <d:displayname />
                    <cs:getctag />
                    <c:supported-calendar-component-set />
                </d:prop>
            </d:propfind>
        EOF;

        $params = [
            'headers' => [
                'Depth' => 'infinity',
            ],
            'body' => $body,
        ];

        $email = $this->user->email;
        $href = $this->isCyrus ? "calendars/user/{$email}" : "calendars/{email}";

        $response = $this->client->request('PROPFIND', $href, $params);

        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();

        if ($this->isCyrus) {
            $this->assertStringContainsString("<d:href>{$this->path}/calendars/user/{$email}/</d:href>", $data);
        } else {
            $this->assertStringContainsString("<d:href>{$this->path}/calendars/{$email}/</d:href>", $data);
        }

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($data);
        $response = $doc->getElementsByTagName('response')->item(1);
        $doc->getElementsByTagName('href')->item(0);

        $this->assertEquals('d:href', $response->childNodes->item(0)->nodeName);
        $href = $response->childNodes->item(0)->nodeValue;

        return $href;
    }

    /**
     * @depends testDiscoverCalendars
     */
    public function testPropfindCalendar($href): void
    {
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <d:resourcetype />
                    <d:owner/>
                    <d:current-user-principal/>
                    <d:current-user-privilege-set/>
                    <d:supported-report-set/>
                    <cs:getctag />
                    <c:supported-calendar-component-set />
                </d:prop>
            </d:propfind>
        EOF;

        $params = [
            'headers' => [
                'Depth' => '0',
            ],
            'body' => $body,
        ];

        $response = $this->client->request('PROPFIND', $href, $params);

        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();
        $this->assertStringContainsString("<d:href>$href</d:href>", $data);
    }

    /**
     * Thunderbird does this and relies on the WWW-Authenticate header response to
     * start sending authenticated requests.
     *
     * @depends testDiscoverCalendars
     */
    public function testPropfindCalendarWithoutAuth($href): void
    {
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <d:resourcetype />
                    <d:owner/>
                    <d:current-user-principal/>
                    <d:current-user-privilege-set/>
                    <d:supported-report-set/>
                    <cs:getctag />
                    <c:supported-calendar-component-set />
                </d:prop>
            </d:propfind>
        EOF;

        $params = [
            'headers' => [
                'Depth' => '0',
            ],
            'body' => $body,
            'auth' => [],
        ];

        $response = $this->client->request('PROPFIND', $href, $params);

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Basic realm=', $response->getHeader('WWW-Authenticate')[0]);

        $data = $response->getBody();
        if ($this->isCyrus) {
            $this->assertStringContainsString("Unauthorized", $data);
        } else {
            $this->assertStringContainsString("<s:exception>Sabre\DAV\Exception\NotAuthenticated</s:exception>", $data);
        }
    }

    /**
     * Required for MacOS autoconfig
     */
    public function testOptions(): void
    {
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <d:resourcetype />
                    <d:displayname />
                    <cs:getctag />
                    <c:supported-calendar-component-set />
                </d:prop>
            </d:propfind>
        EOF;

        $email = $this->user->email;
        $href = $this->isCyrus ? "principals/user/{$email}" : "principals/{email}";

        $response = $this->client->request('OPTIONS', $href, ['body' => $body]);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('PROPFIND', $response->getHeader('Allow')[0]);
    }

    public function testWellKnown(): void
    {
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:cs="http://calendarserver.org/ns/" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <d:resourcetype />
                    <d:displayname />
                    <cs:getctag />
                    <c:supported-calendar-component-set />
                </d:prop>
            </d:propfind>
        EOF;

        $email = $this->user->email;
        $path = trim(\config('services.dav.uri'), '/');
        $baseUri = preg_replace('|/[^/]+$|', '', $path);
        $params = [
            'headers' => [
                'Depth' => 'infinity',
            ],
            'body' => $body,
            'allow_redirects' => false,
            'base_uri' => $baseUri,
        ];

        // The base URL needs to work as a redirect
        $response = $this->client->request('PROPFIND', "/.well-known/caldav/", $params);
        $this->assertEquals(301, $response->getStatusCode());

        $redirectTarget = $response->getHeader('location')[0];

        // FIXME: Is this indeed expected?
        $this->assertEquals($path . ($this->isCyrus ? '/calendars/user/' : '/calendars'), $redirectTarget);

        // Follow the redirect
        $response = $this->client->request('PROPFIND', $redirectTarget, $params);
        $this->assertEquals(207, $response->getStatusCode());

        // Any URL should result in a redirect to the same path
        $response = $this->client->request('PROPFIND', "/.well-known/caldav/calendars/{$email}", $params);
        $this->assertEquals(301, $response->getStatusCode());

        $redirectTarget = $response->getHeader('location')[0];

        // FIXME: This is imho not what I'd expect from Cyrus, and that location fails in the following request
        $expected = $path . ($this->isCyrus ? "/calendars/user/calendars/{$email}" : "/calendars/{$email}");
        $this->assertEquals($expected, $redirectTarget);

        // Follow the redirect
        $response = $this->client->request('PROPFIND', $redirectTarget, $params);
        $this->assertEquals(207, $response->getStatusCode());

        $data = $response->getBody();
        if ($this->isCyrus) {
            $this->assertStringContainsString("<d:href>{$this->path}/calendars/user/{$email}/</d:href>", $data);
        } else {
            $this->assertStringContainsString("<d:href>{$this->path}/calendars/{$email}/</d:href>", $data);
        }
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser($this->user->email);
    }
}
