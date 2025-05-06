<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;

class ExternalEmail implements Rule
{
    protected $message;

    /**
     * Determine if the validation rule passes.
     *
     * Email address validation with some more strict rules
     * than the default Laravel's 'email' rule
     *
     * @param string $attribute Attribute name
     * @param mixed  $email     Email address input
     */
    public function passes($attribute, $email): bool
    {
        $v = Validator::make(['email' => $email], ['email' => 'required|email']);

        if ($v->fails()) {
            $this->message = \trans('validation.emailinvalid');
            return false;
        }

        [$local, $domain] = explode('@', $email);

        // don't allow @localhost and other no-fqdn
        if (!str_contains($domain, '.')) {
            $this->message = \trans('validation.emailinvalid');
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
