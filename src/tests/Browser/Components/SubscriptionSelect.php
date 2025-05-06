<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Component as BaseComponent;

class SubscriptionSelect extends BaseComponent
{
    protected $selector;

    public function __construct($selector)
    {
        $this->selector = $selector;
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
     * @param Browser $browser
     */
    public function assert($browser)
    {
        $browser->assertVisible($this->selector);
    }

    /**
     * Assert subscription record
     */
    public function assertSubscription($browser, int $idx, $name, $title = null, $price = null)
    {
        $idx++; // index starts with 1 in css
        $row = "tbody tr:nth-child({$idx})";

        $browser->assertSeeIn("{$row} td.name label", $name);

        if ($title !== null) {
            $browser->assertTip("{$row} td.buttons button", $title);
        }

        if ($price !== null) {
            $browser->assertSeeIn("{$row} td.price", $price);
        }
    }

    /**
     * Assert subscription state
     */
    public function assertSubscriptionState($browser, int $idx, bool $enabled)
    {
        $idx++; // index starts with 1 in css
        $row = "tbody tr:nth-child({$idx})";
        $browser->{$enabled ? 'assertChecked' : 'assertNotChecked'}("{$row} td.selection input");
    }

    /**
     * Enable/Disable the subscription
     */
    public function clickSubscription($browser, int $idx)
    {
        $idx++; // index starts with 1 in css
        $row = "tbody tr:nth-child({$idx})";
        $browser->click("{$row} td.selection input");
    }
}
