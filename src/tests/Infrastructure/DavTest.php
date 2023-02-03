<?php

namespace Tests\Infrastructure;

use Tests\TestCase;

class DavTest extends TestCase
{
    private static ?\GuzzleHttp\Client $client = null;
    private static ?\App\User $user = null;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        if (!self::$user) {
            self::$user = $this->getTestUser('davtest@kolab.org', ['password' => 'simple123'], true);
        }

        if (!self::$client) {
            self::$client = new \GuzzleHttp\Client([
                'http_errors' => false, // No exceptions
                'base_uri' => \config("services.dav.uri"),
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

    public function testDiscoverPrincipal()
    {
        $user = self::$user;
        $body = "<d:propfind xmlns:d='DAV:'><d:prop><d:current-user-principal/></d:prop></d:propfind>";
        $response = self::$client->request('PROPFIND', '/iRony/', ['body' => $body]);
        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();
        $this->assertStringContainsString("<d:href>/iRony/principals/{$user->email}/</d:href>", $data);
        $this->assertStringContainsString('<d:href>/iRony/calendars/</d:href>', $data);
        $this->assertStringContainsString('<d:href>/iRony/addressbooks/</d:href>', $data);
    }

    /**
     * This codepath is triggerd by MacOS CalDAV when it tries to login.
     * Verify we don't crash and end up with a 500 status code.
     */
    public function testFailingLogin()
    {
        $body = "<d:propfind xmlns:d='DAV:'><d:prop><d:current-user-principal/></d:prop></d:propfind>";
        $headers = [
            "Content-Type" => "application/xml; charset=utf-8",
            "Depth" => "1",
            'body' => $body,
            'auth' => ['invaliduser@kolab.org', 'invalid']
        ];

        $response = self::$client->request('PROPFIND', '/iRony/', $headers);
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * This codepath is triggerd by MacOS CardDAV when it tries to login.
     * NOTE: This depends on the username_domain roundcube config option.
     */
    public function testShortlogin()
    {
        $this->markTestSkipped('Shortlogins dont work with the nginx proxy.');

        // @phpstan-ignore-next-line "Code above always terminates"
        $body = "<d:propfind xmlns:d='DAV:'><d:prop><d:current-user-principal/></d:prop></d:propfind>";
        $response = self::$client->request('PROPFIND', '/iRony/', [
            'body' => $body,
            'auth' => ['davtest', 'simple123']
        ]);

        $this->assertEquals(207, $response->getStatusCode());
    }

    public function testDiscoverCalendarHomeset()
    {
        $user = self::$user;
        $body = <<<EOF
            <d:propfind xmlns:d="DAV:" xmlns:c="urn:ietf:params:xml:ns:caldav">
                <d:prop>
                    <c:calendar-home-set />
                </d:prop>
            </d:propfind>
        EOF;

        $response = self::$client->request('PROPFIND', '/iRony/', ['body' => $body]);
        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();
        $this->assertStringContainsString("<d:href>/iRony/calendars/{$user->email}/</d:href>", $data);
    }

    public function testDiscoverCalendars()
    {
        $user = self::$user;
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

        $response = self::$client->request('PROPFIND', "/iRony/calendars/{$user->email}", [
            'headers' => [
                "Depth" => "infinity",
            ],
            'body' => $body
        ]);
        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();
        $this->assertStringContainsString("<d:href>/iRony/calendars/{$user->email}/</d:href>", $data);

        $doc = new \DOMDocument('1.0', 'UTF-8');
        $doc->loadXML($data);
        $response = $doc->getElementsByTagName('response')->item(1);
        $doc->getElementsByTagName('href')->item(0);

        $this->assertEquals("d:href", $response->childNodes->item(0)->nodeName);
        $href = $response->childNodes->item(0)->nodeValue;
        return $href;
    }

    /**
     * @depends testDiscoverCalendars
     */
    public function testPropfindCalendar($href)
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

        $response = self::$client->request('PROPFIND', $href, [
            'headers' => [
                "Depth" => "0",
            ],
            'body' => $body,
        ]);
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
    public function testPropfindCalendarWithoutAuth($href)
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

        $response = self::$client->request('PROPFIND', $href, [
            'headers' => [
                "Depth" => "0",
            ],
            'body' => $body,
            'auth' => []
        ]);
        $this->assertEquals(401, $response->getStatusCode());
        $this->assertStringContainsString('Basic realm=', $response->getHeader('WWW-Authenticate')[0]);
        $data = $response->getBody();
        $this->assertStringContainsString("<s:exception>Sabre\DAV\Exception\NotAuthenticated</s:exception>", $data);
    }

    /**
    * Required for MacOS autoconfig
    */
    public function testOptions()
    {
        $user = self::$user;
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

        $response = self::$client->request('OPTIONS', "/iRony/principals/{$user->email}/", ['body' => $body]);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('PROPFIND', $response->getHeader('Allow')[0]);
    }

    public function testWellKnown()
    {
        $user = self::$user;
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

        // The base URL needs to work as a redirect
        $response = self::$client->request('PROPFIND', '/.well-known/caldav', [
            'headers' => [
                "Depth" => "infinity",
            ],
            'body' => $body,
            'allow_redirects' => false
        ]);
        $this->assertEquals(301, $response->getStatusCode());
        $redirectTarget = $response->getHeader('location')[0];
        $this->assertEquals(\config('services.dav.uri') . "iRony/", $redirectTarget);

        // Follow the redirect
        $response = self::$client->request('PROPFIND', $redirectTarget, [
            'headers' => [
                "Depth" => "infinity",
            ],
            'body' => $body,
            'allow_redirects' => false
        ]);
        $this->assertEquals(207, $response->getStatusCode());

        // Any URL should result in a redirect to the same path
        $response = self::$client->request('PROPFIND', "/.well-known/caldav/calendars/{$user->email}", [
            'headers' => [
                "Depth" => "infinity",
            ],
            'body' => $body,
            'allow_redirects' => false
        ]);
        $this->assertEquals(301, $response->getStatusCode());
        $redirectTarget = $response->getHeader('location')[0];
        //FIXME we have an extra slash that we don't technically want here
        $this->assertEquals(\config('services.dav.uri') . "iRony//calendars/{$user->email}", $redirectTarget);

        // Follow the redirect
        $response = self::$client->request('PROPFIND', $redirectTarget, [
            'headers' => [
                "Depth" => "infinity",
            ],
            'body' => $body,
            'allow_redirects' => false
        ]);
        $this->assertEquals(207, $response->getStatusCode());
        $data = $response->getBody();
        $this->assertStringContainsString("<d:href>/iRony/calendars/{$user->email}/</d:href>", $data);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}
