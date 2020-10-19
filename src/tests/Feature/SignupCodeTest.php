<?php

namespace Tests\Feature;

use App\SignupCode;
use Carbon\Carbon;
use Tests\TestCase;

class SignupCodeTest extends TestCase
{
    /**
     * Test SignupCode creation
     *
     * @return void
     */
    public function testSignupCodeCreate()
    {
        $data = [
            'data' => [
                'email' => 'User@email.org',
            ]
        ];

        $now = Carbon::now();

        $code = SignupCode::create($data);

        $code_length = env('VERIFICATION_CODE_LENGTH', SignupCode::SHORTCODE_LENGTH);
        $exp = Carbon::now()->addHours(env('SIGNUP_CODE_EXPIRY', SignupCode::CODE_EXP_HOURS));

        $this->assertFalse($code->isExpired());
        $this->assertTrue(strlen($code->code) === SignupCode::CODE_LENGTH);
        $this->assertTrue(strlen($code->short_code) === $code_length);
        $this->assertSame($data['data'], $code->data);
        $this->assertInstanceOf(Carbon::class, $code->expires_at);
        $this->assertSame($code->expires_at->toDateTimeString(), $exp->toDateTimeString());

        $inst = SignupCode::find($code->code);

        $this->assertInstanceOf(SignupCode::class, $inst);
        $this->assertSame($inst->code, $code->code);
    }
}
