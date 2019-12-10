<?php

namespace App\Observers;

use App\SignupCode;
use Carbon\Carbon;

class SignupCodeObserver
{
    /**
     * Handle the "creating" event.
     *
     * Ensure that the code entry is created with a random code/short_code.
     *
     * @param \App\User $user The user being created.
     *
     * @return void
     */
    public function creating(SignupCode $code): void
    {
        $code_length = env('SIGNUP_CODE_LENGTH', SignupCode::CODE_LENGTH);
        $exp_hours   = env('SIGNUP_CODE_EXPIRY', SignupCode::CODE_EXP_HOURS);

        if (empty($code->code)) {
            $code->short_code = SignupCode::generateShortCode();

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
}
