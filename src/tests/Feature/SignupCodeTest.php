<?php

namespace Tests\Feature;

use App\SignupCode;
use Carbon\Carbon;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

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

        $this->assertFalse($code->isExpired());
        $this->assertTrue(strlen($code->code) === SignupCode::CODE_LENGTH);

        $this->assertTrue(
            strlen($code->short_code) === env(
                'VERIFICATION_CODE_LENGTH',
                SignupCode::SHORTCODE_LENGTH
            )
        );

        $this->assertSame($data['data'], $code->data);
        $this->assertInstanceOf(Carbon::class, $code->expires_at);

        $this->assertSame(
            env('SIGNUP_CODE_EXPIRY', SignupCode::CODE_EXP_HOURS),
            $code->expires_at->diffInHours($now) + 1
        );

        $inst = SignupCode::find($code->code);

        $this->assertInstanceOf(SignupCode::class, $inst);
        $this->assertSame($inst->code, $code->code);
    }
}
