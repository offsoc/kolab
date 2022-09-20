<?php

namespace Tests\Unit\Rules;

use App\Rules\SignupToken;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class SignupTokenTest extends TestCase
{
    /**
     * {@inheritDoc}
     */
    public function tearDown(): void
    {
        @unlink(storage_path('signup-tokens.txt'));

        $john = $this->getTestUser('john@kolab.org');
        $john->settings()->where('key', 'signup_token')->delete();

        parent::tearDown();
    }

    /**
     * Tests the resource name validator
     */
    public function testValidation(): void
    {
        $tokens = ['1234567890', 'abcdefghijk'];
        file_put_contents(storage_path('signup-tokens.txt'), implode("\n", $tokens));

        $rules = ['token' => [new SignupToken()]];

        // Empty input
        $v = Validator::make(['token' => null], $rules);
        $this->assertSame(['token' => ["The signup token is invalid."]], $v->errors()->toArray());

        // Length limit
        $v = Validator::make(['token' => str_repeat('a', 192)], $rules);
        $this->assertSame(['token' => ["The signup token is invalid."]], $v->errors()->toArray());

        // Non-existing token
        $v = Validator::make(['token' => '123'], $rules);
        $this->assertSame(['token' => ["The signup token is invalid."]], $v->errors()->toArray());

        // Valid tokens
        $v = Validator::make(['token' => $tokens[0]], $rules);
        $this->assertSame([], $v->errors()->toArray());

        $v = Validator::make(['token' => strtoupper($tokens[1])], $rules);
        $this->assertSame([], $v->errors()->toArray());

        // Tokens already used
        $john = $this->getTestUser('john@kolab.org');
        $john->setSetting('signup_token', $tokens[0]);

        $v = Validator::make(['token' => $tokens[0]], $rules);
        $this->assertSame(['token' => ["The signup token is invalid."]], $v->errors()->toArray());
    }
}
