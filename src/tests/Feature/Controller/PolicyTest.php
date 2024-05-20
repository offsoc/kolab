<?php

namespace Tests\Feature\Controller;

use App\Domain;
use App\Policy\Greylist;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PolicyTest extends TestCase
{
    private $clientAddress;
    private $net;
    private $testUser;
    private $testDomain;

    public function setUp(): void
    {
        parent::setUp();

        $this->clientAddress = '212.103.80.148';
        $this->net = \App\IP4Net::getNet($this->clientAddress);
        $this->testDomain = $this->getTestDomain('test.domain', [
                'type' => Domain::TYPE_EXTERNAL,
                'status' => Domain::STATUS_ACTIVE | Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED
        ]);
        $this->testUser = $this->getTestUser('john@test.domain');

        Greylist\Connect::where('sender_domain', 'sender.domain')->delete();
        Greylist\Whitelist::where('sender_domain', 'sender.domain')->delete();

        $this->useServicesUrl();
    }

    public function tearDown(): void
    {
        $this->deleteTestUser($this->testUser->email);
        $this->deleteTestDomain($this->testDomain->namespace);

        Greylist\Connect::where('sender_domain', 'sender.domain')->delete();
        Greylist\Whitelist::where('sender_domain', 'sender.domain')->delete();

        parent::tearDown();
    }

    /**
     * Test greylist policy webhook
     *
     * @group data
     * @group greylist
     */
    public function testGreylist()
    {
        // Note: Only basic tests here. More detailed policy handler tests are in another place

        // Test 403 response
        $post = [
            'sender' => 'someone@sender.domain',
            'recipient' => $this->testUser->email,
            'client_address' => $this->clientAddress,
            'client_name' => 'some.mx'
        ];

        $response = $this->post('/api/webhooks/policy/greylist', $post);

        $response->assertStatus(403);

        $json = $response->json();

        $this->assertEquals('DEFER_IF_PERMIT', $json['response']);
        $this->assertEquals("Greylisted for 5 minutes. Try again later.", $json['reason']);

        // Test 200 response
        $connect = Greylist\Connect::where('sender_domain', 'sender.domain')->first();
        $connect->created_at = \Carbon\Carbon::now()->subMinutes(6);
        $connect->save();

        $response = $this->post('/api/webhooks/policy/greylist', $post);

        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('DUNNO', $json['response']);
        $this->assertMatchesRegularExpression('/^Received-Greylist: greylisted from/', $json['prepend'][0]);
    }
}
