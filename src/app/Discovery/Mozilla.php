<?php

namespace App\Discovery;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Autodiscover Service class for Mozilla Thunderbird
 */
class Mozilla extends Engine
{
    /**
     * Handles the request
     */
    protected function handleRequest(Request $request): ?Response
    {
        $this->email = $request->emailaddress;

        return null;
    }

    /**
     * Generates (XML) response
     */
    protected function getResponse(): Response
    {
        $xml = new \DOMDocument('1.0', 'utf-8');

        // create main elements
        $doc = $xml->createElement('clientConfig');
        $doc->setAttribute('version', '1.1');
        $doc = $xml->appendChild($doc);

        $provider = $xml->createElement('emailProvider');
        $provider->setAttribute('id', $this->config['domain']);
        $provider = $doc->appendChild($provider);

        // provider description tags
        foreach (['domain', 'displayName', 'displayShortName'] as $tag_name) {
            if (!empty($this->config[$tag_name])) {
                $element = $xml->createElement($tag_name);
                $element->appendChild($xml->createTextNode($this->config[$tag_name]));
                $provider->appendChild($element);
            }
        }

        foreach (['imap', 'pop3', 'smtp'] as $type) {
            if (!empty($this->config[$type])) {
                $server = $this->addServerElement($xml, $type);
                $provider->appendChild($server);
            }
        }

        $xml->formatOutput = true;

        $body = $xml->saveXML();

        return response($body, 200, ['Content-Type' => 'application/xml; charset=utf-8']);
    }

    /**
     * Creates server element for XML response
     */
    private function addServerElement(\DOMDocument $xml, string $type): \DOMElement
    {
        $server = $xml->createElement($type == 'smtp' ? 'outgoingServer' : 'incomingServer');
        $server->setAttribute('type', $type);

        // server attributes
        $server_attributes = [
            'hostname',
            'port',
            'socketType',     // SSL or STARTTLS or plain
            'username',
            'authentication', // 'password-cleartext', 'password-encrypted'
        ];

        foreach ($server_attributes as $tag_name) {
            $value = $this->config[$type][$tag_name] ?? null;
            if ($value) {
                $element = $xml->createElement($tag_name);
                $element->appendChild($xml->createTextNode($value));
                $server->appendChild($element);
            }
        }

        return $server;
    }
}
