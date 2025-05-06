<?php

namespace Tests\Unit;

use App\SignupCode;
use App\Utils;
use Tests\TestCase;

class SignupCodeTest extends TestCase
{
    /**
     * Test SignupCode::generateShortCode()
     */
    public function testGenerateShortCode()
    {
        $code = SignupCode::generateShortCode();

        $this->assertTrue(strlen($code) === env('SIGNUP_CODE_LENGTH', SignupCode::SHORTCODE_LENGTH));
        $this->assertTrue(strspn($code, env('SIGNUP_CODE_CHARS', Utils::CHARS)) === strlen($code));
    }
}
