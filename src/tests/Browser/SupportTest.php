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
        $this->browse(static function (Browser $browser) {
            $browser->withConfig(['app.support_email' => ""])
                ->visit('/')
                ->within(new Menu(), static function ($browser) {
                    $browser->clickMenuItem('support');
                })
                ->waitFor('#support')
                ->assertElementsCount('.card-title', 2)
                ->with('.row .col:last-child', static function ($card) {
                    $card->assertSeeIn('.card-title', 'Contact Support')
                        ->assertSeeIn('.btn-primary', 'Contact Support')
                        ->click('.btn-primary');
                })
                ->with(new Dialog('#support-dialog'), static function (Browser $browser) {
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
                ->with('.row .col:last-child', static function ($card) {
                    $card->click('.btn-primary');
                })
                ->with(new Dialog('#support-dialog'), static function (Browser $browser) {
                    $browser->assertSeeIn('@title', 'Contact Support')
                        ->assertFocused('#support-user')
                        ->assertValue('#support-email', 'email@address.com')
                        ->assertValue('#support-summary', 'Summary')
                        ->assertValue('#support-body', 'Body')
                        ->click('@button-action');
                })
                ->assertToast(Toast::TYPE_ERROR, 'Failed to submit the support request')
                ->assertVisible('#support-dialog');
        });
    }

    /**
     * Test disabled support contact form
     */
    public function testNoSupportForm(): void
    {
        $this->browse(static function (Browser $browser) {
            $browser->withConfig(['app.support_email' => null])
                ->visit('/')
                ->within(new Menu(), static function ($browser) {
                    $browser->clickMenuItem('support');
                })
                ->waitFor('#support')
                ->assertElementsCount('.card-title', 1)
                ->assertSeeIn('.card-title', 'Documentation')
                ->assertSeeIn('.btn-primary', 'Search Knowledgebase');
        });
    }
}
