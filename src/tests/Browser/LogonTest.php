<?php

namespace Tests\Browser;

use Tests\Browser;
use Tests\Browser\Components\Menu;
use Tests\Browser\Components\Toast;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\UserProfile;
use Tests\TestCaseDusk;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class LogonTest extends TestCaseDusk
{

    /**
     * Test menu on logon page
     */
    public function testLogonMenu(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                });

            if ($browser->isDesktop()) {
                $browser->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'tos', 'login']);
                });
            } else {
                $browser->assertMissing('#footer-menu .navbar-nav');
            }

            $browser->assertSeeLink('Forgot password?')
                ->assertSeeLink('Webmail');
        });
    }

    /**
     * Test redirect to /login if user is unauthenticated
     */
    public function testRequiredAuth(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/dashboard');

            // Checks if we're really on the login page
            $browser->waitForLocation('/login')
                ->on(new Home());
        });
    }

    /**
     * Logon with wrong password/user test
     */
    public function testLogonWrongCredentials(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'wrong');

            // Error message
            $browser->assertToast(Toast::TYPE_ERROR, 'Invalid username or password.');

            // Checks if we're still on the logon page
            $browser->on(new Home());
        });
    }

    /**
     * Successful logon test
     */
    public function testLogonSuccessful(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true)
                // Checks if we're really on Dashboard page
                ->on(new Dashboard())
                ->assertVisible('@links a.link-profile')
                ->assertVisible('@links a.link-domains')
                ->assertVisible('@links a.link-users')
                ->assertVisible('@links a.link-wallet')
                ->assertVisible('@links a.link-webmail')
                ->within(new Menu(), function ($browser) {
                    $browser->assertMenuItems(['explore', 'blog', 'support', 'logout']);
                });

            if ($browser->isDesktop()) {
                $browser->within(new Menu('footer'), function ($browser) {
                    $browser->assertMenuItems(['explore', 'blog', 'support', 'tos', 'logout']);
                });
            } else {
                $browser->assertMissing('#footer-menu .navbar-nav');
            }

            $browser->assertUser('john@kolab.org');

            // Assert no "Account status" for this account
            $browser->assertMissing('@status');

            // Goto /domains and assert that the link on logo element
            // leads to the dashboard
            $browser->visit('/domains')
                ->waitForText('Domains')
                ->click('a.navbar-brand')
                ->on(new Dashboard());

            // Test that visiting '/' with logged in user does not open logon form
            // but "redirects" to the dashboard
            $browser->visit('/')
                ->waitForLocation('/dashboard')
                ->on(new Dashboard());
        });
    }

    /**
     * Logout test
     *
     * @depends testLogonSuccessful
     */
    public function testLogout(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->on(new Dashboard());

            // Click the Logout button
            $browser->within(new Menu(), function ($browser) {
                $browser->clickMenuItem('logout');
            });

            // We expect the logon page
            $browser->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
            });

            // Success toast message
            $browser->assertToast(Toast::TYPE_SUCCESS, 'Successfully logged out');
        });
    }

    /**
     * Logout by URL test
     */
    public function testLogoutByURL(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home())
                ->submitLogon('john@kolab.org', 'simple123', true);

            // Checks if we're really on Dashboard page
            $browser->on(new Dashboard());

            // Use /logout url, and expect the logon page
            $browser->visit('/logout')
                ->waitForLocation('/login')
                ->on(new Home());

            // with default menu
            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
            });

            // Success toast message
            $browser->assertToast(Toast::TYPE_SUCCESS, 'Successfully logged out');
        });
    }

    /**
     * Test 2-Factor Authentication
     *
     * @depends testLogoutByURL
     */
    public function test2FA(): void
    {
        $this->browse(function (Browser $browser) {
            // Test missing 2fa code
            $browser->on(new Home())
                ->type('@email-input', 'ned@kolab.org')
                ->type('@password-input', 'simple123')
                ->press('form button')
                ->waitFor('@second-factor-input.is-invalid + .invalid-feedback')
                ->assertSeeIn(
                    '@second-factor-input.is-invalid + .invalid-feedback',
                    'Second factor code is required.'
                )
                ->assertFocused('@second-factor-input')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error');

            // Test invalid code
            $browser->type('@second-factor-input', '123456')
                ->press('form button')
                ->waitUntilMissing('@second-factor-input.is-invalid')
                ->waitFor('@second-factor-input.is-invalid + .invalid-feedback')
                ->assertSeeIn(
                    '@second-factor-input.is-invalid + .invalid-feedback',
                    'Second factor code is invalid.'
                )
                ->assertFocused('@second-factor-input')
                ->assertToast(Toast::TYPE_ERROR, 'Form validation error');

            $code = \App\Auth\SecondFactor::code('ned@kolab.org');

            // Test valid (TOTP) code
            $browser->type('@second-factor-input', $code)
                ->press('form button')
                ->waitUntilMissing('@second-factor-input.is-invalid')
                ->waitForLocation('/dashboard')
                ->on(new Dashboard());
        });
    }

    /**
     * Test redirect to the requested page after logon
     *
     * @depends test2FA
     */
    public function testAfterLogonRedirect(): void
    {
        $this->browse(function (Browser $browser) {
            // User is logged in
            $browser->visit(new UserProfile());

            // Test redirect if the token is invalid
            $browser->script("localStorage.setItem('token', '123')");
            $browser->refresh()
                ->on(new Home())
                ->submitLogon('john@kolab.org', 'simple123', false)
                ->waitForLocation('/profile');
        });
    }
}
