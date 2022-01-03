<?php

namespace App\Rules;

use App\SignupCode;

class SignupExternalEmail extends ExternalEmail
{
    /**
     * {@inheritDoc}
     */
    public function passes($attribute, $email): bool
    {
        if (!parent::passes($attribute, $email)) {
            return false;
        }

        // Check the max length, according to the database column length
        if (strlen($email) > 191) {
            $this->message = \trans('validation.emailinvalid');
            return false;
        }

        // Don't allow multiple open registrations against the same email address
        if (($limit = \config('app.signup.email_limit')) > 0) {
            $signups = SignupCode::where('email', $email)
                ->where('expires_at', '>', \Carbon\Carbon::now());

            if ($signups->count() >= $limit) {
                // @kanarip: this is deliberately an "email invalid" message
                $this->message = \trans('validation.emailinvalid');
                return false;
            }
        }

        // Don't allow multiple open registrations against the same source ip address
        if (($limit = \config('app.signup.ip_limit')) > 0) {
            $signups = SignupCode::where('ip_address', request()->ip())
                ->where('expires_at', '>', \Carbon\Carbon::now());

            if ($signups->count() >= $limit) {
                // @kanarip: this is deliberately an "email invalid" message
                $this->message = \trans('validation.emailinvalid');
                return false;
            }
        }

        return true;
    }
}
