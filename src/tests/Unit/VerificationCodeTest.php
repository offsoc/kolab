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
        $code_chars = env('VERIFICATION_CODE_CHARS', VerificationCode::SHORTCODE_CHARS);

        $this->assertTrue(is_string($code));
        $this->assertTrue(strlen($code) === $code_length);
        $this->assertTrue(strspn($code, $code_chars) === strlen($code));
    }
}
