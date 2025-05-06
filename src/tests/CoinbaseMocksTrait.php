<?php

namespace Tests;

use App\Providers\Payment\Coinbase;
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
     * @return MockHandler
     */
    public function mockCoinbase()
    {
        $handler = HandlerStack::create(
            $mockHandler = new MockHandler()
        );

        $handler->push(
            Middleware::history($this->coinbaseRequestHistory)
        );

        Coinbase::$testClient = new Client(['handler' => $handler]);

        return $mockHandler;
    }

    public function unmockCoinbase()
    {
        Coinbase::$testClient = null;
    }
}
