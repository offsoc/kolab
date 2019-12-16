<?php

namespace Tests\Browser;

use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Signup;

use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class SignupTest extends DuskTestCase
{

    /**
     * Test 1st step of the signup process
     *
     * @return void
     */
    public function testSignupStep1()
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Signup());

            $browser->assertVisible('@step1');

            // Submit empty form
            // Both Step 1 inputs are required, so after pressing Submit
            // we expect focus to be moved to the first input
            $browser->with('@step1', function ($step) {
                $step->click('[type=submit]');
                $step->assertFocused('#signup_name');
            });

            // Submit invalid email
            // We expect email input to have is-invalid class added, with .invalid-feedback element
            $browser->with('@step1', function ($step) use ($browser) {
                $step->type('#signup_name', 'Test User');
                $step->type('#signup_email', '@test');
                $step->click('[type=submit]');

                $step->waitFor('#signup_email.is-invalid');
                $step->waitFor('#signup_email + .invalid-feedback');
                $browser->waitFor('.toast-error');
            });

            // Submit valid data
            // We expect error state on email input to be removed, and Step 2 form visible
            $browser->with('@step1', function ($step) {
                $step->type('#signup_name', 'Test User');
                $step->type('#signup_email', 'BrowserSignupTestUser1@kolab.org');
                $step->click('[type=submit]');

                $step->assertMissing('#signup_email.is-invalid');
                $step->assertMissing('#signup_email + .invalid-feedback');
            });

            $browser->waitFor('@step2');
            $browser->assertMissing('@step1');
        });
    }

    /**
     * Test 2nd Step of the signup process
     *
     * @depends testSignupStep1
     * @return void
     */
    public function testSignupStep2()
    {
        $this->browse(function (Browser $browser) {
            $browser->assertVisible('@step2');

            // Here we expect one text input, Back and Continue buttons
            $browser->with('@step2', function ($step) {
                $step->assertVisible('#signup_code');
                $step->assertVisible('[type=button]');
                $step->assertVisible('[type=submit]');
            });

            $browser->click('@step2 [type=button]');
            $browser->waitFor('@step1');
            $browser->assertMissing('@step2');

            // Submit valid Step 1 data (again)
            $browser->with('@step1', function ($step) {
                $step->type('#signup_name', 'Test User');
                $step->type('#signup_email', 'BrowserSignupTestUser1@kolab.org');
                $step->click('[type=submit]');
            });

            $browser->waitFor('@step2');
            $browser->assertMissing('@step1');

            // Submit invalid code
            // We expect code input to have is-invalid class added, with .invalid-feedback element
            $browser->with('@step2', function ($step) use ($browser) {
                $step->type('#signup_code', 'XXXXX');
                $step->click('[type=submit]');

                $step->waitFor('#signup_code.is-invalid');
                $step->waitFor('#signup_code + .invalid-feedback');
                $browser->waitFor('.toast-error');
            });

            // Submit valid code
            // We expect error state on code input to be removed, and Step 3 form visible
            $browser->with('@step1', function ($step) {
                // TODO: check existence of the signup code in ajax response
                //       Get the short_code from database

                $step->type('#signup_code', 'YYYYYY');
                $step->click('[type=submit]');

                $step->assertMissing('#signup_code.is-invalid');
                $step->assertMissing('#signup_code + .invalid-feedback');
            });

            $browser->waitFor('@step3');
            $browser->assertMissing('@step2');
        });
    }

    /**
     * Test 3rd Step of the signup process
     *
     * @depends testSignupStep2
     * @return void
     */
    public function testSignupStep3()
    {
        $this->browse(function (Browser $browser) {
            $browser->assertVisible('@step3');
        });
    }
}
