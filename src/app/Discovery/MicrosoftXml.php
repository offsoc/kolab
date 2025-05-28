<?php

namespace App\Discovery;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Autodiscover Service class for Microsoft Outlook and Activesync devices
 */
class MicrosoftXml extends Engine
{
    public const NS = "http://schemas.microsoft.com/exchange/autodiscover/responseschema/2006";
    public const OUTLOOK_NS = "http://schemas.microsoft.com/exchange/autodiscover/outlook/responseschema/2006a";
    public const MOBILESYNC_NS = "http://schemas.microsoft.com/exchange/autodiscover/mobilesync/responseschema/2006";

    private $type = 'outlook';
    // private $password;

    /**
     * Handle request parameters (find email address)
     */
    protected function handleRequest(Request $request): ?Response
    {
        try {
            $post = $request->getContent();

            // Parse XML input
            $doc = new \DOMDocument();
            $doc->loadXML($post);

            $ns = $doc->documentElement->getAttributeNode('xmlns')->nodeValue;
            if (empty($ns)) {
                return $this->error("Invalid input. Missing XML request schema");
            }

            if ($email = $doc->getElementsByTagName('EMailAddress')->item(0)) {
                $this->email = $email->nodeValue;
            }

            if ($schema = $doc->getElementsByTagName('AcceptableResponseSchema')->item(0)) {
                $schema = $schema->nodeValue;

                if (str_contains($schema, 'mobilesync')) {
                    $this->type = 'mobilesync';
                }
            }
        } catch (\Throwable $e) {
            return $this->error("Invalid input");
        }

        if (\config('services.autodiscover.mobilesync_only') && $this->type != 'mobilesync') {
            return $this->error("Only mobilesync schema supported");
        }

        // Check for basic authentication
        // FIXME: Is the authentication required or not? Looking at the old kolab-autoconf
        // code it seems like we do not authenticate user if there's no LDAP.
        // From what I see it would be needed if we wanted to support alias authentication.
        // or return real user displayName. We don't do this right now.
        /*
        $user = $request->getUser();
        $this->password = $request->getPassword();

        // basic auth username must match with given email address
        if (empty($user) || empty($this->password) || strcasecmp($user, $this->email) != 0) {
            return $this->unauthorized();
        }
        */

        return null;
    }

    /**
     * Handle response
     */
    protected function getResponse(): Response
    {
        // FIXME: here we would authenticate the user (but see above)

        if ($this->type == 'mobilesync') {
            $xml = $this->mobilesyncResponse();
        } else {
            $xml = $this->outlookResponse();
        }

        if ($xml === null) {
            return $this->error("Schema '{$this->type}' not supported");
        }

        $xml->formatOutput = true;

        $body = $xml->saveXML();

        return response($body, 200)->header('Content-Type', 'text/xml; charset=utf-8');
    }

    /**
     * Send error to the client and exit
     */
    protected function error($msg): Response
    {
        $xml = new \DOMDocument('1.0', 'utf-8');
        $doc = $xml->createElementNS(self::NS, 'Autodiscover');
        $doc = $xml->appendChild($doc);

        $response = $xml->createElement('Response');
        $response = $doc->appendChild($response);

        [$usec, $sec] = explode(' ', microtime());

        $error = $xml->createElement('Error');
        $error->setAttribute('Time', date('H:i:s', (int) $sec) . '.' . substr($usec, 2, 6));
        $error->setAttribute('Id', sprintf("%u", crc32($this->host)));
        $response->appendChild($error);

        $code = $xml->createElement('ErrorCode');
        $code->appendChild($xml->createTextNode('600'));
        $error->appendChild($code);

        $message = $xml->createElement('Message');
        $message->appendChild($xml->createTextNode($msg));
        $error->appendChild($message);

        $response->appendChild($xml->createElement('DebugData'));

        $xml->formatOutput = true;

        $body = $xml->saveXML();

        return response($body, 200, ['Content-Type' => 'text/xml; charset=utf-8']);
    }

