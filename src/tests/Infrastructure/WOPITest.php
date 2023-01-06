<?php

namespace Tests\Infrastructure;

use Tests\TestCase;

class WOPITest extends TestCase
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
            self::$user = $this->getTestUser('wopitest@kolab.org', ['password' => 'simple123'], true);
        }

        if (!self::$client) {
            self::$client = new \GuzzleHttp\Client([
                'base_uri' => \config('services.wopi.uri'),
                'verify' => false,
                'auth' => [self::$user->email, 'simple123'],
                'connect_timeout' => 10,
                'timeout' => 10
            ]);
        }
    }

    public function testAccess()
    {
        $response = self::$client->request('GET', 'api/?method=authenticate&version=4');
        $this->assertEquals($response->getStatusCode(), 200);
        $json = json_decode($response->getBody(), true);

        $this->assertEquals('OK', $json['status']);
        $token = $json['result']['token'];
        $this->assertTrue(!empty($token));

        //FIXME the session token doesn't seem to be required here?
        $response = self::$client->request('GET', 'api/?method=mimetypes', [
            'headers' => [
                'X-Session_token' => $token
            ]
        ]);
        $this->assertEquals($response->getStatusCode(), 200);
        $json = json_decode($response->getBody(), true);
        $this->assertEquals('OK', $json['status']);
        $this->assertEquals('OK', $json['status']);
        $this->assertContains('image/png', $json['result']['view']);
        $this->assertArrayHasKey('text/plain', $json['result']['edit']);
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testCleanup(): void
    {
        $this->deleteTestUser(self::$user->email);
    }
}
