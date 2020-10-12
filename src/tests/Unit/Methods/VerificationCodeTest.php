<?php

namespace Tests\Unit\Methods;

use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    private $verificationcode;

    /**
     * Unit tests need no extensive setup.
     */
    public function setUp(): void
    {
        $this->verificationcode = new \App\VerificationCode();
    }

    /**
     * With no setup, no teardown is needed.
     */
    public function tearDown(): void
    {
        // nothing to do here
    }

    public function testGenerateShortCode()
    {
        $codes = [];

        // 2 ^ 10 generated codes yields duplicates
        // the number of verification with valid codes is to happen within 8 hours
        for ($x = 0; $x < pow(2, 9); $x++) {
            $code = \App\VerificationCode::generateShortCode();

            $this->assertTrue(!in_array($code, $codes), "Duplicate code generated at {$x} codes");

            $codes[] = $code;
        }
    }
}
