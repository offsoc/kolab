<?php

namespace App\Discovery;

use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Autodiscover Service class for Microsoft Autodiscover V2
 */
class MicrosoftJson extends Engine
{
    protected $protocol;

    /**
     * Process incoming request
     */
    protected function handleRequest(Request $request): ?Response
    {
        // Check protocol (at this state we don't know if autodiscover is configured)
        $allowedProtocols = ['activesync', 'autodiscoverv1'];
        $this->protocol = $request->Protocol;

        if (empty($this->protocol)) {
            return $this->error(
                "A valid value must be provided for the query parameter 'Protocol'",
                'MandatoryParameterMissing'
            );
        }

        if (!in_array(strtolower($request->Protocol), $allowedProtocols)) {
            return $this->error(
                sprintf(
                    "The given protocol value '%s' is invalid. Supported values are '%s'",
                    $this->protocol,
                    implode(",", $allowedProtocols)
                ),
                'InvalidProtocol'
            );
        }

        // Check email
        if (preg_match('|autodiscover.json/v1.0/([^\?]+)|', $request->url(), $regs)) {
            $this->email = $regs[1];
        } elseif (!empty($request->Email)) {
            $this->email = $request->Email;
        } elseif (!empty($request->email)) {
            $this->email = $request->email;
        }

        if (empty($this->email) || !str_contains($this->email, '@')) {
            return $this->error('A valid email address must be provided', 'MandatoryParameterMissing');
        }

        return null;
    }

    /**
     * Generates JSON response
     */
    protected function getResponse(): Response
    {
        switch (strtolower($this->protocol)) {
            case 'activesync':
                // throw error if activesync is disabled
                if (empty($this->config['activesync'])) {
                    return $this->error(
                        sprintf(
                            "The given protocol value '%s' is invalid. Supported values are '%s'",
                            $this->protocol,
                            'autodiscoverv1'
                        ),
                        'InvalidProtocol'
                    );
                }

                if (!preg_match('/^https?:/i', $this->config['activesync'])) {
                    $this->config['activesync'] = "https://{$this->config['activesync']}/Microsoft-Server-ActiveSync";
                }

                $json = [
                    'Protocol' => 'ActiveSync',
                    'Url' => $this->config['activesync'],
                ];

                break;
            case 'autodiscoverv1':
            default:
                $json = [
                    'Protocol' => 'AutodiscoverV1',
                    'Url' => "https://{$this->host}/Autodiscover/Autodiscover.xml",
                ];
        }

        return response(json_encode($json, \JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json']);
    }

    /**
     * Send error to the client and exit
     */
    protected function error($msg, $code = 'InternalServerError'): Response
    {
        $json = [
            'ErrorCode' => $code,
            'ErrorMessage' => $msg,
        ];

        return response(json_encode($json, \JSON_PRETTY_PRINT), 400, ['Content-Type' => 'application/json']);
    }
}
