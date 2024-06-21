<?php

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

trait CoinbaseMocksTrait
{
    public $coinbaseRequestHistory = [];

    /**
     * Make Coinbase's Guzzle instance use a mock handler.
     *
     * @see http://docs.guzzlephp.org/en/stable/testing.html
     *
     * @return \GuzzleHttp\Handler\MockHandler
     */
    public function mockCoinbase()
    {
        $handler = HandlerStack::create(
            $mockHandler = new MockHandler()
        );

        $handler->push(
            Middleware::history($this->coinbaseRequestHistory)
        );

        \App\Providers\Payment\Coinbase::$testClient = new Client(['handler' => $handler]);

        return $mockHandler;
    }

    public function unmockCoinbase()
    {
        \App\Providers\Payment\Coinbase::$testClient = null;
    }
}
