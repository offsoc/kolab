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
            'email' => 'User@email.org',
        ];

        $now = Carbon::now();

        $code = SignupCode::create($data);
        $code_length = env('VERIFICATION_CODE_LENGTH', SignupCode::SHORTCODE_LENGTH);
        $exp = Carbon::now()->addHours(env('SIGNUP_CODE_EXPIRY', SignupCode::CODE_EXP_HOURS));

        $this->assertFalse($code->isExpired());
        $this->assertTrue(strlen($code->code) === SignupCode::CODE_LENGTH);
        $this->assertTrue(strlen($code->short_code) === $code_length);
        $this->assertSame($data['email'], $code->email);
        $this->assertSame('User', $code->local_part);
        $this->assertSame('email.org', $code->domain_part);
        $this->assertSame('127.0.0.1', $code->ip_address);
        $this->assertInstanceOf(Carbon::class, $code->expires_at);
        $this->assertSame($code->expires_at->toDateTimeString(), $exp->toDateTimeString());

        $inst = SignupCode::find($code->code);

        $this->assertInstanceOf(SignupCode::class, $inst);
        $this->assertSame($inst->code, $code->code);

        $inst->email = 'other@email.com';
        $inst->save();

        $this->assertSame('other', $inst->local_part);
        $this->assertSame('email.com', $inst->domain_part);
    }
}
