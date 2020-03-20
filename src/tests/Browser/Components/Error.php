<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class Error extends BaseComponent
{
    protected $code;
    protected $message;
    protected $messages_map = [
        400 => "Bad request",
        401 => "Unauthorized",
        403 => "Access denied",
        404 => "Not found",
        405 => "Method not allowed",
        500 => "Internal server error",
    ];

    public function __construct($code)
    {
        $this->code = $code;
        $this->message = $this->messages_map[$code];
    }

    /**
     * Get the root selector for the component.
     *
     * @return string
     */
    public function selector()
    {
        return '#error-page';
    }

    /**
     * Assert that the browser page contains the component.
     *
     * @param \Laravel\Dusk\Browser $browser
     *
     * @return void
     */
    public function assert($browser)
    {
        $browser->waitFor($this->selector())
            ->assertSeeIn('@code', $this->code);

        $message = $browser->text('@message');
        PHPUnit::assertSame(strtolower($message), strtolower($this->message));
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        $selector = $this->selector();

        return [
            '@code' => "$selector .code",
            '@message' =>  "$selector .message",
        ];
    }
}
