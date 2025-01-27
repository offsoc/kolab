<?php

namespace Tests\Unit\Auth;

use App\Auth\Utils;
use Carbon\Carbon;
use Tests\TestCase;

class UtilsTest extends TestCase
{
    /**
     * Test token creation and validation
     */
    public function testTokenCreateAndValidate(): void
    {
        $userid = '1234567890';
        $token = Utils::tokenCreate($userid);

        $this->assertTrue(strlen($token) > 50 && strlen($token) < 128);
        $this->assertTrue(preg_match('|^[a-zA-Z0-9!+/]+$|', $token) === 1);

        $this->assertSame($userid, Utils::tokenValidate($token));

        // Expired token
        Carbon::setTestNow(Carbon::now()->addSeconds(11));
        $this->assertNull(Utils::tokenValidate($token));

        // Invalid token
        $this->assertNull(Utils::tokenValidate('sdfsdfsfsdfsdfs!asd!sdfsdfsdfrwet'));
        $this->assertNull(Utils::tokenValidate('sdfsdfsfsdfsdf'));
    }
}
