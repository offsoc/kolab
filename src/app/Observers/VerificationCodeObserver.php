<?php

namespace App\Observers;

use App\VerificationCode;
use Carbon\Carbon;
use Illuminate\Support\Str;

class VerificationCodeObserver
{
    /**
     * Handle the "creating" event.
     *
     * Ensure that the code entry is created with a random code/short_code.
     *
     * @param \App\VerificationCode $code The code being created.
     *
     * @return void
     */
    public function creating(VerificationCode $code): void
    {
        $code_length = VerificationCode::CODE_LENGTH;
        $exp_hours   = env('VERIFICATION_CODE_EXPIRY', VerificationCode::CODE_EXP_HOURS);

        if (empty($code->code)) {
            $code->short_code = VerificationCode::generateShortCode();

            // FIXME: Replace this with something race-condition free
            while (true) {
                $code->code = Str::random($code_length);
                if (!VerificationCode::find($code->code)) {
                    break;
                }
            }
        }

        $code->expires_at = Carbon::now()->addHours($exp_hours);
    }
}
