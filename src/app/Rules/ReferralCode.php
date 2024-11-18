<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class ReferralCode implements Rule
{
    private $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $code      Referral code
     */
    public function passes($attribute, $code): bool
    {
        // Check the max length, according to the database column length
        if (!is_string($code) || strlen($code) > 16) {
            $this->message = \trans('validation.referralcodeinvalid');
            return false;
        }

        $exists = \App\ReferralCode::where('code', $code)
            ->join('referral_programs', 'referral_programs.id', '=', 'referral_codes.program_id')
            ->withEnvTenantContext()
            ->where('active', true)
            ->exists();

        if (!$exists) {
            $this->message = \trans('validation.referralcodeinvalid');
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): ?string
    {
        return $this->message;
    }
}
