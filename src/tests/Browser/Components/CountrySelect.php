<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Component as BaseComponent;
use PHPUnit\Framework\Assert as PHPUnit;

class CountrySelect extends BaseComponent
{
    protected $selector;
    protected $countries;


    public function __construct($selector)
    {
        $this->selector = $selector;
        $this->countries = include resource_path('countries.php');
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
            ->assertMissing("{$this->selector} @dialog");
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@link' => 'a',
            '@dialog' => '.modal',
        ];
    }

    /**
     * Assert selected countries on the map and in the link content
     */
    public function assertCountries($browser, array $list)
    {
        if (empty($list)) {
            $browser->assertSeeIn('@link', 'No restrictions')
                ->click('@link')
                ->with(new Dialog('@dialog'), function ($browser) {
                    $browser->waitFor('.world-map > svg')
                        ->assertElementsCount('.world-map [aria-selected="true"]', 1)
                        ->click('@button-cancel');
                });

            return;
        }

        $browser->assertSeeIn('@link', $this->countriesText($list))
            ->click('@link')
            ->with(new Dialog('@dialog'), function ($browser) use ($list) {
                $browser->waitFor('.world-map > svg')
                    ->assertElementsCount('.world-map [aria-selected="true"]', count($list) + 1);

                foreach ($list as $code) {
                    $browser->assertVisible('.world-map [cc="' . $code . '"]');
                }

                $browser->click('@button-cancel');
            });
    }

    /**
     * Update selected countries
     */
    public function setCountries($browser, array $list)
    {
        $browser->click('@link')
            ->with(new Dialog('@dialog'), function ($browser) use ($list) {
                $browser->waitFor('.world-map > svg')
                    ->execScript("\$('.world-map [cc]').attr('aria-selected', '')");

                foreach ($list as $code) {
                    $browser->click('.world-map [cc="' . $code . '"]');
                }

                $browser->click('@button-action');
            });
    }

    /**
     * Get textual list of country names
     */
    protected function countriesText(array $list): string
    {
        $names = [];
        foreach ($list as $code) {
            $names[] = $this->countries[$code][1];
        }

        return implode(', ', $names);
    }
}
