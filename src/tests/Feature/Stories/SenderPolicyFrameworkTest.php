<?php

namespace Tests\Feature\Stories;

use Tests\TestCase;

/**
 * @group slow
 * @group data
 * @group spf
 */
class SenderPolicyFrameworkTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        $this->setUpTest();
        $this->useServicesUrl();
    }

    public function testSenderFailv4()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);
    }

    public function testSenderPermerror()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-permerror.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);
    }

    public function testSenderSoftfail()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
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
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->setSetting('spf_whitelist', json_encode(['the.only.acceptable.helo']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->removeSetting('spf_whitelist');
    }

    public function testSenderRelayPolicyHeloExactPositive()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->setSetting('spf_whitelist', json_encode(['helo.some.relayservice.domain']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);

        $this->domainOwner->removeSetting('spf_whitelist');
    }


    public function testSenderRelayPolicyRegexpNegative()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->setSetting('spf_whitelist', json_encode(['/a\.domain/']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->removeSetting('spf_whitelist');
    }

    public function testSenderRelayPolicyRegexpPositive()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->setSetting('spf_whitelist', json_encode(['/relayservice\.domain/']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);

        $this->domainOwner->removeSetting('spf_whitelist');
    }

    public function testSenderRelayPolicyWildcardSubdomainNegative()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->setSetting('spf_whitelist', json_encode(['.helo.some.relayservice.domain']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->removeSetting('spf_whitelist');
    }

    public function testSenderRelayPolicyWildcardSubdomainPositive()
    {
        $data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->domainOwner->email
        ];

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(403);

        $this->domainOwner->setSetting('spf_whitelist', json_encode(['.some.relayservice.domain']));

        $response = $this->post('/api/webhooks/policy/spf', $data);

        $response->assertStatus(200);

        $this->domainOwner->removeSetting('spf_whitelist');
    }
}
