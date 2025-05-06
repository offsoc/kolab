<?php

namespace Tests\Infrastructure;

use App\User;
use GuzzleHttp\Client;
use Tests\TestCase;

class WOPITest extends TestCase
{
    private static ?Client $client = null;
    private static ?User $user = null;

    protected function setUp(): void
    {
        parent::setUp();

        if (!self::$user) {
            self::$user = $this->getTestUser('wopitest@kolab.org', ['password' => 'simple123'], true);
        }

        if (!self::$client) {
            self::$client = new Client([
                'base_uri' => \config('services.wopi.uri'),
                'verify' => false,
                'auth' => [self::$user->email, 'simple123'],
                'connect_timeout' => 10,
                'timeout' => 10,
            ]);
        }
    }

    public function testAccess()
    {
        $response = self::$client->request('GET', 'api/?method=authenticate&version=4');
        $this->assertSame($response->getStatusCode(), 200);
        $json = json_decode($response->getBody(), true);

        $this->assertSame('OK', $json['status']);
        $token = $json['result']['token'];
        $this->assertTrue(!empty($token));

        // FIXME the session token doesn't seem to be required here?
        $response = self::$client->request('GET', 'api/?method=mimetypes', [
            'headers' => [
                'X-Session_token' => $token,
            ],
        ]);
        $this->assertSame($response->getStatusCode(), 200);
        $json = json_decode($response->getBody(), true);
        $this->assertSame('OK', $json['status']);
        $this->assertSame('OK', $json['status']);
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
