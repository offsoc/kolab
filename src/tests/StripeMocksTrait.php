<?php

namespace Tests;

use Stripe as StripeAPI;

trait StripeMocksTrait
{
    /**
     * Mock Stripe's HTTP client
     *
     * @return StripeMockClient
     */
    public function mockStripe()
    {
        $mockClient = new StripeMockClient();
        StripeAPI\ApiRequestor::setHttpClient($mockClient);

        return $mockClient;
    }

    public function unmockStripe()
    {
        $curlClient = StripeAPI\HttpClient\CurlClient::instance();
        StripeAPI\ApiRequestor::setHttpClient($curlClient);
    }
}
