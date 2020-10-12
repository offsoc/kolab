<?php

namespace Tests\Unit\Methods;

use Tests\TestCase;

class SignupCodeTest extends TestCase
{
    private $signupcode;

    /**
     * Unit tests need no extensive setup.
     */
    public function setUp(): void
    {
        $this->signupcode = new \App\SignupCode();
    }

    /**
     * With no setup, no teardown is needed.
     */
    public function tearDown(): void
    {
        // nothing to do here
    }

    /**
     * Verify that on the object and database level, the signup code's expiry is not set.
     *
     * It is set in the observer, which is a ::create(), which is therefore functional.
     */
    public function testExpiredAtDefaultNull()
    {
        $this->assertNull($this->signupcode->expires_at);
    }

    /**
     * Verify that on the object and database level, the signup code's expiry is not set.
     *
     * It is set in the observer, which is a ::create(), which is therefore functional.
     *
     * That means ->isExpired() should always return false.
     */
    public function testIsExpired()
    {
        $this->assertFalse($this->signupcode->isExpired());
    }

    public function testGenerateShortCode()
    {
        $codes = [];

        // 2 ^ 10 generated codes yields duplicates
        // the number of signups with valid codes is to happen within 24 hours
        for ($x = 0; $x < pow(2, 9); $x++) {
            $code = \App\SignupCode::generateShortCode();

            $this->assertTrue(!in_array($code, $codes), "Duplicate code generated at {$x} codes");

            $codes[] = $code;
        }
    }
}
