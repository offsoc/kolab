<?php

namespace App\Discovery;

use App\Tenant;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

abstract class Engine
{
    protected $config = [];
    protected $email;
    protected $host;
    protected $user;

    /**
     * Get services configuration
     */
    protected function configure()
    {
        $this->config = [
            'email' => $this->user->email,
            'domain' => $this->user->domainNamespace(),
            'displayName' => (string) Tenant::getConfig($this->user->tenant_id, 'services.discovery.name'),
            'displayShortName' => (string) Tenant::getConfig($this->user->tenant_id, 'services.discovery.short_name'),
        ];

        $proto_map = ['tls' => 'STARTTLS', 'ssl' => 'SSL'];

        foreach (['imap', 'pop3', 'smtp'] as $type) {
            $value = (string) Tenant::getConfig($this->user->tenant_id, 'services.discovery.' . $type);

            if ($value) {
                $params = explode(';', $value);
                $pass_secure = in_array($params[1] ?? null, ['CRAM-MD5', 'DIGEST-MD5']);
                $host = $params[0];
                $host = str_replace('%d', $this->config['domain'], $host);
                $host = str_replace('%h', $this->host, $host);

                $url = parse_url($host);

                $this->config[$type] = [
                    'hostname' => $url['host'],
                    'port' => $url['port'],
                    'socketType' => ($proto_map[$url['scheme']] ?? false) ?: 'plain',
                    'username' => $this->config['email'],
                    'authentication' => 'password-' . ($pass_secure ? 'encrypted' : 'cleartext'),
                ];
            }
        }

        if ($host = Tenant::getConfig($this->user->tenant_id, 'services.discovery.activesync')) {
            $host = str_replace('%d', $this->config['domain'], $host);
            $host = str_replace('%h', $this->host, $host);

            $this->config['activesync'] = $host;
        }
    }

    /**
     * Send error to the client and exit
     */
    protected function error($msg): Response
    {
        $response = new Response();
        $response->setStatusCode(500, $msg);

        return $response;
    }

    /**
     * Handle request
     */
    public function handle(Request $request): Response
    {
        $this->host = $request->host();

        // read request parameters
        $response = $this->handleRequest($request);

        if ($response) {
            return $response;
        }

        // validate requested email address
        if (empty($this->email)) {
            return $this->error("Email address not provided");
        }

        if (!str_contains($this->email, '@')) {
            return $this->error("Invalid email address");
        }

        $this->user = User::where('email', $this->email)->first();

        if (!$this->user) {
            return $this->error("Invalid email address");
        }

        // find/set services parameters
        $this->configure();

        // create a response
        return $this->getResponse();
    }

    /**
     * Process incoming request
     */
    abstract protected function handleRequest(Request $request): ?Response;

    /**
     * Generates JSON response
     */
    abstract protected function getResponse(): Response;
}
