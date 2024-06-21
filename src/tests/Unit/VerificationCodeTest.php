<?php

namespace Tests\Unit;

use App\VerificationCode;
use Tests\TestCase;

class VerificationCodeTest extends TestCase
{
    /**
     * Test VerificationCode::generateShortCode()
     *
     * @return void
     */
    public function testGenerateShortCode()
    {
        $code = VerificationCode::generateShortCode();

        $code_length = env('VERIFICATION_CODE_LENGTH', VerificationCode::SHORTCODE_LENGTH);

        $this->assertTrue(is_string($code));
        $this->assertTrue(strlen($code) === $code_length);
        $this->assertTrue(strspn($code, \App\Utils::CHARS) === strlen($code));
    }
}