    /**
     * Generates XML response for Activesync
     */
    protected function mobilesyncResponse(): ?\DOMDocument
    {
        if (empty($this->config['activesync'])) {
            return null;
        }

        if (!preg_match('/^https?:/i', $this->config['activesync'])) {
            $this->config['activesync'] = "https://{$this->config['activesync']}/Microsoft-Server-ActiveSync";
        }

        $xml = new \DOMDocument('1.0', 'utf-8');

        // create main elements (tree)
        $doc = $xml->createElementNS(self::NS, 'Autodiscover');
        $doc = $xml->appendChild($doc);

        $response = $xml->createElementNS(self::MOBILESYNC_NS, 'Response');
        $response = $doc->appendChild($response);

        $user = $xml->createElement('User');
        $user = $response->appendChild($user);

        $action = $xml->createElement('Action');
        $action = $response->appendChild($action);

        $settings = $xml->createElement('Settings');
        $settings = $action->appendChild($settings);

        $server = $xml->createElement('Server');
        $server = $settings->appendChild($server);

        // configuration
        $dispname = $xml->createElement('DisplayName');
        $dispname = $user->appendChild($dispname);
        $dispname->appendChild($xml->createTextNode($this->config['username'] ?? ''));

        $email = $xml->createElement('EMailAddress');
        $email = $user->appendChild($email);
        $email->appendChild($xml->createTextNode($this->config['email']));

        $element = $xml->createElement('Type');
        $element = $server->appendChild($element);
        $element->appendChild($xml->createTextNode('MobileSync'));

        $element = $xml->createElement('Url');
        $element = $server->appendChild($element);
        $element->appendChild($xml->createTextNode($this->config['activesync']));

        return $xml;
    }

    /**
     * Generates XML response for Outlook
     */
    protected function outlookResponse(): \DOMDocument
    {
        $xml = new \DOMDocument('1.0', 'utf-8');

        // create main elements (tree)
        $doc = $xml->createElementNS(self::NS, 'Autodiscover');
        $doc = $xml->appendChild($doc);

        $response = $xml->createElementNS(self::OUTLOOK_NS, 'Response');
        $response = $doc->appendChild($response);

        $user = $xml->createElement('User');
        $user = $response->appendChild($user);

        $account = $xml->createElement('Account');
        $account = $response->appendChild($account);

        $accountType = $xml->createElement('AccountType');
        $accountType = $account->appendChild($accountType);
        $accountType->appendChild($xml->createTextNode('email'));

        $action = $xml->createElement('Action');
        $action = $account->appendChild($action);
        $action->appendChild($xml->createTextNode('settings'));

        // configuration
        $dispname = $xml->createElement('DisplayName');
        $dispname = $user->appendChild($dispname);
        $dispname->appendChild($xml->createTextNode($this->config['username'] ?? ''));

        $email = $xml->createElement('AutoDiscoverSMTPAddress');
        $email = $user->appendChild($email);
        $email->appendChild($xml->createTextNode($this->config['email']));

        // @TODO: Microsoft supports also DAV protocol here
        foreach (['imap', 'pop3', 'smtp'] as $type) {
            if (!empty($this->config[$type])) {
                $protocol = $this->addProtocolElement($xml, $type);
                $account->appendChild($protocol);
            }
        }

        return $xml;
    }

    /**
     * Creates Protocol element for XML response
     */
    private function addProtocolElement(\DOMDocument $xml, string $type): \DOMElement
    {
        $protocol = $xml->createElement('Protocol');

        $element = $xml->createElement('Type');
        $element = $protocol->appendChild($element);
        $element->appendChild($xml->createTextNode(strtoupper($type)));

        // @TODO: TTL/ExpirationDate tags

        // server attributes map
        $server_attributes = [
            'Server' => 'hostname',
            'Port' => 'port',
            'LoginName' => 'username',
        ];

        foreach ($server_attributes as $tag_name => $conf_name) {
            $value = $this->config[$type][$conf_name];
            if (!empty($value)) {
                $element = $xml->createElement($tag_name);
                $element->appendChild($xml->createTextNode($value));
                $protocol->appendChild($element);
            }
        }

        $spa = $this->config[$type]['authentication'] == 'password-encrypted' ? 'on' : 'off';
        $element = $xml->createElement('SPA');
        $element->appendChild($xml->createTextNode($spa));
        $protocol->appendChild($element);

        $map = ['STARTTLS' => 'TLS', 'SSL' => 'SSL', 'plain' => 'None'];
        $element = $xml->createElement('Encryption');
        $element->appendChild($xml->createTextNode($map[$this->config[$type]['socketType']] ?? 'Auto'));
        $protocol->appendChild($element);

        return $protocol;
    }

    /**
     * Send 401 Unauthorized to the client
     */
    protected function unauthorized($basicauth = true): Response
    {
        $response = response('', 401);

        if ($basicauth) {
            $response->headers->set('WWW-Authenticate', "Basic realm=\"{$this->host}\"");
        }

        return $response;
    }
}
