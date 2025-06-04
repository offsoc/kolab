<?php

namespace Tests\Unit\Rules;

use App\Rules\Password;
use App\User;
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
