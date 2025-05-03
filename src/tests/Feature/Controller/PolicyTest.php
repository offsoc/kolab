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

        $this->clientAddress = '127.0.0.100';

        $this->net = \App\IP4Net::create([
                'net_number' => '127.0.0.0',
                'net_broadcast' => '127.255.255.255',
                'net_mask' => 8,
                'country' => 'US',
                'rir_name' => 'test',
                'serial' => 1,
        ]);

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
        $this->net->delete();

        Greylist\Connect::where('sender_domain', 'sender.domain')->delete();
        Greylist\Whitelist::where('sender_domain', 'sender.domain')->delete();

        parent::tearDown();
    }

    /**
     * Test greylist policy webhook
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

    /**
     * Test mail filter (POST /api/webhooks/policy/mail/filter)
     */
    public function testMailfilter()
    {
        // Note: Only basic tests here. More detailed policy handler tests are in another place

        $headers = ['CONTENT_TYPE' => 'message/rfc822'];
        $post = file_get_contents(__DIR__ . '/../../data/mail/1.eml');
        $post = str_replace("\n", "\r\n", $post);

        // Basic test, no changes to the mail content
        $url = '/api/webhooks/policy/mail/filter?recipient=john@kolab.org&sender=jack@kolab.org';
        $response = $this->call('POST', $url, [], [], [], $headers, $post)
            ->assertNoContent(204);

        // Test returning (modified) mail content
        $url = '/api/webhooks/policy/mail/filter?recipient=john@kolab.org&sender=jack@external.tld';
        $content = $this->call('POST', $url, [], [], [], $headers, $post)
            ->assertStatus(200)
            ->assertHeader('Content-Type', 'message/rfc822')
            ->streamedContent();

        $this->assertStringContainsString('Subject: [EXTERNAL] test sync', $content);

        // TODO: Test multipart/form-data request
        // TODO: Test rejecting mail
        // TODO: Test two modules that both modify the mail content
        $this->markTestIncomplete();
    }

    /**
     * Test submission policy webhook
     */
    public function testSubmission()
    {
        // Note: Only basic tests here. More detailed policy handler tests are in another place

        // Test invalid sender
        $post = [
            'sender' => 'sender',
            'recipients' => ['recipient@gmail.com'],
        ];

        $response = $this->post('/api/webhooks/policy/submission', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertEquals('REJECT', $json['response']);
        $this->assertEquals("Invalid sender", $json['reason']);

        // Test invalid user
        $post = [
            'user' => 'unknown',
            'sender' => $this->testUser->email,
            'recipients' => ['recipient@gmail.com'],
        ];

        $response = $this->post('/api/webhooks/policy/submission', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertEquals('REJECT', $json['response']);
        $this->assertEquals("Invalid user", $json['reason']);

        // Test unknown user
        $post = [
            'user' => 'unknown@domain.tld',
            'sender' => 'john+test@test.domain',
            'recipients' => ['recipient@gmail.com'],
        ];

        $response = $this->post('/api/webhooks/policy/submission', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertEquals('REJECT', $json['response']);
        $this->assertEquals("Could not find user {$post['user']}", $json['reason']);

        // Test existing user and an invalid sender address
        $post = [
            'user' => 'john@test.domain',
            'sender' => 'john1@test.domain',
            'recipients' => ['recipient@gmail.com'],
        ];

        $response = $this->post('/api/webhooks/policy/submission', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertEquals('REJECT', $json['response']);
        $this->assertEquals("john@test.domain is unauthorized to send mail as john1@test.domain", $json['reason']);

        // Test existing user with a valid sender address
        $post = [
            'user' => 'john@test.domain',
            'sender' => 'john+test@test.domain',
            'recipients' => ['recipient@gmail.com'],
        ];

        $response = $this->post('/api/webhooks/policy/submission', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertEquals('PERMIT', $json['response']);
    }

    /**
     * Test ratelimit policy webhook
     */
    public function testRatelimit()
    {
        // Note: Only basic tests here. More detailed policy handler tests are in another place

        // Test a valid user
        $post = [
            'sender' => $this->testUser->email,
            'recipients' => 'someone@sender.domain',
        ];

        $response = $this->post('/api/webhooks/policy/ratelimit', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('DUNNO', $json['response']);

        // Test invalid sender
        $post['sender'] = 'non-existing';
        $response = $this->post('/api/webhooks/policy/ratelimit', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertSame('HOLD', $json['response']);
        $this->assertSame('Invalid sender email', $json['reason']);

        // Test unknown sender
        $post['sender'] = 'non-existing@example.com';
        $response = $this->post('/api/webhooks/policy/ratelimit', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('DUNNO', $json['response']);

        // Test alias sender
        $this->testUser->suspend();
        $this->testUser->aliases()->create(['alias' => 'alias@test.domain']);
        $post['sender'] = 'alias@test.domain';
        $response = $this->post('/api/webhooks/policy/ratelimit', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertSame('HOLD', $json['response']);
        $this->assertSame('Sender deleted or suspended', $json['reason']);

        // Test app.ratelimit_whitelist
        \config(['app.ratelimit_whitelist' => ['alias@test.domain']]);
        $response = $this->post('/api/webhooks/policy/ratelimit', $post);
        $response->assertStatus(200);

        $json = $response->json();

        $this->assertSame('DUNNO', $json['response']);
    }

    /**
     * Test SPF webhook
     *
     * @group data
     * @group skipci
     */
    public function testSenderPolicyFramework(): void
    {
        // Note: Only basic tests here. More detailed policy handler tests are in another place

        // TODO: Make a test that does not depend on data/dns (remove skipci)

        // Test a valid user
        $post = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $post);
        $response->assertStatus(403);

        $json = $response->json();

        $this->assertSame('REJECT', $json['response']);
        $this->assertSame('Prohibited by Sender Policy Framework', $json['reason']);
        $this->assertSame(['Received-SPF: Fail identity=mailfrom; client-ip=212.103.80.148;'
            . ' helo=mx.kolabnow.com; envelope-from=sender@spf-fail.kolab.org;'], $json['prepend']);
    }
}
