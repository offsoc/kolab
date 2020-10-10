<?php

namespace Tests\Unit\Rules;

use App\Rules\UserEmailLocal;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class UserEmailLocalTest extends TestCase
{
    /**
     * Test validation of email local part
     */
    public function testUserEmailLocal(): void
    {
        //$this->markTestIncomplete();

        // the email address can not start with a dot.
        $this->assertFalse(\App\Utils::isValidEmailAddress('.something@test.domain'));
    }
}
