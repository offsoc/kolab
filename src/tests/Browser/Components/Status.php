<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Component as BaseComponent;

class Status extends BaseComponent
{
    /**
     * Get the root selector for the component.
     *
     * @return string
     */
    public function selector()
    {
        return '#status-box';
    }

    /**
     * Assert that the browser page contains the component.
     *
     * @param Browser $browser The browser object
     */
    public function assert($browser)
    {
        $browser->waitFor($this->selector());
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@body' => "#status-body",
            '@progress-bar' => ".progress-bar",
            '@progress-label' => ".progress-label",
            '@refresh-button' => "#status-refresh",
            '@refresh-text' => "#refresh-text",
        ];
    }

    /**
     * Assert progress state
     */
    public function assertProgress($browser, int $percent, string $label, $class)
    {
        $browser->assertVisible('@progress-bar')
            ->assertAttribute('@progress-bar', 'aria-valuenow', $percent)
            ->assertSeeIn('@progress-label', $label)
            ->withinBody(static function ($browser) use ($class) {
                $browser->assertVisible('#status-box.process-' . $class);
            });
    }
}
