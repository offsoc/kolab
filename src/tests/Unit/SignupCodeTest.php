<?php

namespace Tests\Unit;

use App\SignupCode;
use Tests\TestCase;

class SignupCodeTest extends TestCase
{
    /**
     * Test SignupCode::generateShortCode()
     *
     * @return void
     */
    public function testGenerateShortCode()
    {
        $code = SignupCode::generateShortCode();

        $this->assertTrue(is_string($code));
        $this->assertTrue(strlen($code) === env('SIGNUP_CODE_LENGTH', SignupCode::SHORTCODE_LENGTH));
        $this->assertTrue(strspn($code, env('SIGNUP_CODE_CHARS', \App\Utils::CHARS)) === strlen($code));
    }
}
