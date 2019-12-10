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
        $this->assertTrue(strlen($code) === SignupCode::SHORTCODE_LENGTH);
        $this->assertTrue(strspn($code, SignupCode::SHORTCODE_CHARS) === strlen($code));
    }
}
