<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\Browser\Pages\Signup;
use Tests\TestCaseDusk;

class FaqTest extends TestCaseDusk
{
    /**
     * Test FAQ widget
     */
    public function testFaq(): void
    {
        $this->browse(static function (Browser $browser) {
            $browser->visit(new Signup())
                ->whenAvailable('#faq', static function ($browser) {
                    $browser->assertSeeIn('h5', 'FAQ')
                        ->assertElementsCount('ul > li', 1)
                        ->assertSeeIn('li:last-child a', 'Need support?')
                        ->click('li:last-child a');
                })
                ->waitForLocation('/support');
        });
    }
}
