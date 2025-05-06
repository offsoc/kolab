<?php

namespace Tests\Browser\Components;

use Laravel\Dusk\Browser;
use Laravel\Dusk\Component as BaseComponent;

class AclInput extends BaseComponent
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
        $browser->assertVisible($this->selector)
            ->assertVisible("{$this->selector} @input")
            ->assertVisible("{$this->selector} @add-btn")
            ->assertSelectHasOptions("{$this->selector} @mod-select", ['user', 'anyone'])
            ->assertSelectHasOptions("{$this->selector} @acl-select", ['read-only', 'read-write', 'full']);
    }

    /**
     * Get the element shortcuts for the component.
     *
     * @return array
     */
    public function elements()
    {
        return [
            '@add-btn' => '.input-group:first-child a.btn',
            '@input' => '.input-group:first-child input',
            '@acl-select' => '.input-group:first-child select.acl',
            '@mod-select' => '.input-group:first-child select.mod',
        ];
    }

    /**
     * Assert acl input content
     */
    public function assertAclValue($browser, array $list)
    {
        if (empty($list)) {
            $browser->assertMissing('.input-group:not(:first-child)');
            return;
        }

        foreach ($list as $idx => $value) {
            $selector = '.input-group:nth-child(' . ($idx + 2) . ')';
            [$ident, $acl] = preg_split('/\s*,\s*/', $value);

            $input = $ident == 'anyone' ? 'input:read-only' : 'input:not(:read-only)';

            $browser->assertVisible("{$selector} {$input}")
                ->assertVisible("{$selector} select")
                ->assertVisible("{$selector} a.btn")
                ->assertValue("{$selector} {$input}", $ident)
                ->assertSelected("{$selector} select", $acl);
        }
    }

    /**
     * Add acl entry
     */
    public function addAclEntry($browser, string $value)
    {
        [$ident, $acl] = preg_split('/\s*,\s*/', $value);

        $browser->select('@mod-select', $ident == 'anyone' ? 'anyone' : 'user')
            ->select('@acl-select', $acl);

        if ($ident == 'anyone') {
            $browser->assertValue('@input', '')->assertMissing('@input');
        } else {
            $browser->type('@input', $ident);
        }

        $browser->click('@add-btn')
            ->assertSelected('@mod-select', 'user')
            ->assertSelected('@acl-select', 'read-only')
            ->assertValue('@input', '');
    }

    /**
     * Remove acl entry
     */
    public function removeAclEntry($browser, int $num)
    {
        $selector = '.input-group:nth-child(' . ($num + 1) . ') a.btn';
        $browser->click($selector);
    }

    /**
     * Update acl entry
     */
    public function updateAclEntry($browser, int $num, $value)
    {
        [$ident, $acl] = preg_split('/\s*,\s*/', $value);

        $selector = '.input-group:nth-child(' . ($num + 1) . ')';

        $browser->select("{$selector} select.acl", $acl)
            ->type("{$selector} input", $ident);
    }

    /**
     * Assert an error message on the widget
     */
    public function assertFormError($browser, int $num, string $msg, bool $focused = false)
    {
        $selector = '.input-group:nth-child(' . ($num + 1) . ') input.is-invalid';

        $browser->waitFor($selector)
            ->assertSeeIn(' + .invalid-feedback', $msg);

        if ($focused) {
            $browser->assertFocused($selector);
        }
    }
}
