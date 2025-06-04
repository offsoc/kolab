<?php

namespace Tests\Unit\Policy;

use App\Policy\Password;
use App\Utils;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordTest extends TestCase
{
    /**
     * Test checkHash() method
     */
    public function testCheckHash(): void
    {
        // Test default algorithm w/o prefix
        $hash = Hash::make('test123');

        $this->assertTrue(Password::checkHash('test123', $hash));
        $this->assertFalse(Password::checkHash('badpass', $hash));

        // Test SSHA algorithm
        $hash = '{SSHA}kor2Qo2qEsP1XojQe3esWFB8IvYKqwUH';

        $this->assertTrue(Password::checkHash('test123', $hash));
        $this->assertFalse(Password::checkHash('badpass', $hash));

        // Test SSHA algorithm
        $hash = '{SSHA512}' . base64_encode(pack('H*', hash('sha512', 'test123')));

        $this->assertTrue(Password::checkHash('test123', $hash));
        $this->assertFalse(Password::checkHash('badpass', $hash));

        // Test PBKDF2-SHA512 algorithm (389-ds)
        $hash = '{PBKDF2-SHA512}10000$hbMHmUXNh3UoHNlDu+NJBOW+hVAhP3C0$ax9vNLL1rr85ppODFTykPC46igHh92v'
            . 'ULWpZaR/CQqyD4IGG/IyPYbbC2v7BxSPIDUXc0e9AGX9IuwinIj5a/w==';

        $this->assertTrue(Password::checkHash('12345678_kolab', $hash));
        $this->assertFalse(Password::checkHash('badpass', $hash));

        // Test PBKDF2_SHA256 algorithm (389-ds)
        $hash = '{PBKDF2_SHA256}AAAIABzpVq0s1Ta7cqubx+19QOzsI7n7KRLu0SovLVivxUVCn0+ghlj3+9+tf3jqurd'
            . 'QhpQ/OWYmxMlAJCAeIU3jN0DDW7ODk9FpLFzhO2055J+vY5M7EXAGrvhUlkiyeH/zx/RBp2pVQq/2vtI+qmO'
            . 'GOGUXdZ0hK00yNXpZ7K7WTwsnEXeWs4DGkGkxwyPgsGTyEwwdYK4YpCFdjJi/dXI6+kKf72j+B+epuzPtuvd'
            . 'Mj5xGnqe9jS+BN9Huzkof4vRPX3bYecywPaeNcdXPUY3iSj8hxFqiWbBDZ0mYy9aYAy6QgMitcdEGadPcR+d'
            . 'HXWNGK1qSLrFJJrB3cQtYhl+OgtHlwI0H4XTGBdp4MbegM3VgpUKuBNyIypwZ5oB/PRHA188bmsMjDmyN2kE'
            . 'nHSb1CK9MXcuS4bCQzNtutmQCxBCo';

        $this->assertTrue(Password::checkHash('Simple321Simple321', $hash));
        $this->assertFalse(Password::checkHash('badpass', $hash));
    }

    /**
     * Test checkPolicy() method
     */
    public function testCheckPolicy(): void
    {
        \config(['app.password_policy' => 'min:5,max:10,upper,lower,digit']);
        $result = Password::checkPolicy('abcd');

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

        \config(['app.password_policy' => 'min:5,last:1']);

        $result = Password::checkPolicy('abcd', $user);

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

        $user_pass = Utils::generatePassphrase(); // should be the same plain password as John already has
        $result = Password::checkPolicy($user_pass, $user);

        $this->assertCount(2, $result);
        $this->assertSame('last', $result['last']['label']);
        $this->assertSame('Password cannot be the same as the last 1 passwords', $result['last']['name']);
        $this->assertSame('1', $result['last']['param']);
        $this->assertTrue($result['last']['enabled']);
        $this->assertFalse($result['last']['status']);

        $user->passwords()->create(['password' => Hash::make('1234567891')]);
        $user->passwords()->create(['password' => Hash::make('1234567890')]);

        $result = Password::checkPolicy('1234567890', $user);
        $this->assertTrue($result['last']['status']);

        \config(['app.password_policy' => 'min:5,last:3']);

        $result = Password::checkPolicy('1234567890', $user);
        $this->assertFalse($result['last']['status']);

        // Test LDAP password in history
        \config(['app.password_policy' => 'min:5,last:5']);
        $hash = '{SSHA512}' . base64_encode(pack('H*', hash('sha512', 'test123aaa')));
        $user->passwords()->create(['password' => $hash]);

        $result = Password::checkPolicy('test123aaa', $user);
        $this->assertFalse($result['last']['status']);
    }

    /**
     * Test rules() method
     */
    public function testRules(): void
    {
        $user = $this->getTestUser('john@kolab.org');
        $user->setSetting('password_policy', 'min:10,upper');

        \config(['app.password_policy' => 'min:5,max:10,digit']);

        $result = Password::rules($user);

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
        $result = Password::rules($user, true);

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
}
