<?php

namespace Tests\Browser\Reseller;

use App\Utils;
use Tests\Browser;
use Tests\Browser\Pages\Admin\Stats;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;

class StatsTest extends TestCaseDusk
{
    protected function setUp(): void
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
        $this->browse(static function (Browser $browser) {
            $browser->visit('/stats')->on(new Home());
        });
    }

    /**
     * Test Stats page
     */
    public function testStats(): void
    {
        $this->browse(static function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('reseller@' . \config('app.domain'), Utils::generatePassphrase(), true)
                ->on(new Dashboard())
                ->assertSeeIn('@links .link-stats', 'Stats')
                ->click('@links .link-stats')
                ->on(new Stats())
                ->assertElementsCount('@container > div', 5)
                ->waitForTextIn('@container #chart-users svg .title', 'Users - last 8 weeks')
                ->waitForTextIn('@container #chart-users-all svg .title', 'All Users - last year')
                ->waitForTextIn('@container #chart-payers svg .title', 'Payers - last year')
                ->waitForTextIn('@container #chart-discounts svg .title', 'Discounts')
                ->waitForTextIn('@container #chart-vouchers svg .title', 'Vouchers');
        });
    }
}
