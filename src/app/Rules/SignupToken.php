<?php

namespace App\Rules;

use App\Plan;
use Illuminate\Contracts\Validation\Rule;

class SignupToken implements Rule
{
    protected $message;
    protected $plan;

    /**
     * Class constructor.
     *
     * @param ?Plan $plan Signup plan
     */
    public function __construct($plan)
    {
        $this->plan = $plan;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $token     The value to validate
     *
     * @return bool
     */
    public function passes($attribute, $token): bool
    {
        // Check the max length, according to the database column length
        if (!is_string($token) || strlen($token) > 191) {
            $this->message = \trans('validation.signuptokeninvalid');
            return false;
        }

        // Sanity check on the plan
        if (!$this->plan || $this->plan->mode != Plan::MODE_TOKEN) {
            $this->message = \trans('validation.signuptokeninvalid');
            return false;
        }

        // Check the token existence
        if (!$this->plan->signupTokens()->find($token)) {
            $this->message = \trans('validation.signuptokeninvalid');
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
