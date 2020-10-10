<?php

namespace Tests\Feature\Browser;

use Tests\Browser;
use Tests\Browser\Components\Dialog;
use Tests\Browser\Components\ListInput;
use Tests\Browser\Components\QuotaInput;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserInfo;
use Tests\Browser\Pages\UserList;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class UserListPageTest extends TestCaseDusk
{
    /**
     * Verify that the page with a list of users requires authentication
     */
    public function testUserListPageRequiresAuthentication(): void
    {
        // Test that the page requires authentication
        $this->browse(
            function (Browser $browser) {
                $browser->visit('/users')->on(new Home());
                $browser->screenshot(__LINE__);
            }
        );
    }

    /**
     * Test users list page as an owner of a domain.
     */
    public function testUsersListPageAsOwner(): void
    {
        // Test that the page requires authentication
        $this->browse(
            function (Browser $browser) {
                $browser->visit(new Home());
                $browser->screenshot(__LINE__);
                $browser->submitLogon($this->domainOwner->email, $this->userPassword, true);
                $browser->screenshot(__LINE__);
                $browser->on(new Dashboard());
                $browser->screenshot(__LINE__);
                $browser->assertSeeIn('@links .link-users', 'User accounts');
                $browser->click('@links .link-users');
                $browser->screenshot(__LINE__);
                $browser->on(new UserList());
                $browser->whenAvailable(
                    '@table',
                    function (Browser $browser) {
                        $browser->waitFor('tbody tr');
                        $browser->assertElementsCount('tbody tr', sizeof($this->domainUsers));
                        $browser->screenshot(__LINE__);

                        foreach ($this->domainUsers as $user) {
                            $arrayPosition = array_search($user, $this->domainUsers);
                            $listPosition = $arrayPosition + 1;

                            $browser->assertSeeIn("tbody tr:nth-child({$listPosition}) a", $user->email);
                            $browser->assertVisible("tbody tr:nth-child({$listPosition}) button.button-delete");
                        }

                        $browser->assertMissing('tfoot');
                    }
                );
            }
        );
    }

    /**
     * Test users list page as a user of a domain.
     */
    public function testUsersListPageAsUser(): void
    {
        // Test that the page requires authentication
        $this->browse(
            function (Browser $browser) {
                $browser->visit(new Home());
                $browser->screenshot(__LINE__);
                $browser->submitLogon($this->jack->email, $this->userPassword, true);
                $browser->on(new Dashboard());
                $browser->screenshot(__LINE__);
                $browser->assertDontSee('@links .link-users');
            }
        );
    }

    /**
     * Test users list page as an additional controller on the wallet for a domain.
     */
    public function testUsersListPageAsController(): void
    {
        $this->browse(
            function (Browser $browser) {
                $browser->visit(new Home());
                $browser->submitLogon($this->jane->email, $this->userPassword, true);
                $browser->on(new Dashboard());
                $browser->assertSeeIn('@links .link-users', 'User accounts');
                $browser->click('@links .link-users');
                $browser->on(new UserList());
                $browser->whenAvailable(
                    '@table',
                    function (Browser $browser) {
                        $browser->waitFor('tbody tr');
                        $browser->assertElementsCount('tbody tr', sizeof($this->domainUsers));

                        foreach ($this->domainUsers as $user) {
                            $arrayPosition = array_search($user, $this->domainUsers);
                            $listPosition = $arrayPosition + 1;

                            $browser->assertSeeIn("tbody tr:nth-child({$listPosition}) a", $user->email);
                            $browser->assertVisible("tbody tr:nth-child({$listPosition}) button.button-delete");
                        }

                        $browser->assertMissing('tfoot');
                    }
                );
            }
        );
    }
}
