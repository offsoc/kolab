<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class Toast extends BaseComponent
{
    public const TYPE_ERROR = 'error';
    public const TYPE_SUCCESS = 'success';
    public const TYPE_WARNING = 'warning';
    public const TYPE_INFO = 'info';

    protected $type;
    protected $element;


    public function __construct($type)
    {
        $this->type = $type;
    }

    /**
     * Get the root selector for the component.
     *
     * @return string
     */
    public function selector()
    {
        return '.toast-container > .toast.toast-' . $this->type;
    }

    /**
     * Assert that the browser page contains the component.
     *
     * @param \Laravel\Dusk\Browser $browser The browser object
     *
     * @return void
     */
    public function assert($browser)
    {
        $browser->waitFor($this->selector());
        $this->element = $browser->element($this->selector());
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@title' => ".toast-title",
            '@message' =>  ".toast-message",
        ];
    }

    /**
     * Assert title of the toast element
     */
    public function assertToastTitle($browser, string $title)
    {
        if (empty($title)) {
            $browser->assertMissing('@title');
        } else {
            $browser->assertSeeIn('@title', $title);
        }
    }

    /**
     * Assert message of the toast element
     */
    public function assertToastMessage($browser, string $message)
    {
        $browser->assertSeeIn('@message', $message);
    }

    /**
     * Close the toast with a click
     */
    public function closeToast($browser)
    {
        $this->element->click();
    }
}
