<?php

namespace App\Rules;

use App\Policy\Password as PasswordPolicy;
use App\User;
use Illuminate\Contracts\Validation\Rule;

class Password implements Rule
{
    /** @var ?string The validation error message */
    private $message;

    /** @var ?User The account owner which to take the policy from */
    private $owner;

    /** @var ?User The user to whom the checked password belongs */
    private $user;

    /**
     * Class constructor.
     *
     * @param ?User $owner The account owner (to take the policy from)
     * @param ?User $user  The user the password is for (Null for a new user)
     */
    public function __construct(?User $owner = null, ?User $user = null)
    {
        $this->owner = $owner;
        $this->user = $user;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $password  Password string
     */
    public function passes($attribute, $password): bool
    {
        foreach (PasswordPolicy::checkPolicy($password, $this->user, $this->owner) as $rule) {
            if (empty($rule['status'])) {
                $this->message = \trans('validation.password-policy-error');
                return false;
            }
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
