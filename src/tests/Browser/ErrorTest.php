<?php

namespace Tests\Browser;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;

class ErrorTest extends DuskTestCase
{

    /**
     * Test error 404 page on unknown route
     *
     * @return void
     */
    public function testError404Page()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/unknown');

            $browser->waitFor('#app > #error-page');
            $browser->assertVisible('#app > #primary-menu');
            $this->assertSame('404', $browser->text('#error-page .code'));
            $this->assertSame('Not Found', $browser->text('#error-page .message'));
        });

        $this->browse(function (Browser $browser) {
            $browser->visit('/login/unknown');

            $browser->waitFor('#app > #error-page');
            $browser->assertVisible('#app > #primary-menu');
            $this->assertSame('404', $browser->text('#error-page .code'));
            $this->assertSame('Not Found', $browser->text('#error-page .message'));
        });
    }
}
