<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class Menu extends BaseComponent
{
    /**
     * Get the root selector for the component.
     *
     * @return string
     */
    public function selector()
    {
        return '#primary-menu';
    }

    /**
     * Assert that the browser page contains the component.
     *
     * @param Browser $browser
     *
     * @return void
     */
    public function assert(Browser $browser)
    {
        $browser->assertVisible($this->selector());
        $browser->assertVisible('@brand');
    }

    /**
     * Assert that menu contains only specified menu items.
     *
     * @param Browser $browser
     * @param array   $items   List of menu items
     *
     * @return void
     */
    public function assertMenuItems(Browser $browser, array $items)
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
     * @param Browser $browser
     * @param string  $item    Menu item name
     *
     * @return void
     */
    public function assertActiveItem(Browser $browser, string $item)
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
            '@list' => "$selector .navbar-nav",
            '@brand' =>  "$selector .navbar-brand",
            '@toggler' => "$selector .navbar-toggler",
        ];
    }
}
