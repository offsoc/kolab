<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class ListInput extends BaseComponent
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
     * @param \Laravel\Dusk\Browser $browser
     *
     * @return void
     */
    public function assert($browser)
    {
        $browser->assertVisible($this->selector)
            ->assertVisible("{$this->selector} @input")
            ->assertVisible("{$this->selector} @add-btn");
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@input' => '.input-group:first-child input',
            '@add-btn' => '.input-group:first-child a.btn',
        ];
    }

    /**
     * Assert list input content
     */
    public function assertListInputValue($browser, array $list)
    {
        if (empty($list)) {
            $browser->assertMissing('.input-group:not(:first-child)');
            return;
        }

        foreach ($list as $idx => $value) {
            $selector = '.input-group:nth-child(' . ($idx + 2) . ') input';
            $browser->assertVisible($selector)->assertValue($selector, $value);
        }
    }

    /**
     * Add list entry
     */
    public function addListEntry($browser, string $value)
    {
        $browser->type('@input', $value)
            ->click('@add-btn')
            ->assertValue('.input-group:last-child input', $value);
    }

    /**
     * Remove list entry
     */
    public function removeListEntry($browser, int $num)
    {
        $selector = '.input-group:nth-child(' . ($num + 1) . ') a.btn';
        $browser->click($selector);
    }

    /**
     * Assert an error message on the widget
     */
    public function assertFormError($browser, int $num, string $msg, bool $focused = false)
    {
        $selector = '.input-group:nth-child(' . ($num + 1) . ') input.is-invalid';

        $browser->assertVisible($selector)
            ->assertSeeIn(' + .invalid-feedback', $msg);

        if ($focused) {
            $browser->assertFocused($selector);
        }
    }
}
