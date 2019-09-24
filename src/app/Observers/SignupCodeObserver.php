<?php

namespace App\Observers;

use App\SignupCode;
use Carbon\Carbon;

class SignupCodeObserver
{
    /**
     * Handle the "creating" event.
     *
     * Ensure that the code entry is created with a random, large integer.
     *
     * @param \App\User $user The user being created.
     *
     * @return void
     */
    public function creating(SignupCode $code)
    {
        $code_length = env('SIGNUP_CODE_LENGTH', SignupCode::CODE_LENGTH);
        $exp_hours   = env('SIGNUP_CODE_EXPIRY', SignupCode::CODE_EXP_HOURS);

        if (empty($code->code)) {
            $code->short_code = $this->generateShortCode();

            // FIXME: Replace this with something race-condition free
            while (true) {
                $code->code = str_random($code_length);
                if (!SignupCode::find($code->code)) {
                    break;
                }
            }
        }

        $code->expires_at = Carbon::now()->addHours($exp_hours);
    }

    /**
     * Generate a short code (for human).
     *
     * @return string
     */
    private function generateShortCode()
    {
        $code_length = env('SIGNUP_CODE_LENGTH', SignupCode::SHORTCODE_LENGTH);
        $code_chars  = env('SIGNUP_CODE_CHARS', SignupCode::SHORTCODE_CHARS);
        $random      = [];

        for ($i = 1; $i <= $code_length; $i++) {
            $random[] = $code_chars[rand(0, strlen($code_chars) - 1)];
        }

        shuffle($random);

        return implode('', $random);
    }
}
