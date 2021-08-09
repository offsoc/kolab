<?php

namespace Tests\Browser\Reseller;

use Tests\Browser;
use Tests\Browser\Pages\Admin\Stats;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class StatsTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useResellerUrl();
    }

    /**
     * Test Stats page (unauthenticated)
     */
    public function testStatsUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $browser->visit('/stats')->on(new Home());
        });
    }

    /**
     * Test Stats page
     */
    public function testStats(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('reseller@' . \config('app.domain'), \App\Utils::generatePassphrase(), true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-stats', 'Stats')
                ->click('@links .link-stats')
                ->on(new Stats())
                ->assertElementsCount('@container > div', 3)
                ->waitForTextIn('@container #chart-users svg .title', 'Users - last 8 weeks')
                ->waitForTextIn('@container #chart-users-all svg .title', 'All Users - last year')
                ->waitForTextIn('@container #chart-discounts svg .title', 'Discounts');
        });
    }
}
