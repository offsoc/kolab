<?php

namespace App\Http;

use Illuminate\Http\Request as LaravelRequest;

class Request extends LaravelRequest
{
    /**
     * Get the client IP address.
     *
     * @return string|null
     */
    public function ip()
    {
        if (($client_ip = $this->headers->get('X-Client-IP')) && $this->isFromTrustedProxy()) {
            return $client_ip;
        }

        return parent::ip();
    }
}
