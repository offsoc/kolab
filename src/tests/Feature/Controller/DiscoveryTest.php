<?php

namespace Tests\Feature\Controller;

use App\Discovery;
use Tests\TestCase;

class DiscoveryTest extends TestCase
{
    /**
     * Test Microsoft Autodiscover
     */
    public function testMicrosoftJson()
    {
        $host = parse_url(\config('app.url'), \PHP_URL_HOST);

        $response = $this->get('/autodiscover/autodiscover.json?Email=john@kolab.org&Protocol=AutodiscoverV1');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'Protocol' => 'AutodiscoverV1',
                'Url' => "https://{$host}/Autodiscover/Autodiscover.xml",
            ]);

        $response = $this->get('/autodiscover/autodiscover.json?email=john@kolab.org&Protocol=AutodiscoverV1');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'Protocol' => 'AutodiscoverV1',
                'Url' => "https://{$host}/Autodiscover/Autodiscover.xml",
            ]);

        $response = $this->get('/autodiscover/autodiscover.json/v1.0/john@kolab.org?Protocol=ActiveSync');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'Protocol' => 'ActiveSync',
                'Url' => "https://activesync.{$host}/Microsoft-Server-ActiveSync",
            ]);

        // Test error handling (missing Protocol)
        $response = $this->get('/autodiscover/autodiscover.json?Email=john@kolab.org');
        $response->assertStatus(400)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'ErrorCode' => 'MandatoryParameterMissing',
                'ErrorMessage' => "A valid value must be provided for the query parameter 'Protocol'",
            ]);

        // Test error handling (invalid Protocol)
        $response = $this->get('/autodiscover/autodiscover.json?Email=john@kolab.org&Protocol=unknown');
        $response->assertStatus(400)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'ErrorCode' => 'InvalidProtocol',
                'ErrorMessage' => "The given protocol value 'unknown' is invalid."
                    . " Supported values are 'activesync,autodiscoverv1'",
            ]);

        // Test error handling (missing email)
        $response = $this->get('/autodiscover/autodiscover.json?Protocol=ActiveSync');
        $response->assertStatus(400)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'ErrorCode' => 'MandatoryParameterMissing',
                'ErrorMessage' => "A valid email address must be provided",
            ]);

        // Test error handling (unknown user email)
        $response = $this->get('/autodiscover/autodiscover.json?Protocol=ActiveSync&email=unknown@kolab.org');
        $response->assertStatus(400)
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'ErrorCode' => 'InternalServerError',
                'ErrorMessage' => "Invalid email address",
            ]);
    }

    /**
     * Test Microsoft Outlook/Mobilesync discovery
     */
    public function testMicrosoftXml()
    {
        $host = parse_url(\config('app.url'), \PHP_URL_HOST);

        // Test outlook schema
        $body = <<<'EOF'
            <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/outlook/requestschema/2006">
                <Request>
                    <EMailAddress>john@kolab.org</EMailAddress>
                    <AcceptableResponseSchema>
                        http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a
                    </AcceptableResponseSchema>
                </Request>
            </Autodiscover>
            EOF;

        $response = $this->call('POST', '/autodiscover/autodiscover.xml', [], [], [], [], $body);
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/xml; charset=utf-8');

        $xml = $response->getContent();

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);

        $autodiscover = $doc->documentElement;
        $this->assertSame(Discovery\MicrosoftXml::NS, $autodiscover->getAttribute('xmlns'));
        $response = $autodiscover->getElementsByTagName('Response')->item(0);
        $this->assertSame(Discovery\MicrosoftXml::OUTLOOK_NS, $response->getAttribute('xmlns'));
        $user = $response->getElementsByTagName('User')->item(0);
        $this->assertSame('', $user->getElementsByTagName('DisplayName')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $user->getElementsByTagName('AutoDiscoverSMTPAddress')->item(0)->nodeValue);
        $account = $response->getElementsByTagName('Account')->item(0);
        $this->assertSame('settings', $account->getElementsByTagName('Action')->item(0)->nodeValue);
        $this->assertSame('email', $account->getElementsByTagName('AccountType')->item(0)->nodeValue);
        $protocols = $account->getElementsByTagName('Protocol');
        $this->assertSame('IMAP', $protocols->item(0)->getElementsByTagName('Type')->item(0)->nodeValue);
        $this->assertSame("imap.{$host}", $protocols->item(0)->getElementsByTagName('Server')->item(0)->nodeValue);
        $this->assertSame('993', $protocols->item(0)->getElementsByTagName('Port')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $protocols->item(0)->getElementsByTagName('LoginName')->item(0)->nodeValue);
        $this->assertSame('off', $protocols->item(0)->getElementsByTagName('SPA')->item(0)->nodeValue);
        $this->assertSame('SSL', $protocols->item(0)->getElementsByTagName('Encryption')->item(0)->nodeValue);
        $this->assertSame('POP3', $protocols->item(1)->getElementsByTagName('Type')->item(0)->nodeValue);
        $this->assertSame("pop3.{$host}", $protocols->item(1)->getElementsByTagName('Server')->item(0)->nodeValue);
        $this->assertSame('995', $protocols->item(1)->getElementsByTagName('Port')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $protocols->item(1)->getElementsByTagName('LoginName')->item(0)->nodeValue);
        $this->assertSame('off', $protocols->item(1)->getElementsByTagName('SPA')->item(0)->nodeValue);
        $this->assertSame('SSL', $protocols->item(2)->getElementsByTagName('Encryption')->item(0)->nodeValue);
        $this->assertSame('SMTP', $protocols->item(2)->getElementsByTagName('Type')->item(0)->nodeValue);
        $this->assertSame("smtp.{$host}", $protocols->item(2)->getElementsByTagName('Server')->item(0)->nodeValue);
        $this->assertSame('465', $protocols->item(2)->getElementsByTagName('Port')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $protocols->item(2)->getElementsByTagName('LoginName')->item(0)->nodeValue);
        $this->assertSame('off', $protocols->item(2)->getElementsByTagName('SPA')->item(0)->nodeValue);
        $this->assertSame('SSL', $protocols->item(2)->getElementsByTagName('Encryption')->item(0)->nodeValue);

        // Test mobilesync schema
        $body = <<<'EOF'
            <Autodiscover xmlns="http://schemas.microsoft.com/exchange/autodiscover/mobilesync/requestschema/2006">
                <Request>
                <EMailAddress>john@kolab.org</EMailAddress>
                <AcceptableResponseSchema>
                    http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006
                    </AcceptableResponseSchema>
                </Request>
            </Autodiscover>
            EOF;

        $response = $this->call('POST', '/Autodiscover/Autodiscover.xml', [], [], [], [], $body);
        $response->assertStatus(200);

        $xml = $response->getContent();

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);

        $autodiscover = $doc->documentElement;
        $this->assertSame(Discovery\MicrosoftXml::NS, $autodiscover->getAttribute('xmlns'));
        $response = $autodiscover->getElementsByTagName('Response')->item(0);
        $this->assertSame(Discovery\MicrosoftXml::MOBILESYNC_NS, $response->getAttribute('xmlns'));
        $user = $response->getElementsByTagName('User')->item(0);
        $this->assertSame('', $user->getElementsByTagName('DisplayName')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $user->getElementsByTagName('EMailAddress')->item(0)->nodeValue);
        $action = $response->getElementsByTagName('Action')->item(0);
        $settings = $action->getElementsByTagName('Settings')->item(0);
        $server = $settings->getElementsByTagName('Server')->item(0);
        $this->assertSame('MobileSync', $server->getElementsByTagName('Type')->item(0)->nodeValue);
        $this->assertSame(
            "https://activesync.{$host}/Microsoft-Server-ActiveSync",
            $server->getElementsByTagName('Url')->item(0)->nodeValue
        );

        // Test the other route, i.e. /AutoDiscover/AutoDiscover.xml
        $response = $this->call('POST', '/AutoDiscover/AutoDiscover.xml', [], [], [], [], $body);
        $response->assertStatus(200);

        // Test error responses (empty request body)
        $response = $this->call('POST', '/Autodiscover/Autodiscover.xml', [], [], [], [], '');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'text/xml; charset=utf-8');

        $xml = $response->getContent();

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);

        $autodiscover = $doc->documentElement;
        $this->assertSame(Discovery\MicrosoftXml::NS, $autodiscover->getAttribute('xmlns'));
        $response = $autodiscover->getElementsByTagName('Response')->item(0);
        $error = $response->getElementsByTagName('Error')->item(0);
        $this->assertSame('600', $error->getElementsByTagName('ErrorCode')->item(0)->nodeValue);
        $this->assertSame('Invalid input', $error->getElementsByTagName('Message')->item(0)->nodeValue);

        // TODO: Test more error cases
    }

    /**
     * Test Mozilla Thunderbird format
     */
    public function testMozilla()
    {
        $host = parse_url(\config('app.url'), \PHP_URL_HOST);

        // Test .well-known URL
        $response = $this->get('/.well-known/autoconfig/mail/config-v1.1.xml?emailaddress=john@kolab.org');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $xml = $response->getContent();

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);

        $clientConfig = $doc->documentElement;
        $this->assertSame('1.1', $clientConfig->getAttribute('version'));
        $emailProvider = $clientConfig->getElementsByTagName('emailProvider')->item(0);
        $this->assertSame('kolab.org', $emailProvider->getAttribute('id'));
        $domain = $emailProvider->getElementsByTagName('domain')->item(0);
        $this->assertSame('kolab.org', $domain->nodeValue);
        $displayName = $emailProvider->getElementsByTagName('displayName')->item(0);
        $this->assertSame('Kolab', $displayName->nodeValue);
        $servers = $emailProvider->getElementsByTagName('incomingServer');
        $this->assertSame('imap', $servers->item(0)->getAttribute('type'));
        $this->assertSame("imap.{$host}", $servers->item(0)->getElementsByTagName('hostname')->item(0)->nodeValue);
        $this->assertSame('993', $servers->item(0)->getElementsByTagName('port')->item(0)->nodeValue);
        $this->assertSame('SSL', $servers->item(0)->getElementsByTagName('socketType')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $servers->item(0)->getElementsByTagName('username')->item(0)->nodeValue);
        $this->assertSame('password-cleartext', $servers->item(0)->getElementsByTagName('authentication')->item(0)->nodeValue);
        $this->assertSame('pop3', $servers->item(1)->getAttribute('type'));
        $this->assertSame("pop3.{$host}", $servers->item(1)->getElementsByTagName('hostname')->item(0)->nodeValue);
        $this->assertSame('995', $servers->item(1)->getElementsByTagName('port')->item(0)->nodeValue);
        $this->assertSame('SSL', $servers->item(1)->getElementsByTagName('socketType')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $servers->item(1)->getElementsByTagName('username')->item(0)->nodeValue);
        $this->assertSame('password-cleartext', $servers->item(1)->getElementsByTagName('authentication')->item(0)->nodeValue);
        $servers = $emailProvider->getElementsByTagName('outgoingServer');
        $this->assertSame('smtp', $servers->item(0)->getAttribute('type'));
        $this->assertSame("smtp.{$host}", $servers->item(0)->getElementsByTagName('hostname')->item(0)->nodeValue);
        $this->assertSame('465', $servers->item(0)->getElementsByTagName('port')->item(0)->nodeValue);
        $this->assertSame('SSL', $servers->item(0)->getElementsByTagName('socketType')->item(0)->nodeValue);
        $this->assertSame('john@kolab.org', $servers->item(0)->getElementsByTagName('username')->item(0)->nodeValue);
        $this->assertSame('password-cleartext', $servers->item(0)->getElementsByTagName('authentication')->item(0)->nodeValue);

        // Test non-.well-known URL
        $response = $this->get('/mail/config-v1.1.xml?emailaddress=john@kolab.org');
        $response->assertStatus(200)
            ->assertHeader('Content-Type', 'application/xml; charset=utf-8');

        $xml = $response->getContent();

        $doc = new \DOMDocument('1.0', 'utf-8');
        $doc->loadXML($xml);

        $clientConfig = $doc->documentElement;
        $this->assertSame('1.1', $clientConfig->getAttribute('version'));
        $emailProvider = $clientConfig->getElementsByTagName('emailProvider')->item(0);
        $this->assertSame('kolab.org', $emailProvider->getAttribute('id'));

        // Test error responses
        $response = $this->get('/mail/config-v1.1.xml');
        $response->assertNoContent(500);

        $response = $this->get('/mail/config-v1.1.xml?emailaddress=john');
        $response->assertNoContent(500);
    }
}
