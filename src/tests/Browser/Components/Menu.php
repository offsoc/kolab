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
     * @param \Tests\Browser $browser
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
     * @param \Tests\Browser $browser
     * @param array          $items   List of menu items
     * @param string         $active  Expected active item
     *
     * @return void
     */
    public function assertMenuItems($browser, array $items, string $active = null)
    {
        // On mobile the links are not visible, show them first (wait for transition)
        if (!$browser->isDesktop()) {
            $browser->click('@toggler')->waitFor('.navbar-collapse.show');
        }

        foreach ($items as $item) {
            $browser->assertVisible('.link-' . $item);
        }

        // Check number of items, to make sure there's no extra items
        PHPUnit::assertCount(count($items), $browser->elements('li'));

        if ($active) {
            $browser->assertPresent(".link-{$active}.active");
        }

        if (!$browser->isDesktop()) {
            $browser->click('@toggler')->waitUntilMissing('.navbar-collapse.show');
        }
    }

    /**
     * Click menu link.
     *
     * @param \Tests\Browser $browser The browser object
     * @param string         $name    Menu item name
     *
     * @return void
     */
    public function clickMenuItem($browser, string $name)
    {
        // On mobile the links are not visible, show them first (wait for transition)
        if ($browser->isPhone()) {
            $browser->click('@toggler')->waitFor('.navbar-collapse.show');
        }

        $browser->click('.link-' . $name);

        if ($browser->isPhone()) {
            $browser->waitUntilMissing('.navbar-collapse.show');
        }
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
            '@lang' =>  ".nav-link.link-lang",
        ];
    }
}
