<?php

namespace Tests\Infrastructure;

use Tests\TestCase;

class AutodiscoverTest extends TestCase
{
    private static ?\GuzzleHttp\Client $client = null;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        if (!self::$client) {
            self::$client = new \GuzzleHttp\Client([
                'http_errors' => false, // No exceptions
                'base_uri' => "http://roundcube/",
                'verify' => false,
                'connect_timeout' => 10,
                'timeout' => 10,
            ]);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        parent::tearDown();
    }

    public function testWellKnownOutlook()
    {
        $body = <<<EOF
        <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006">
            <Request>
                <EMailAddress>admin@example.local</EMailAddress>
                <AcceptableResponseSchema>
                    http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a
                </AcceptableResponseSchema>
            </Request>
        </Autodiscover>
        EOF;
        $response = self::$client->request('POST', 'autodiscover/autodiscover.xml', [
            'headers' => [
                "Content-Type" => "text/xml; charset=utf-8"
            ],
            'body' => $body
            ]);
        $this->assertEquals($response->getStatusCode(), 200);
        $data = $response->getBody();
        $this->assertTrue(str_contains($data, '<Server>example.local</Server>'));
        $this->assertTrue(str_contains($data, 'admin@example.local'));
    }

    public function testWellKnownActivesync()
    {
        $body = <<<EOF
        <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006">
            <Request>
            <EMailAddress>admin@example.local</EMailAddress>
            <AcceptableResponseSchema>
                http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006
                </AcceptableResponseSchema>
            </Request>
        </Autodiscover>
        EOF;
        $response = self::$client->request('POST', 'autodiscover/autodiscover.xml', [
            'headers' => [
                "Content-Type" => "text/xml; charset=utf-8"
            ],
            'body' => $body
            ]);
        $this->assertEquals($response->getStatusCode(), 200);
        $data = $response->getBody();
        $this->assertTrue(str_contains($data, '<Url>https://example.local/Microsoft-Server-ActiveSync</Url>'));
        $this->assertTrue(str_contains($data, 'admin@example.local'));
    }

    public function testWellKnownMail()
    {
        $response = self::$client->request(
            'GET',
            '.well-known/autoconfig/mail/config-v1.1.xml?emailaddress=fred@example.com'
        );
        $this->assertEquals($response->getStatusCode(), 200);
    }
}
