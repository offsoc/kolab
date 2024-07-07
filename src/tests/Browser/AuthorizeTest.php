<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class AuthorizeTest extends TestCaseDusk
{
    private $client;

    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        // Create a client for tests
        $this->client = \App\Auth\PassportClient::firstOrCreate(
            ['id' => 'test'],
            [
                'user_id' => null,
                'name' => 'Test',
                'secret' => '123',
                'provider' => 'users',
                'redirect' => 'https://kolab.org',
                'personal_access_client' => 0,
                'password_client' => 0,
                'revoked' => false,
                'allowed_scopes' => ['email'],
            ]
        );
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->client->delete();

        parent::tearDown();
    }

    /**
     * Test /oauth/authorize page
     */
    public function testAuthorize(): void
    {
        $url = '/oauth/authorize?' . http_build_query([
                'client_id' => $this->client->id,
                'response_type' => 'code',
                'scope' => 'email',
                'state' => 'state',
        ]);

        $this->browse(function (Browser $browser) use ($url) {
            $redirect_check = "window.location.host == 'kolab.org'"
                . " && window.location.search.match(/^\?code=[a-f0-9]+&state=state/)";

            // Unauthenticated user
            $browser->visit($url)
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123')
                ->waitUntil($redirect_check);

            // Authenticated user
            $browser->visit($url)
                ->waitUntil($redirect_check);

            // Error handling (invalid response_type)
            $browser->visit('oauth/authorize?response_type=invalid')
                ->assertErrorPage(422)
                ->assertToast(Toast::TYPE_ERROR, 'Invalid value of request property: response_type.');
        });
    }
}
