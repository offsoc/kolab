<?php

namespace Tests\Browser\Admin;

use App\Discount;
use Tests\Browser;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Admin\User as UserPage;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UserTest extends TestCaseDusk
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();
        self::useAdminUrl();

        $john = $this->getTestUser('john@kolab.org');
        $john->setSettings([
                'phone' => '+48123123123',
        ]);

        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->balance = 0;
        $wallet->save();
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $john = $this->getTestUser('john@kolab.org');
        $john->setSettings([
                'phone' => null,
        ]);

        $wallet = $john->wallets()->first();
        $wallet->discount()->dissociate();
        $wallet->balance = 0;
        $wallet->save();

        parent::tearDown();
    }

    /**
     * Test user info page (unauthenticated)
     */
    public function testUserUnauth(): void
    {
        // Test that the page requires authentication
        $this->browse(function (Browser $browser) {
            $jack = $this->getTestUser('jack@kolab.org');
            $browser->visit('/user/' . $jack->id)->on(new Home());
        });
    }

    /**
     * Test user info page
     */
    public function testUserInfo(): void
    {
        $this->browse(function (Browser $browser) {
            $jack = $this->getTestUser('jack@kolab.org');
            $page = new UserPage($jack->id);

            $browser->visit(new Home())
                ->submitLogon('jeroen@jeroen.jeroen', 'jeroen', true)
                ->on(new Dashboard())
                ->visit($page)
                ->on($page);

            // Assert main info box content
            $browser->assertSeeIn('@user-info .card-title', $jack->email)
                ->with('@user-info form', function (Browser $browser) use ($jack) {
                    $browser->assertElementsCount('.row', 7)
                        ->assertSeeIn('.row:nth-child(1) label', 'Managed by')
                        ->assertSeeIn('.row:nth-child(1) #manager a', 'john@kolab.org')
                        ->assertSeeIn('.row:nth-child(2) label', 'ID (Created at)')
                        ->assertSeeIn('.row:nth-child(2) #userid', "{$jack->id} ({$jack->created_at})")
                        ->assertSeeIn('.row:nth-child(3) label', 'Status')
                        ->assertSeeIn('.row:nth-child(3) #status span.text-success', 'Active')
                        ->assertSeeIn('.row:nth-child(4) label', 'First name')
                        ->assertSeeIn('.row:nth-child(4) #first_name', 'Jack')
                        ->assertSeeIn('.row:nth-child(5) label', 'Last name')
                        ->assertSeeIn('.row:nth-child(5) #last_name', 'Daniels')
                        ->assertSeeIn('.row:nth-child(6) label', 'External email')
                        ->assertMissing('.row:nth-child(6) #external_email a')
                        ->assertSeeIn('.row:nth-child(7) label', 'Country')
                        ->assertSeeIn('.row:nth-child(7) #country', 'United States of America');
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 5);

            // Assert Finances tab
            $browser->assertSeeIn('@nav #tab-finances', 'Finances')
                ->with('@user-finances', function (Browser $browser) {
                    $browser->assertSeeIn('.card-title', 'Account balance')
                        ->assertSeeIn('.card-title .text-success', '0,00 CHF')
                        ->with('form', function (Browser $browser) {
                            $browser->assertElementsCount('.row', 1)
                                ->assertSeeIn('.row:nth-child(1) label', 'Discount')
                                ->assertSeeIn('.row:nth-child(1) #discount span', 'none');
                        });
                });

            // Assert Aliases tab
            $browser->assertSeeIn('@nav #tab-aliases', 'Aliases (1)')
                ->click('@nav #tab-aliases')
                ->whenAvailable('@user-aliases', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 1)
                        ->assertSeeIn('table tbody tr:first-child td:first-child', 'jack.daniels@kolab.org')
                        ->assertMissing('table tfoot');
                });

            // Assert Subscriptions tab
            $browser->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (3)')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 3)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'User Mailbox')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '4,44 CHF')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Storage Quota 2 GB')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:first-child', 'Groupware Features')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '5,55 CHF')
                        ->assertMissing('table tfoot');
                });

            // Assert Domains tab
            $browser->assertSeeIn('@nav #tab-domains', 'Domains (0)')
                ->click('@nav #tab-domains')
                ->with('@user-domains', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no domains in this account.');
                });

            // Assert Users tab
            $browser->assertSeeIn('@nav #tab-users', 'Users (0)')
                ->click('@nav #tab-users')
                ->with('@user-users', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no users in this account.');
                });
        });
    }

    /**
     * Test user info page (continue)
     *
     * @depends testUserInfo
     */
    public function testUserInfo2(): void
    {
        $this->browse(function (Browser $browser) {
            $john = $this->getTestUser('john@kolab.org');
            $page = new UserPage($john->id);
            $discount = Discount::where('code', 'TEST')->first();
            $wallet = $john->wallet();
            $wallet->discount()->associate($discount);
            $wallet->debit(2010);
            $wallet->save();

            // Click the managed-by link on Jack's page
            $browser->click('@user-info #manager a')
                ->on($page);

            // Assert main info box content
            $browser->assertSeeIn('@user-info .card-title', $john->email)
                ->with('@user-info form', function (Browser $browser) use ($john) {
                    $ext_email = $john->getSetting('external_email');

                    $browser->assertElementsCount('.row', 8)
                        ->assertSeeIn('.row:nth-child(1) label', 'ID (Created at)')
                        ->assertSeeIn('.row:nth-child(1) #userid', "{$john->id} ({$john->created_at})")
                        ->assertSeeIn('.row:nth-child(2) label', 'Status')
                        ->assertSeeIn('.row:nth-child(2) #status span.text-success', 'Active')
                        ->assertSeeIn('.row:nth-child(3) label', 'First name')
                        ->assertSeeIn('.row:nth-child(3) #first_name', 'John')
                        ->assertSeeIn('.row:nth-child(4) label', 'Last name')
                        ->assertSeeIn('.row:nth-child(4) #last_name', 'Doe')
                        ->assertSeeIn('.row:nth-child(5) label', 'Phone')
                        ->assertSeeIn('.row:nth-child(5) #phone', $john->getSetting('phone'))
                        ->assertSeeIn('.row:nth-child(6) label', 'External email')
                        ->assertSeeIn('.row:nth-child(6) #external_email a', $ext_email)
                        ->assertAttribute('.row:nth-child(6) #external_email a', 'href', "mailto:$ext_email")
                        ->assertSeeIn('.row:nth-child(7) label', 'Address')
                        ->assertSeeIn('.row:nth-child(7) #billing_address', $john->getSetting('billing_address'))
                        ->assertSeeIn('.row:nth-child(8) label', 'Country')
                        ->assertSeeIn('.row:nth-child(8) #country', 'United States of America');
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 5);

            // Assert Finances tab
            $browser->assertSeeIn('@nav #tab-finances', 'Finances')
                ->with('@user-finances', function (Browser $browser) {
                    $browser->assertSeeIn('.card-title', 'Account balance')
                        ->assertSeeIn('.card-title .text-danger', '-20,10 CHF')
                        ->with('form', function (Browser $browser) {
                            $browser->assertElementsCount('.row', 1)
                                ->assertSeeIn('.row:nth-child(1) label', 'Discount')
                                ->assertSeeIn('.row:nth-child(1) #discount span', '10% - Test voucher');
                        });
                });

            // Assert Aliases tab
            $browser->assertSeeIn('@nav #tab-aliases', 'Aliases (1)')
                ->click('@nav #tab-aliases')
                ->whenAvailable('@user-aliases', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 1)
                        ->assertSeeIn('table tbody tr:first-child td:first-child', 'john.doe@kolab.org')
                        ->assertMissing('table tfoot');
                });

            // Assert Subscriptions tab
            $browser->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (3)')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 3)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'User Mailbox')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '3,99 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Storage Quota 2 GB')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:first-child', 'Groupware Features')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '4,99 CHF/month¹')
                        ->assertMissing('table tfoot')
                        ->assertSeeIn('table + .hint', '¹ applied discount: 10% - Test voucher');
                });

            // Assert Domains tab
            $browser->assertSeeIn('@nav #tab-domains', 'Domains (1)')
                ->click('@nav #tab-domains')
                ->with('@user-domains table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 1)
                        ->assertSeeIn('tbody tr:nth-child(1) td:first-child a', 'kolab.org')
                        ->assertVisible('tbody tr:nth-child(1) td:first-child svg.text-success')
                        ->assertMissing('tfoot');
                });

            // Assert Users tab
            $browser->assertSeeIn('@nav #tab-users', 'Users (3)')
                ->click('@nav #tab-users')
                ->with('@user-users table', function (Browser $browser) {
                    $browser->assertElementsCount('tbody tr', 3)
                        ->assertSeeIn('tbody tr:nth-child(1) td:first-child a', 'jack@kolab.org')
                        ->assertVisible('tbody tr:nth-child(1) td:first-child svg.text-success')
                        ->assertSeeIn('tbody tr:nth-child(2) td:first-child a', 'joe@kolab.org')
                        ->assertVisible('tbody tr:nth-child(2) td:first-child svg.text-success')
                        ->assertSeeIn('tbody tr:nth-child(3) td:first-child a', 'ned@kolab.org')
                        ->assertVisible('tbody tr:nth-child(3) td:first-child svg.text-success')
                        ->assertMissing('tfoot');
                });
        });

        // Now we go to Ned's info page, he's a controller on John's wallet
        $this->browse(function (Browser $browser) {
            $ned = $this->getTestUser('ned@kolab.org');
            $page = new UserPage($ned->id);

            $browser->click('@user-users tbody tr:nth-child(3) td:first-child a')
                ->on($page);

            // Assert main info box content
            $browser->assertSeeIn('@user-info .card-title', $ned->email)
                ->with('@user-info form', function (Browser $browser) use ($ned) {
                    $browser->assertSeeIn('.row:nth-child(2) label', 'ID (Created at)')
                        ->assertSeeIn('.row:nth-child(2) #userid', "{$ned->id} ({$ned->created_at})");
                });

            // Some tabs are loaded in background, wait a second
            $browser->pause(500)
                ->assertElementsCount('@nav a', 5);

            // Assert Finances tab
            $browser->assertSeeIn('@nav #tab-finances', 'Finances')
                ->with('@user-finances', function (Browser $browser) {
                    $browser->assertSeeIn('.card-title', 'Account balance')
                        ->assertSeeIn('.card-title .text-success', '0,00 CHF')
                        ->with('form', function (Browser $browser) {
                            $browser->assertElementsCount('.row', 1)
                                ->assertSeeIn('.row:nth-child(1) label', 'Discount')
                                ->assertSeeIn('.row:nth-child(1) #discount span', 'none');
                        });
                });

            // Assert Aliases tab
            $browser->assertSeeIn('@nav #tab-aliases', 'Aliases (0)')
                ->click('@nav #tab-aliases')
                ->whenAvailable('@user-aliases', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'This user has no email aliases.');
                });

            // Assert Subscriptions tab, we expect John's discount here
            $browser->assertSeeIn('@nav #tab-subscriptions', 'Subscriptions (5)')
                ->click('@nav #tab-subscriptions')
                ->with('@user-subscriptions', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 5)
                        ->assertSeeIn('table tbody tr:nth-child(1) td:first-child', 'User Mailbox')
                        ->assertSeeIn('table tbody tr:nth-child(1) td:last-child', '3,99 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:first-child', 'Storage Quota 2 GB')
                        ->assertSeeIn('table tbody tr:nth-child(2) td:last-child', '0,00 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:first-child', 'Groupware Features')
                        ->assertSeeIn('table tbody tr:nth-child(3) td:last-child', '4,99 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(4) td:first-child', 'Activesync')
                        ->assertSeeIn('table tbody tr:nth-child(4) td:last-child', '0,90 CHF/month¹')
                        ->assertSeeIn('table tbody tr:nth-child(5) td:first-child', '2-Factor Authentication')
                        ->assertSeeIn('table tbody tr:nth-child(5) td:last-child', '0,00 CHF/month¹')
                        ->assertMissing('table tfoot')
                        ->assertSeeIn('table + .hint', '¹ applied discount: 10% - Test voucher');
                });

            // We don't expect John's domains here
            $browser->assertSeeIn('@nav #tab-domains', 'Domains (0)')
                ->click('@nav #tab-domains')
                ->with('@user-domains', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no domains in this account.');
                });

            // We don't expect John's users here
            $browser->assertSeeIn('@nav #tab-users', 'Users (0)')
                ->click('@nav #tab-users')
                ->with('@user-users', function (Browser $browser) {
                    $browser->assertElementsCount('table tbody tr', 0)
                        ->assertSeeIn('table tfoot tr td', 'There are no users in this account.');
                });
        });
    }
}
