<?php

namespace Tests\Browser;

use App\SignupCode;
use App\User;
use Tests\Browser\Components\Menu;
use Tests\Browser\Pages\Dashboard;
use Tests\Browser\Pages\Signup;
use Tests\DuskTestCase;
use Laravel\Dusk\Browser;
use Illuminate\Foundation\Testing\DatabaseMigrations;

class SignupTest extends DuskTestCase
{
    /**
     * {@inheritDoc}
     *
     * @return void
     */
    public function tearDown(): void
    {
        User::where('email', 'signuptestdusk@' . \config('app.domain'))->delete();
    }

    /**
     * Test signup code verification with a link
     *
     * @return void
     */
    public function testSignupCodeByLink()
    {
        // Test invalid code (invalid format)
        $this->browse(function (Browser $browser) {
            // Register Signup page element selectors we'll be using
            $browser->onWithoutAssert(new Signup());

            // TODO: Test what happens if user is logged in

            $browser->visit('/signup/invalid-code');

            // TODO: According to https://github.com/vuejs/vue-router/issues/977
            // it is not yet easily possible to display error page component (route)
            // without changing the URL
            // TODO: Instead of css selector we should probably define page/component
            // and use it instead
            $browser->waitFor('#error-page');
        });

        // Test invalid code (valid format)
        $this->browse(function (Browser $browser) {
            $browser->visit('/signup/XXXXX-code');

            // FIXME: User will not be able to continue anyway, so we should
            //        either display 1st step or 404 error page
            $browser->waitFor('@step1');
            $browser->waitFor('.toast-error');
            $browser->click('.toast-error'); // remove the toast
        });

        // Test valid code
        $this->browse(function (Browser $browser) {
            $code = SignupCode::create([
                    'data' => [
                        'email' => 'User@example.org',
                        'name' => 'User Name',
                        'plan' => 'individual',
                    ]
            ]);

            $browser->visit('/signup/' . $code->short_code . '-' . $code->code);

            $browser->waitFor('@step3');
            $browser->assertMissing('@step1');
            $browser->assertMissing('@step2');

            // FIXME: Find a nice way to read javascript data without using hidden inputs
            $this->assertSame($code->code, $browser->value('@step2 #signup_code'));

            // TODO: Test if the signup process can be completed
        });
    }

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

            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                $browser->assertActiveItem('signup');
            });

            // Here we expect two text inputs and Continue
            $browser->with('@step1', function ($step) {
                $step->assertVisible('#signup_name');
                $step->assertFocused('#signup_name');
                $step->assertVisible('#signup_email');
                $step->assertVisible('[type=submit]');
            });

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
                $browser->click('.toast-error'); // remove the toast
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

            $browser->waitUntilMissing('@step2 #signup_code[value=""]');
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
                $step->assertVisible('#signup_short_code');
                $step->assertFocused('#signup_short_code');
                $step->assertVisible('[type=button]');
                $step->assertVisible('[type=submit]');
            });

            // Test Back button functionality
            $browser->click('@step2 [type=button]');
            $browser->waitFor('@step1');
            $browser->assertFocused('@step1 #signup_name');
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
                $step->type('#signup_short_code', 'XXXXX');
                $step->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertVisible('#signup_short_code.is-invalid');
                $step->assertVisible('#signup_short_code + .invalid-feedback');
                $step->assertFocused('#signup_short_code');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit valid code
            // We expect error state on code input to be removed, and Step 3 form visible
            $browser->with('@step2', function ($step) {
                // Get the code and short_code from database
                // FIXME: Find a nice way to read javascript data without using hidden inputs
                $code = $step->value('#signup_code');

                $this->assertNotEmpty($code);

                $code = SignupCode::find($code);

                $step->type('#signup_short_code', $code->short_code);
                $step->click('[type=submit]');

                $step->assertMissing('#signup_short_code.is-invalid');
                $step->assertMissing('#signup_short_code + .invalid-feedback');
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

            // Here we expect 3 text inputs, Back and Continue buttons
            $browser->with('@step3', function ($step) {
                $step->assertVisible('#signup_login');
                $step->assertVisible('#signup_password');
                $step->assertVisible('#signup_confirm');
                $step->assertVisible('select#signup_domain');
                $step->assertVisible('[type=button]');
                $step->assertVisible('[type=submit]');
                $step->assertFocused('#signup_login');
                $step->assertValue('select#signup_domain', \config('app.domain'));
                $step->assertValue('#signup_login', '');
                $step->assertValue('#signup_password', '');
                $step->assertValue('#signup_confirm', '');

                // TODO: Test domain selector
            });

            // Test Back button
            $browser->click('@step3 [type=button]');
            $browser->waitFor('@step2');
            $browser->assertFocused('@step2 #signup_short_code');
            $browser->assertMissing('@step3');

            // TODO: Test form reset when going back

            // Submit valid code again
            $browser->with('@step2', function ($step) {
                $code = $step->value('#signup_code');

                $this->assertNotEmpty($code);

                $code = SignupCode::find($code);

                $step->type('#signup_short_code', $code->short_code);
                $step->click('[type=submit]');
            });

            $browser->waitFor('@step3');

            // Submit invalid data
            $browser->with('@step3', function ($step) use ($browser) {
                $step->assertFocused('#signup_login');

                $step->type('#signup_login', '*');
                $step->type('#signup_password', '12345678');
                $step->type('#signup_confirm', '123456789');

                $step->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertVisible('#signup_login.is-invalid');
                $step->assertVisible('#signup_domain + .invalid-feedback');
                $step->assertVisible('#signup_password.is-invalid');
                $step->assertVisible('#signup_password + .invalid-feedback');
                $step->assertFocused('#signup_login');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit invalid data (valid login, invalid password)
            $browser->with('@step3', function ($step) use ($browser) {
                $step->type('#signup_login', 'SignupTestDusk');

                $step->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertVisible('#signup_password.is-invalid');
                $step->assertVisible('#signup_password + .invalid-feedback');
                $step->assertMissing('#signup_login.is-invalid');
                $step->assertMissing('#signup_domain + .invalid-feedback');
                $step->assertFocused('#signup_password');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit valid data
            $browser->with('@step3', function ($step) {
                $step->type('#signup_confirm', '12345678');

                $step->click('[type=submit]');
            });

            $browser->waitUntilMissing('@step3');

            // At this point we should be auto-logged-in to dashboard
            $dashboard = new Dashboard();
            $dashboard->assert($browser);

            // FIXME: Is it enough to be sure user is logged in?
        });
    }
}
