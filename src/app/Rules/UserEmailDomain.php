<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class UserEmailDomain implements Rule
{
    private $message;
    private $domains;

    /**
     * Class constructor.
     *
     * @param array|null $domains Allowed domains
     */
    public function __construct($domains = null)
    {
        $this->domains = $domains;
    }

    /**
     * Determine if the validation rule passes.
     *
     * Validation of local part of an email address that's
     * going to be user's login.
     *
     * @param string $attribute Attribute name
     * @param mixed  $domain    Domain part of email address
     *
     * @return bool
     */
    public function passes($attribute, $domain): bool
    {
        // don't allow @localhost and other no-fqdn
        if (empty($domain) || strpos($domain, '.') === false || stripos($domain, 'www.') === 0) {
            $this->message = \trans('validation.domaininvalid');
            return false;
        }

        $domain = Str::lower($domain);

        // Use email validator to validate the domain part
        $v = Validator::make(['email' => 'user@' . $domain], ['email' => 'required|email']);
        if ($v->fails()) {
            $this->message = \trans('validation.domaininvalid');
            return false;
        }

        // Check if specified domain is allowed for signup
        if (is_array($this->domains) && !in_array($domain, $this->domains)) {
            $this->message = \trans('validation.domaininvalid');
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
