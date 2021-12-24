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
     *
     * @return bool
     */
    public function passes($attribute, $email): bool
    {
        $v = Validator::make(['email' => $email], ['email' => 'required|email']);

        if ($v->fails()) {
            $this->message = \trans('validation.emailinvalid');
            return false;
        }

        list($local, $domain) = explode('@', $email);

        // don't allow @localhost and other no-fqdn
        if (strpos($domain, '.') === false) {
            $this->message = \trans('validation.emailinvalid');
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     *
     * @return string
     */
    public function message(): ?string
    {
        return $this->message;
    }
}
