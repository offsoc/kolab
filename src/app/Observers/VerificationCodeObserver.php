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
     * @param VerificationCode $code the code being created
     */
    public function creating(VerificationCode $code): void
    {
        $code_length = VerificationCode::CODE_LENGTH;
        $exp_hours = env('VERIFICATION_CODE_EXPIRY', VerificationCode::CODE_EXP_HOURS);

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

        if (empty($code->expires_at)) {
            $code->expires_at = Carbon::now()->addHours($exp_hours);
        }

        // Verification codes are active by default
        // Note: This is not required, but this way we make sure the property value
        // is a boolean not null after create() call, if it wasn't specified there.
        if (!isset($code->active)) {
            $code->active = true;
        }
    }
}
