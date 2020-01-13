<?php

namespace Tests\Browser;

use App\Domain;
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
     */
    public function setUp(): void
    {
        parent::setUp();

        Domain::where('namespace', 'user-domain-signup.com')->delete();
        User::where('email', 'signuptestdusk@' . \config('app.domain'))
            ->orWhere('email', 'admin@user-domain-signup.com')
            ->delete();
    }

    /**
     * Test signup code verification with a link
     */
    public function testSignupCodeByLink(): void
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
     * Test signup "welcome" page
     */
    public function testSignupStep0(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Signup());

            $browser->assertVisible('@step0')
                ->assertMissing('@step1')
                ->assertMissing('@step2')
                ->assertMissing('@step3');

            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                $browser->assertActiveItem('signup');
            });

            $browser->waitFor('@step0 .plan-selector > .plan-box');

            // Assert first plan box and press the button
            $browser->with('@step0 .plan-selector > .plan-individual', function ($step) {
                $step->assertVisible('button')
                    ->assertSeeIn('button', 'individual')
                    ->assertVisible('.plan-description')
                    ->click('button');
            });

            $browser->waitForLocation('/signup/individual')
                ->assertVisible('@step1')
                ->assertMissing('@step0')
                ->assertMissing('@step2')
                ->assertMissing('@step3')
                ->assertFocused('@step1 #signup_name');

            // Click Back button
            $browser->click('@step1 [type=button]')
                ->waitForLocation('/signup')
                    ->assertVisible('@step0')
                    ->assertMissing('@step1')
                    ->assertMissing('@step2')
                    ->assertMissing('@step3');

            // Choose the group account plan
            $browser->click('@step0 .plan-selector > .plan-group button')
                ->waitForLocation('/signup/group')
                ->assertVisible('@step1')
                ->assertMissing('@step0')
                ->assertMissing('@step2')
                ->assertMissing('@step3')
                ->assertFocused('@step1 #signup_name');

            // TODO: Test if 'plan' variable is set properly in vue component
        });
    }

    /**
     * Test 1st step of the signup process
     */
    public function testSignupStep1(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit('/signup/individual')->onWithoutAssert(new Signup());

            $browser->assertVisible('@step1');

            $browser->within(new Menu(), function ($browser) {
                $browser->assertMenuItems(['signup', 'explore', 'blog', 'support', 'login']);
                $browser->assertActiveItem('signup');
            });

            // Here we expect two text inputs and Back and Continue buttons
            $browser->with('@step1', function ($step) {
                $step->assertVisible('#signup_name')
                    ->assertFocused('#signup_name')
                    ->assertVisible('#signup_email')
                    ->assertVisible('[type=button]')
                    ->assertVisible('[type=submit]');
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
     */
    public function testSignupStep2(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->assertVisible('@step2')
                ->assertMissing('@step0')
                ->assertMissing('@step1')
                ->assertMissing('@step3');

            // Here we expect one text input, Back and Continue buttons
            $browser->with('@step2', function ($step) {
                $step->assertVisible('#signup_short_code')
                    ->assertFocused('#signup_short_code')
                    ->assertVisible('[type=button]')
                    ->assertVisible('[type=submit]');
            });

            // Test Back button functionality
            $browser->click('@step2 [type=button]')
                ->waitFor('@step1')
                ->assertFocused('@step1 #signup_name')
                ->assertMissing('@step2');

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
     */
    public function testSignupStep3(): void
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

            // Logout the user
            // TODO: Test what happens if you goto /signup with active session
            $browser->click('a.link-logout');
        });
    }

    /**
     * Test signup for a group account
     */
    public function testSignupGroup(): void
    {
        $this->browse(function (Browser $browser) {
            $browser->visit(new Signup());

            // Choose the group account plan
            $browser->click('@step0 .plan-selector > .plan-group button')
                ->waitForLocation('/signup/group');

            $browser->assertVisible('@step1');

            // Submit valid data
            // We expect error state on email input to be removed, and Step 2 form visible
            $browser->with('@step1', function ($step) {
                $step->type('#signup_name', 'Test User')
                    ->type('#signup_email', 'BrowserSignupTestUser1@kolab.org')
                    ->click('[type=submit]');
            });

            $browser->waitFor('@step2');

            // Submit valid code
            $browser->with('@step2', function ($step) {
                // Get the code and short_code from database
                // FIXME: Find a nice way to read javascript data without using hidden inputs
                $code = $step->value('#signup_code');
                $code = SignupCode::find($code);

                $step->type('#signup_short_code', $code->short_code)
                    ->click('[type=submit]');
            });

            $browser->waitFor('@step3');

            // Here we expect 4 text inputs, Back and Continue buttons
            $browser->with('@step3', function ($step) {
                $step->assertVisible('#signup_login')
                    ->assertVisible('#signup_password')
                    ->assertVisible('#signup_confirm')
                    ->assertVisible('input#signup_domain')
                    ->assertVisible('[type=button]')
                    ->assertVisible('[type=submit]')
                    ->assertFocused('#signup_login')
                    ->assertValue('input#signup_domain', '')
                    ->assertValue('#signup_login', '')
                    ->assertValue('#signup_password', '')
                    ->assertValue('#signup_confirm', '');
            });

            // Submit invalid login and password data
            $browser->with('@step3', function ($step) use ($browser) {
                $step->assertFocused('#signup_login')
                    ->type('#signup_login', '*')
                    ->type('#signup_domain', 'test.com')
                    ->type('#signup_password', '12345678')
                    ->type('#signup_confirm', '123456789')
                    ->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertVisible('#signup_login.is-invalid')
                    ->assertVisible('#signup_domain + .invalid-feedback')
                    ->assertVisible('#signup_password.is-invalid')
                    ->assertVisible('#signup_password + .invalid-feedback')
                    ->assertFocused('#signup_login');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit invalid domain
            $browser->with('@step3', function ($step) use ($browser) {
                $step->type('#signup_login', 'admin')
                    ->type('#signup_domain', 'aaa')
                    ->type('#signup_password', '12345678')
                    ->type('#signup_confirm', '12345678')
                    ->click('[type=submit]');

                $browser->waitFor('.toast-error');

                $step->assertMissing('#signup_login.is-invalid')
                    ->assertVisible('#signup_domain.is-invalid + .invalid-feedback')
                    ->assertMissing('#signup_password.is-invalid')
                    ->assertMissing('#signup_password + .invalid-feedback')
                    ->assertFocused('#signup_domain');

                $browser->click('.toast-error'); // remove the toast
            });

            // Submit invalid domain
            $browser->with('@step3', function ($step) use ($browser) {
                $step->type('#signup_domain', 'user-domain-signup.com')
                    ->click('[type=submit]');
            });

            $browser->waitUntilMissing('@step3');

            // At this point we should be auto-logged-in to dashboard
            $dashboard = new Dashboard();
            $dashboard->assert($browser);

            // FIXME: Is it enough to be sure user is logged in?
            $browser->click('a.link-logout');
        });
    }
}
