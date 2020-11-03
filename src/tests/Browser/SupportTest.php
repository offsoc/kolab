<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\TestCaseDusk;

class SupportTest extends TestCaseDusk
{
    /**
     * Test support contact form
     */
    public function testSupportForm(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/')
                ->within(new Menu(), function ($browser) {
                    $browser->clickMenuItem('support');
                })
                ->waitFor('#support')
                ->assertSeeIn('.card-title', 'Contact Support')
                ->assertSeeIn('a.btn-info', 'Contact Support')
                ->click('a.btn-info')
                ->with(new Dialog('#support-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Contact Support')
                        ->assertFocused('#support-user')
                        ->assertSeeIn('@button-cancel', 'Cancel')
                        ->assertSeeIn('@button-action', 'Submit')
                        ->assertVisible('#support-name')
                        ->assertVisible('#support-email')
                        ->assertVisible('#support-summary')
                        ->assertVisible('#support-body')
                        ->type('#support-email', 'email@address.com')
                        ->type('#support-summary', 'Summary')
                        ->type('#support-body', 'Body')
                        ->click('@button-cancel');
                })
                ->assertMissing('#support-dialog')
                ->click('a.btn-info')
                ->with(new Dialog('#support-dialog'), function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Contact Support')
                        ->assertFocused('#support-user')
                        ->assertValue('#support-email', 'email@address.com')
                        ->assertValue('#support-summary', 'Summary')
                        ->assertValue('#support-body', 'Body')
                        ->click('@button-action');
                })
                // Note: This line assumes SUPPORT_EMAIL is not set in config
                ->assertToast(Toast::TYPE_ERROR, 'Failed to submit the support request')
                ->assertVisible('#support-dialog');
        });
    }
}
