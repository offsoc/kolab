<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class Error extends BaseComponent
{
    protected $code;
    protected $message;
    protected $messages_map = [
        404 => 'Not Found'
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
            ->assertSeeIn('@code', $this->code)
            ->assertSeeIn('@message', $this->message);
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
