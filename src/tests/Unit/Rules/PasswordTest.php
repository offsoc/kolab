<?php

namespace Tests\Unit\Rules;

use App\Rules\Password;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    /**
     * Test password validation
     */
    public function testValidator(): void
    {
        $error = "Specified password does not comply with the policy.";

        \config(['app.password_policy' => 'min:5']);
        $this->assertSame($error, $this->validate('abcd'));
        $this->assertSame(null, $this->validate('abcde'));

        \config(['app.password_policy' => 'min:5,max:10']);
        $this->assertSame($error, $this->validate('12345678901'));
        $this->assertSame(null, $this->validate('1234567890'));

        \config(['app.password_policy' => 'min:5,lower']);
        $this->assertSame($error, $this->validate('12345'));
        $this->assertSame($error, $this->validate('AAAAA'));
        $this->assertSame(null, $this->validate('12345a'));

        \config(['app.password_policy' => 'upper']);
        $this->assertSame($error, $this->validate('5'));
        $this->assertSame($error, $this->validate('a'));
        $this->assertSame(null, $this->validate('A'));

        \config(['app.password_policy' => 'digit']);
        $this->assertSame($error, $this->validate('a'));
        $this->assertSame($error, $this->validate('A'));
        $this->assertSame(null, $this->validate('5'));

        \config(['app.password_policy' => 'special']);
        $this->assertSame($error, $this->validate('a'));
        $this->assertSame($error, $this->validate('5'));
        $this->assertSame(null, $this->validate('*'));
        $this->assertSame(null, $this->validate('-'));

        // Test with an account policy
        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('password_policy', 'min:10,upper');

        $this->assertSame($error, $this->validate('aaa', $user));
        $this->assertSame($error, $this->validate('1234567890', $user));
        $this->assertSame(null, $this->validate('1234567890A', $user));
    }

    /**
     * Test check() method
     */
    public function testCheck(): void
    {
        $pass = new Password();

        \config(['app.password_policy' => 'min:5,max:10,upper,lower,digit']);
        $result = $pass->check('abcd');

        $this->assertCount(5, $result);
        $this->assertSame('min', $result['min']['label']);
        $this->assertSame('Minimum password length: 5 characters', $result['min']['name']);
        $this->assertSame('5', $result['min']['param']);
        $this->assertSame(true, $result['min']['enabled']);
        $this->assertSame(false, $result['min']['status']);

        $this->assertSame('max', $result['max']['label']);
        $this->assertSame('Maximum password length: 10 characters', $result['max']['name']);
        $this->assertSame('10', $result['max']['param']);
        $this->assertSame(true, $result['max']['enabled']);
        $this->assertSame(true, $result['max']['status']);

        $this->assertSame('upper', $result['upper']['label']);
        $this->assertSame('Password contains an upper-case character', $result['upper']['name']);
        $this->assertSame(null, $result['upper']['param']);
        $this->assertSame(true, $result['upper']['enabled']);
        $this->assertSame(false, $result['upper']['status']);

        $this->assertSame('lower', $result['lower']['label']);
        $this->assertSame('Password contains a lower-case character', $result['lower']['name']);
        $this->assertSame(null, $result['lower']['param']);
        $this->assertSame(true, $result['lower']['enabled']);
        $this->assertSame(true, $result['lower']['status']);

        $this->assertSame('digit', $result['digit']['label']);
        $this->assertSame('Password contains a digit', $result['digit']['name']);
        $this->assertSame(null, $result['digit']['param']);
        $this->assertSame(true, $result['digit']['enabled']);
        $this->assertSame(false, $result['digit']['status']);
    }

    /**
     * Test rules() method
     */
    public function testRules(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('password_policy', 'min:10,upper');

        $pass = new Password($user);

        \config(['app.password_policy' => 'min:5,max:10,digit']);

        $result = $pass->rules();

        $this->assertCount(2, $result);
        $this->assertSame('min', $result['min']['label']);
        $this->assertSame('Minimum password length: 10 characters', $result['min']['name']);
        $this->assertSame('10', $result['min']['param']);
        $this->assertSame(true, $result['min']['enabled']);

        $this->assertSame('upper', $result['upper']['label']);
        $this->assertSame('Password contains an upper-case character', $result['upper']['name']);
        $this->assertSame(null, $result['upper']['param']);
        $this->assertSame(true, $result['upper']['enabled']);

        // Expect to see all supported policy rules
        $result = $pass->rules(true);

        $this->assertCount(6, $result);
        $this->assertSame('min', $result['min']['label']);
        $this->assertSame('Minimum password length: 10 characters', $result['min']['name']);
        $this->assertSame('10', $result['min']['param']);
        $this->assertSame(true, $result['min']['enabled']);

        $this->assertSame('max', $result['max']['label']);
        $this->assertSame('Maximum password length: 255 characters', $result['max']['name']);
        $this->assertSame('255', $result['max']['param']);
        $this->assertSame(false, $result['max']['enabled']);

        $this->assertSame('upper', $result['upper']['label']);
        $this->assertSame('Password contains an upper-case character', $result['upper']['name']);
        $this->assertSame(null, $result['upper']['param']);
        $this->assertSame(true, $result['upper']['enabled']);

        $this->assertSame('lower', $result['lower']['label']);
        $this->assertSame('Password contains a lower-case character', $result['lower']['name']);
        $this->assertSame(null, $result['lower']['param']);
        $this->assertSame(false, $result['lower']['enabled']);

        $this->assertSame('digit', $result['digit']['label']);
        $this->assertSame('Password contains a digit', $result['digit']['name']);
        $this->assertSame(null, $result['digit']['param']);
        $this->assertSame(false, $result['digit']['enabled']);

        $this->assertSame('special', $result['special']['label']);
        $this->assertSame('Password contains a special character', $result['special']['name']);
        $this->assertSame(null, $result['digit']['param']);
        $this->assertSame(false, $result['digit']['enabled']);
    }

    /**
     * Validates the password using Laravel Validator API
     *
     * @param string     $password The password to validate
     * @param ?\App\User $user     The account owner
     *
     * @return ?string Validation error message on error, NULL otherwise
     */
    private function validate($password, $user = null): ?string
    {
        // Instead of doing direct tests, we use validator to make sure
        // it works with the framework api

        $v = Validator::make(
            ['password' => $password],
            ['password' => new Password($user)]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['password'][0];
        }

        return null;
    }
}
