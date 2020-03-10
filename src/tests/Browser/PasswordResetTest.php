<?php

namespace Tests\Browser;

use App\User;
use App\VerificationCode;
use Tests\Browser;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Home;
use Tests\Browser\Pages\PasswordReset;
use Tests\DuskTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class PasswordResetTest extends DuskTestCase
{
    /**
     * {@inheritDoc}
     */
    public function setUp(): void
    {
        parent::setUp();

        $this->deleteTestUser('passwordresettestdusk@' . \config('app.domain'));
    }

    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        $this->deleteTestUser('passwordresettestdusk@' . \config('app.domain'));

        parent::tearDown();
    }

    /**
     * Test the link from logon to password-reset page
     */
    public function testPasswordResetLinkOnLogon(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Home());

            $browser->assertSeeLink('Forgot password?');
            $browser->clickLink('Forgot password?');

            $browser->on(new PasswordReset());
            $browser->assertVisible('@step1');
        });
    }

    /**
     * Test 1st step of password-reset
     */
    public function testPasswordResetStep1(): void
    {
        $user = $this->getTestUser('passwordresettestdusk@' . \config('app.domain'));
        $user->setSetting('external_email', 'external@domain.tld');

        $this->browse(function (Browser $browser) {
            $browser->visit(new PasswordReset());

            $browser->assertVisible('@step1');

            // Here we expect email input and submit button
            $browser->with('@step1', function ($step) {
                $step->assertVisible('#reset_email');
                $step->assertFocused('#reset_email');
                $step->assertVisible('[type=submit]');
            });

            // Submit empty form
            $browser->with('@step1', function ($step) {
                $step->click('[type=submit]');
                $step->assertFocused('#reset_email');
            });

            // Submit invalid email
            // We expect email input to have is-invalid class added, with .invalid-feedback element
            $browser->with('@step1', function ($step) use ($browser) {
                $step->type('#reset_email', '@test');
                $step->click('[type=submit]');

                $step->waitFor('#reset_email.is-invalid');
                $step->waitFor('#reset_email + .invalid-feedback');
                $browser->waitFor('.toast-error');
                $browser->click('.toast-error'); // remove the toast
            });

            // Submit valid data
            $browser->with('@step1', function ($step) {
                $step->type('#reset_email', 'passwordresettestdusk@' . \config('app.domain'));
                $step->click('[type=submit]');

                $step->assertMissing('#reset_email.is-invalid');
                $step->assertMissing('#reset_email + .invalid-feedback');
            });

            $browser->waitUntilMissing('@step2 #reset_code[value=""]');
            $browser->waitFor('@step2');
            $browser->assertMissing('@step1');
        });
    }

    /**
     * Test 2nd Step of the password reset process
     *
     * @depends testPasswordResetStep1
     */
    public function testPasswordResetStep2(): void
    {
        $user = $this->getTestUser('passwordresettestdusk@' . \config('app.domain'));
        $user->setSetting('external_email', 'external@domain.tld');

        $this->browse(function (Browser $browser) {
            $browser->assertVisible('@step2');

            // Here we expect one text input, Back and Continue buttons
            $browser->with('@step2', function ($step) {
                $step->assertVisible('#reset_short_code');
                $step->assertFocused('#reset_short_code');
                $step->assertVisible('[type=button]');
                $step->assertVisible('[type=submit]');
            });

            // Test Back button functionality
            $browser->click('@step2 [type=button]');
            $browser->waitFor('@step1');
            $browser->assertFocused('@step1 #reset_email');
            $browser->assertMissing('@step2');

            // Submit valid Step 1 data (again)
            $browser->with('@step1', function ($step) {
                $step->type('#reset_email', 'passwordresettestdusk@' . \config('app.domain'));
                $step->click('[type=submit]');
            });

            $browser->waitFor('@step2');
            $browser->assertMissing('@step1');

            // Submit invalid code
            // We expect code input to have is-invalid class added, with .invalid-feedback element
            $browser->with('@step2', function ($step) use ($browser) {
                $step->type('#reset_short_code', 'XXXXX');
                $step->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertVisible('#reset_short_code.is-invalid');
                $step->assertVisible('#reset_short_code + .invalid-feedback');
                $step->assertFocused('#reset_short_code');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit valid code
            // We expect error state on code input to be removed, and Step 3 form visible
            $browser->with('@step2', function ($step) {
                // Get the code and short_code from database
                // FIXME: Find a nice way to read javascript data without using hidden inputs
                $code = $step->value('#reset_code');

                $this->assertNotEmpty($code);

                $code = VerificationCode::find($code);

                $step->type('#reset_short_code', $code->short_code);
                $step->click('[type=submit]');

                $step->assertMissing('#reset_short_code.is-invalid');
                $step->assertMissing('#reset_short_code + .invalid-feedback');
            });

            $browser->waitFor('@step3');
            $browser->assertMissing('@step2');
        });
    }

    /**
     * Test 3rd Step of the password reset process
     *
     * @depends testPasswordResetStep2
     */
    public function testPasswordResetStep3(): void
    {
        $user = $this->getTestUser('passwordresettestdusk@' . \config('app.domain'));
        $user->setSetting('external_email', 'external@domain.tld');

        $this->browse(function (Browser $browser) {
            $browser->assertVisible('@step3');

            // Here we expect 2 text inputs, Back and Continue buttons
            $browser->with('@step3', function ($step) {
                $step->assertVisible('#reset_password');
                $step->assertVisible('#reset_confirm');
                $step->assertVisible('[type=button]');
                $step->assertVisible('[type=submit]');
                $step->assertFocused('#reset_password');
            });

            // Test Back button
            $browser->click('@step3 [type=button]');
            $browser->waitFor('@step2');
            $browser->assertFocused('@step2 #reset_short_code');
            $browser->assertMissing('@step3');
            $browser->assertMissing('@step1');

            // TODO: Test form reset when going back

            // Because the verification code is removed in tearDown()
            // we'll start from the beginning (Step 1)
            $browser->click('@step2 [type=button]');
            $browser->waitFor('@step1');
            $browser->assertFocused('@step1 #reset_email');
            $browser->assertMissing('@step3');
            $browser->assertMissing('@step2');

            // Submit valid data
            $browser->with('@step1', function ($step) {
                $step->type('#reset_email', 'passwordresettestdusk@' . \config('app.domain'));
                $step->click('[type=submit]');
            });

            $browser->waitFor('@step2');
            $browser->waitUntilMissing('@step2 #reset_code[value=""]');

            // Submit valid code again
            $browser->with('@step2', function ($step) {
                $code = $step->value('#reset_code');

                $this->assertNotEmpty($code);

                $code = VerificationCode::find($code);

                $step->type('#reset_short_code', $code->short_code);
                $step->click('[type=submit]');
            });

            $browser->waitFor('@step3');

            // Submit invalid data
            $browser->with('@step3', function ($step) use ($browser) {
                $step->assertFocused('#reset_password');

                $step->type('#reset_password', '12345678');
                $step->type('#reset_confirm', '123456789');

                $step->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertVisible('#reset_password.is-invalid');
                $step->assertVisible('#reset_password + .invalid-feedback');
                $step->assertFocused('#reset_password');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit valid data
            $browser->with('@step3', function ($step) {
                $step->type('#reset_confirm', '12345678');

                $step->click('[type=submit]');
            });

            $browser->waitUntilMissing('@step3');

            // At this point we should be auto-logged-in to dashboard
            $browser->on(new Dashboard());

            // FIXME: Is it enough to be sure user is logged in?
        });
    }
}
