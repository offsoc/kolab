<?php

namespace Tests\Feature;

use App\User;
use App\VerificationCode;
use Tests\TestCase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class VerificationCodeTest extends TestCase
{
    /**
     * Test VerificationCode creation
     */
    public function testVerificationCodeCreate(): void
    {
        $user = $this->getTestUser('UserAccountA@UserAccount.com');
        $data = [
            'user_id' => $user->id,
            'mode' => 'password-reset',
        ];

        $now = new \DateTime('now');

        $code = VerificationCode::create($data);

        $code_length = env('VERIFICATION_CODE_LENGTH', VerificationCode::SHORTCODE_LENGTH);
        $code_exp_hrs = env('VERIFICATION_CODE_EXPIRY', VerificationCode::CODE_EXP_HOURS);

        $this->assertFalse($code->isExpired());
        $this->assertTrue(strlen($code->code) === VerificationCode::CODE_LENGTH);
        $this->assertTrue(strlen($code->short_code) === $code_length);
        $this->assertSame($data['mode'], $code->mode);
        $this->assertSame($user->id, $code->user->id);
        $this->assertInstanceOf(\DateTime::class, $code->expires_at);
        $this->assertSame($code_exp_hrs, $code->expires_at->diff($now)->h + 1);

        $inst = VerificationCode::find($code->code);

        $this->assertInstanceOf(VerificationCode::class, $inst);
        $this->assertSame($inst->code, $code->code);
    }
}
