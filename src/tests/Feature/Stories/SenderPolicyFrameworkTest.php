<?php

namespace Tests\Feature\Stories;

use App\Domain;
use Tests\TestCase;

/**
 * @group data
 * @group spf
 */
class SenderPolicyFrameworkTest extends TestCase
{
    private $testDomain;
    private $testUser;

    public function setUp(): void
    {
        parent::setUp();

        $this->testDomain = $this->getTestDomain('test.domain', [
                'type' => Domain::TYPE_EXTERNAL,
                'status' => Domain::STATUS_ACTIVE | Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED
        ]);

        $this->testUser = $this->getTestUser('john@test.domain');

        $this->useServicesUrl();
    }

    public function tearDown(): void
    {
        $this->deleteTestUser($this->testUser->email);
        $this->deleteTestDomain($this->testDomain->namespace);

        parent::tearDown();
    }

    // @group skipci
    public function testSenderFailv4()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderFailv6()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            // actually IN AAAA gmail.com.
            'client_address' => '2a00:1450:400a:801::2005',
            'recipient' => $this->testUser->email
        ];

        $this->assertFalse(strpos(':', $data['client_address']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderNone()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-none.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    public function testSenderNoNet()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-none.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '256.0.0.1',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderPass()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-pass.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    public function testSenderPassAll()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-passall.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    // @group skipci
    public function testSenderPermerror()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-permerror.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    // @group skipci
    public function testSenderSoftfail()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderTemperror()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-temperror.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    public function testSenderRelayPolicyHeloExactNegative()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->testUser->setSetting('spf_whitelist', json_encode(['the.only.acceptable.helo']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderRelayPolicyHeloExactPositive()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->testUser->setSetting('spf_whitelist', json_encode(['helo.some.relayservice.domain']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    public function testSenderRelayPolicyRegexpNegative()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->testUser->setSetting('spf_whitelist', json_encode(['/a\.domain/']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderRelayPolicyRegexpPositive()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->testUser->setSetting('spf_whitelist', json_encode(['/relayservice\.domain/']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    public function testSenderRelayPolicyWildcardSubdomainNegative()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->testUser->setSetting('spf_whitelist', json_encode(['.helo.some.relayservice.domain']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderRelayPolicyWildcardSubdomainPositive()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->testUser->setSetting('spf_whitelist', json_encode(['.some.relayservice.domain']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }
}
