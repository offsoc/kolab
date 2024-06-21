<?php

namespace Tests;

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use Mollie\Api\MollieApiClient;

trait MollieMocksTrait
{
    public $mollieRequestHistory = [];

    /**
     * Make Mollie's Guzzle instance use a mock handler.
     *
     * @see http://docs.guzzlephp.org/en/stable/testing.html
     *
     * @return \GuzzleHttp\Handler\MockHandler
     */
    public function mockMollie()
    {
        $handler = HandlerStack::create(
            $mockHandler = new MockHandler()
        );

        $handler->push(
            Middleware::history($this->mollieRequestHistory)
        );

        $guzzle = new Client(['handler' => $handler]);

        $this->app->forgetInstance('mollie.api.client');
        $this->app->forgetInstance('mollie.api');
        $this->app->forgetInstance('mollie');

        $this->app->singleton('mollie.api.client', function () use ($guzzle) {
            return new MollieApiClient($guzzle);
        });

        return $mockHandler;
    }

    public function unmockMollie()
    {
        $this->app->forgetInstance('mollie.api.client');
        $this->app->forgetInstance('mollie.api');
        $this->app->forgetInstance('mollie');

        $guzzle = new Client();

        $this->app->singleton('mollie.api.client', function () use ($guzzle) {
            return new MollieApiClient($guzzle);
        });
    }
}
