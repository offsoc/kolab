<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\TestCaseDusk;

class ErrorTest extends TestCaseDusk
{
    /**
     * Test error 404 page on unknown route
     *
     * @return void
     */
    public function testError404Page()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/unknown')
                ->waitFor('#app > #error-page')
                ->assertVisible('#app > #header-menu')
                ->assertVisible('#app > #footer-menu');

            $this->assertSame('404', $browser->text('#error-page .code'));
            $this->assertSame('Not found', $browser->text('#error-page .message'));
        });

        $this->browse(function (Browser $browser) {
            $browser->visit('/login/unknown')
                ->waitFor('#app > #error-page')
                ->assertVisible('#app > #header-menu')
                ->assertVisible('#app > #footer-menu');

            $this->assertSame('404', $browser->text('#error-page .code'));
            $this->assertSame('Not found', $browser->text('#error-page .message'));
        });
    }
}
