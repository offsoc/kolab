<?php

namespace Tests;

use Stripe as StripeAPI;

class StripeMockClient implements StripeAPI\HttpClient\ClientInterface
{
    private $responses = [];

    public function request($method, $absUrl, $headers, $params, $hasFile)
    {
        $response = array_shift($this->responses);

        return $response;
    }

    public function addResponse($body, $code = 200, $headers = [])
    {
        $this->responses[] = [$body, $code, $headers];
    }
}
