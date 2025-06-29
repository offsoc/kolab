<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Component as BaseComponent;

class QuotaInput extends BaseComponent
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
     * @param Browser $browser
     */
    public function assert($browser)
    {
        $browser->waitFor($this->selector() . ' input[type=range]');
    }

    /**
     * Assert input value
     *
     * @param Browser $browser The browser
     * @param int     $value   Value in GB
     */
    public function assertQuotaValue($browser, $value)
    {
        $browser->assertValue('@input', (string) $value)
            ->assertSeeIn('@label', "{$value} GB");
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@label' => 'label',
            '@input' => 'input',
        ];
    }

    /**
     * Set input value
     *
     * @param Browser $browser The browser
     * @param int     $value   Value in GB
     */
    public function setQuotaValue($browser, $value)
    {
        // Use keyboard because ->value() does not work here
        $browser->click('@input')->keys('@input', '{home}');

        $num = $value - 5;
        while ($num > 0) {
            $browser->keys('@input', '{arrow_right}');
            $num--;
        }

        $browser->assertSeeIn('@label', "{$value} GB");
    }
}
