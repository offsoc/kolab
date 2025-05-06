<?php

namespace Tests\Unit\Rules;

use App\Rules\Password;
use App\User;
use App\Utils;
use Illuminate\Support\Facades\Hash;
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
        $this->assertNull($this->validate('abcde'));

        \config(['app.password_policy' => 'min:5,max:10']);
        $this->assertSame($error, $this->validate('12345678901'));
        $this->assertNull($this->validate('1234567890'));

        \config(['app.password_policy' => 'min:5,lower']);
        $this->assertSame($error, $this->validate('12345'));
        $this->assertSame($error, $this->validate('AAAAA'));
        $this->assertNull($this->validate('12345a'));

        \config(['app.password_policy' => 'upper']);
        $this->assertSame($error, $this->validate('5'));
        $this->assertSame($error, $this->validate('a'));
        $this->assertNull($this->validate('A'));

        \config(['app.password_policy' => 'digit']);
        $this->assertSame($error, $this->validate('a'));
        $this->assertSame($error, $this->validate('A'));
        $this->assertNull($this->validate('5'));

        \config(['app.password_policy' => 'special']);
        $this->assertSame($error, $this->validate('a'));
        $this->assertSame($error, $this->validate('5'));
        $this->assertNull($this->validate('*'));
        $this->assertNull($this->validate('-'));

        // Test with an account policy
        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('password_policy', 'min:10,upper');

        $this->assertSame($error, $this->validate('aaa', $user));
        $this->assertSame($error, $this->validate('1234567890', $user));
        $this->assertNull($this->validate('1234567890A', $user));
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
        $this->assertTrue($result['min']['enabled']);
        $this->assertFalse($result['min']['status']);

        $this->assertSame('max', $result['max']['label']);
        $this->assertSame('Maximum password length: 10 characters', $result['max']['name']);
        $this->assertSame('10', $result['max']['param']);
        $this->assertTrue($result['max']['enabled']);
        $this->assertTrue($result['max']['status']);

        $this->assertSame('upper', $result['upper']['label']);
        $this->assertSame('Password contains an upper-case character', $result['upper']['name']);
        $this->assertNull($result['upper']['param']);
        $this->assertTrue($result['upper']['enabled']);
        $this->assertFalse($result['upper']['status']);

        $this->assertSame('lower', $result['lower']['label']);
        $this->assertSame('Password contains a lower-case character', $result['lower']['name']);
        $this->assertNull($result['lower']['param']);
        $this->assertTrue($result['lower']['enabled']);
        $this->assertTrue($result['lower']['status']);

        $this->assertSame('digit', $result['digit']['label']);
        $this->assertSame('Password contains a digit', $result['digit']['name']);
        $this->assertNull($result['digit']['param']);
        $this->assertTrue($result['digit']['enabled']);
        $this->assertFalse($result['digit']['status']);

        // Test password history check
        $user = $this->getTestUser('john@kolab.org');
        $user->passwords()->delete();
        $user_pass = Utils::generatePassphrase(); // should be the same plain password as John already has

        $pass = new Password(null, $user);

        \config(['app.password_policy' => 'min:5,last:1']);

        $result = $pass->check('abcd');

        $this->assertCount(2, $result);
        $this->assertSame('min', $result['min']['label']);
        $this->assertSame('Minimum password length: 5 characters', $result['min']['name']);
        $this->assertSame('5', $result['min']['param']);
        $this->assertTrue($result['min']['enabled']);
        $this->assertFalse($result['min']['status']);

        $this->assertSame('last', $result['last']['label']);
        $this->assertSame('Password cannot be the same as the last 1 passwords', $result['last']['name']);
        $this->assertSame('1', $result['last']['param']);
        $this->assertTrue($result['last']['enabled']);
        $this->assertTrue($result['last']['status']);

        $result = $pass->check($user_pass);

        $this->assertCount(2, $result);
        $this->assertSame('last', $result['last']['label']);
        $this->assertSame('Password cannot be the same as the last 1 passwords', $result['last']['name']);
        $this->assertSame('1', $result['last']['param']);
        $this->assertTrue($result['last']['enabled']);
        $this->assertFalse($result['last']['status']);

        $user->passwords()->create(['password' => Hash::make('1234567891')]);
        $user->passwords()->create(['password' => Hash::make('1234567890')]);

        $result = $pass->check('1234567890');

        $this->assertTrue($result['last']['status']);

        \config(['app.password_policy' => 'min:5,last:3']);

        $result = $pass->check('1234567890');

        $this->assertFalse($result['last']['status']);
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
        $this->assertTrue($result['min']['enabled']);

        $this->assertSame('upper', $result['upper']['label']);
        $this->assertSame('Password contains an upper-case character', $result['upper']['name']);
        $this->assertNull($result['upper']['param']);
        $this->assertTrue($result['upper']['enabled']);

        // Expect to see all supported policy rules
        $result = $pass->rules(true);

        $this->assertCount(7, $result);
        $this->assertSame('min', $result['min']['label']);
        $this->assertSame('Minimum password length: 10 characters', $result['min']['name']);
        $this->assertSame('10', $result['min']['param']);
        $this->assertTrue($result['min']['enabled']);

        $this->assertSame('max', $result['max']['label']);
        $this->assertSame('Maximum password length: 255 characters', $result['max']['name']);
        $this->assertSame('255', $result['max']['param']);
        $this->assertFalse($result['max']['enabled']);

        $this->assertSame('upper', $result['upper']['label']);
        $this->assertSame('Password contains an upper-case character', $result['upper']['name']);
        $this->assertNull($result['upper']['param']);
        $this->assertTrue($result['upper']['enabled']);

        $this->assertSame('lower', $result['lower']['label']);
        $this->assertSame('Password contains a lower-case character', $result['lower']['name']);
        $this->assertNull($result['lower']['param']);
        $this->assertFalse($result['lower']['enabled']);

        $this->assertSame('digit', $result['digit']['label']);
        $this->assertSame('Password contains a digit', $result['digit']['name']);
        $this->assertNull($result['digit']['param']);
        $this->assertFalse($result['digit']['enabled']);

        $this->assertSame('special', $result['special']['label']);
        $this->assertSame('Password contains a special character', $result['special']['name']);
        $this->assertNull($result['digit']['param']);
        $this->assertFalse($result['digit']['enabled']);

        $this->assertSame('last', $result['last']['label']);
        $this->assertSame('Password cannot be the same as the last 3 passwords', $result['last']['name']);
        $this->assertSame('3', $result['last']['param']);
        $this->assertFalse($result['last']['enabled']);
    }

    /**
     * Validates the password using Laravel Validator API
     *
     * @param string $password The password to validate
     * @param ?User  $owner    The account owner
     *
     * @return ?string Validation error message on error, NULL otherwise
     */
    private function validate($password, $owner = null): ?string
    {
        // Instead of doing direct tests, we use validator to make sure
        // it works with the framework api

        $v = Validator::make(
            ['password' => $password],
            ['password' => new Password($owner)]
        );

        if ($v->fails()) {
            return $v->errors()->toArray()['password'][0];
        }

        return null;
    }
}
