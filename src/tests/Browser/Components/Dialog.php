<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class Dialog extends BaseComponent
{
    protected $selector;


    public function __construct($selector)
    {
        $this->selector = trim($selector);
    }

    /**
     * Get the root selector for the component.
     *
     * @return string
     */
    public function selector()
    {
        return $this->selector;
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
        $browser->waitFor($this->selector() . '.modal.show');
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@title' => '.modal-title',
            '@body' => '.modal-body',
            '@button-action' => '.modal-footer button.modal-action',
            '@button-cancel' => '.modal-footer button.modal-cancel',
        ];
    }
}
