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
                ->submitLogon('reseller@kolabnow.com', 'reseller', true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-stats', 'Stats')
                ->click('@links .link-stats')
                ->on(new Stats())
                ->assertElementsCount('@container > div', 3)
                ->waitFor('@container #chart-users svg')
                ->assertSeeIn('@container #chart-users svg .title', 'Users - last 8 weeks')
                ->waitFor('@container #chart-users-all svg')
                ->assertSeeIn('@container #chart-users-all svg .title', 'All Users - last year')
                ->waitFor('@container #chart-discounts svg')
                ->assertSeeIn('@container #chart-discounts svg .title', 'Discounts');
        });
    }
}
