<?php

namespace Tests\Feature\Policy;

use App\Domain;
use App\Policy\SPF;
use Tests\TestCase;

/**
 * @group data
 */
class SPFTest extends TestCase
{
    private $testDomain;
    private $testUser;

    protected function setUp(): void
    {
        parent::setUp();

        $this->testDomain = $this->getTestDomain('test.domain', [
            'type' => Domain::TYPE_EXTERNAL,
            'status' => Domain::STATUS_ACTIVE | Domain::STATUS_CONFIRMED | Domain::STATUS_VERIFIED,
        ]);

        $this->testUser = $this->getTestUser('john@test.domain');
    }

    protected function tearDown(): void
    {
        $this->deleteTestUser($this->testUser->email);
        $this->deleteTestDomain($this->testDomain->namespace);

        parent::tearDown();
    }

    /**
     * Test SPF handle
     */
    public function testHandle()
    {
        // Test sender fail (IPv6)
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            // actually IN AAAA gmail.com.
            'client_address' => '2a00:1450:400a:801::2005',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(403, $response->code);
        $this->assertSame('DEFER_IF_PERMIT', $response->action);
        $this->assertSame('Temporary error. Please try again later.', $response->reason);
        $this->assertSPFHeader($response, $data, null);

        // Test none sender
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-none.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('', $response->reason);
        $this->assertSPFHeader($response, $data, 'Neutral');

        // Test sender no net
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-none.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '256.0.0.1',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(403, $response->code);
        $this->assertSame('DEFER_IF_PERMIT', $response->action);
        $this->assertSame('Temporary error. Please try again later.', $response->reason);
        $this->assertSPFHeader($response, $data, null);

        // Test sender pass
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-pass.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('', $response->reason);
        $this->assertSPFHeader($response, $data, 'Pass');

        // Test sender pass all
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-passall.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('', $response->reason);
        $this->assertSPFHeader($response, $data, 'Pass');

        // Test sender relay policy HELO exact negative
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@amazon.co.uk',
            'client_name' => 'helo.some.relayservice.domain',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(403, $response->code);
        $this->assertSame('REJECT', $response->action);
        $this->assertSame('Prohibited by Sender Policy Framework', $response->reason);
        $this->assertSPFHeader($response, $data, 'Fail');

        $this->testUser->setSetting('spf_whitelist', json_encode(['the.only.acceptable.helo']));

        $response = SPF::handle($data);

        $this->assertSame(403, $response->code);
        $this->assertSame('REJECT', $response->action);
        $this->assertSame('Prohibited by Sender Policy Framework', $response->reason);
        $this->assertSPFHeader($response, $data, 'Fail');

        // Test sender relay policy HELO exact positive
        $this->testUser->setSetting('spf_whitelist', json_encode(['helo.some.relayservice.domain']));

        $response = SPF::handle($data);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('HELO name whitelisted', $response->reason);
        $this->assertSPFHeader($response, $data, 'Pass', 'Check skipped at recipient\'s discretion');

        // Test sender relay policy regexp negative
        $this->testUser->setSetting('spf_whitelist', json_encode(['/a\.domain/']));

        $response = SPF::handle($data);

        $this->assertSame(403, $response->code);
        $this->assertSame('REJECT', $response->action);
        $this->assertSame('Prohibited by Sender Policy Framework', $response->reason);
        $this->assertSPFHeader($response, $data, 'Fail');

        // Test sender relay policy regexp positive
        $this->testUser->setSetting('spf_whitelist', json_encode(['/relayservice\.domain/']));

        $response = SPF::handle($data);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('HELO name whitelisted', $response->reason);
        $this->assertSPFHeader($response, $data, 'Pass', 'Check skipped at recipient\'s discretion');

        // Test sender relay policy wildcard subdomain negative
        $this->testUser->setSetting('spf_whitelist', json_encode(['.helo.some.relayservice.domain']));

        $response = SPF::handle($data);

        $this->assertSame(403, $response->code);
        $this->assertSame('REJECT', $response->action);
        $this->assertSame('Prohibited by Sender Policy Framework', $response->reason);
        $this->assertSPFHeader($response, $data, 'Fail');

        // Test sender relay policy wildcard subdomain positive
        $this->testUser->setSetting('spf_whitelist', json_encode(['.some.relayservice.domain']));

        $response = SPF::handle($data);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('HELO name whitelisted', $response->reason);
        $this->assertSPFHeader($response, $data, 'Pass', 'Check skipped at recipient\'s discretion');
    }

    /**
     * @group skipci
     */
    public function testHandleSenderErrors(): void
    {
        // Test sender temp error
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-temperror.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(200, $response->code);
        $this->assertSame('DUNNO', $response->action);
        $this->assertSame('', $response->reason);
        $this->assertSPFHeader($response, $data, 'Temperror');

        // Test sender permament error
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-permerror.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(403, $response->code);
        $this->assertSame('REJECT', $response->action);
        $this->assertSame('Prohibited by Sender Policy Framework', $response->reason);
        $this->assertSPFHeader($response, $data, 'Permerror');

        // Test sender soft fail
        $response = SPF::handle($data = [
            'instance' => 'test.local.instance',
            'protocol_state' => 'RCPT',
            'sender' => 'sender@spf-fail.kolab.org',
            'client_name' => 'mx.kolabnow.com',
            'client_address' => '212.103.80.148',
            'recipient' => $this->testUser->email,
        ]);

        $this->assertSame(403, $response->code);
        $this->assertSame('REJECT', $response->action);
        $this->assertSame('Prohibited by Sender Policy Framework', $response->reason);
        $this->assertSPFHeader($response, $data, 'Fail');
    }

    /**
     * Assert Received-SPF header in the response
     */
    private function assertSPFHeader($response, $data, $state, $content = ''): void
    {
        if (!$state) {
            $this->assertCount(0, $response->prepends);
            return;
        }

        $headers = array_filter($response->prepends, static fn ($h) => str_starts_with($h, 'Received-SPF:'));
        $this->assertCount(1, $headers);

        if ($content) {
            $expected = "Received-SPF: {$state} {$content}";
        } else {
            $expected = sprintf(
                'Received-SPF: %s identity=mailfrom; client-ip=%s; helo=%s; envelope-from=%s;',
                $state,
                $data['client_address'],
                $data['client_name'],
                $data['sender']
            );
        }

        $this->assertSame($expected, $headers[0]);
    }
}
