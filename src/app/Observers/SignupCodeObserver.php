<?php

namespace App\Observers;

use App\SignupCode;
use Carbon\Carbon;
use Illuminate\Support\Str;

class SignupCodeObserver
{
    /**
     * Handle the "creating" event.
     *
     * Ensure that the code entry is created with a random code/short_code.
     *
     * @param \App\SignupCode $code The code being created.
     *
     * @return void
     */
    public function creating(SignupCode $code): void
    {
        $code_length = SignupCode::CODE_LENGTH;
        $exp_hours   = env('SIGNUP_CODE_EXPIRY', SignupCode::CODE_EXP_HOURS);

        if (empty($code->code)) {
            $code->short_code = SignupCode::generateShortCode();

            // FIXME: Replace this with something race-condition free
            while (true) {
                $code->code = Str::random($code_length);
                if (!SignupCode::find($code->code)) {
                    break;
                }
            }
        }

        $code->headers = collect(request()->headers->all())
            ->filter(function ($value, $key) {
                // remove some headers we don't care about
                return !in_array($key, ['cookie', 'referer', 'x-test-payment-provider', 'origin']);
            })
            ->map(function ($value) {
                return is_array($value) && count($value) == 1 ? $value[0] : $value;
            });

        $code->expires_at = Carbon::now()->addHours($exp_hours);
        $code->ip_address = request()->ip();

        if ($code->email) {
            $parts = explode('@', $code->email);

            $code->local_part = $parts[0];
            $code->domain_part = $parts[1];
        }
    }

    /**
     * Handle the "updating" event.
     *
     * @param SignupCode $code The code being updated.
     *
     * @return void
     */
    public function updating(SignupCode $code)
    {
        if ($code->email) {
            $parts = explode('@', $code->email);

            $code->local_part = $parts[0];
            $code->domain_part = $parts[1];
        } else {
            $code->local_part = null;
            $code->domain_part = null;
        }
    }
}
