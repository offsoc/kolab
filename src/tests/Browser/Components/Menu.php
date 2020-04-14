<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class Menu extends BaseComponent
{
    protected $mode;

    /**
     * Object constructor
     *
     * @param string $mode Menu mode ('header' or 'footer')
     */
    public function __construct($mode = 'header')
    {
        $this->mode = $mode;
    }

    /**
     * Get the root selector for the component.
     *
     * @return string
     */
    public function selector()
    {
        return '#' . $this->mode . '-menu';
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
        $browser->assertVisible($this->selector());
    }

    /**
     * Assert that menu contains only specified menu items.
     *
     * @param \Laravel\Dusk\Browser $browser
     * @param array                 $items   List of menu items
     *
     * @return void
     */
    public function assertMenuItems($browser, array $items)
    {
        // TODO: On mobile the links will not be visible

        foreach ($items as $item) {
            $browser->assertVisible('.link-' . $item);
        }

        // Check number of items, to make sure there's no extra items
        PHPUnit::assertCount(count($items), $browser->elements('li'));
    }

    /**
     * Assert that specified menu item is active
     *
     * @param \Laravel\Dusk\Browser $browser
     * @param string                $item    Menu item name
     *
     * @return void
     */
    public function assertActiveItem($browser, string $item)
    {
        // TODO: On mobile the links will not be visible

        $browser->assertVisible(".link-{$item}.active");
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
            '@list' => ".navbar-nav",
            '@brand' =>  ".navbar-brand",
            '@toggler' => ".navbar-toggler",
        ];
    }
}
