<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Password implements Rule
{
    /** @var ?string The validation error message */
    private $message;

    /** @var ?\App\User The account owner which to take the policy from */
    private $owner;

    /** @var ?\App\User The user to whom the checked password belongs */
    private $user;

    /**
     * Class constructor.
     *
     * @param ?\App\User $owner The account owner (to take the policy from)
     * @param ?\App\User $user  The user the password is for (Null for a new user)
     */
    public function __construct(?\App\User $owner = null, ?\App\User $user = null)
    {
        $this->owner = $owner;
        $this->user = $user;
    }

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $password  Password string
     *
     * @return bool
     */
    public function passes($attribute, $password): bool
    {
        foreach ($this->check($password) as $rule) {
            if (empty($rule['status'])) {
                $this->message = \trans('validation.password-policy-error');
                return false;
            }
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

    /**
     * Check a password against the policy rules
     *
     * @param string $password The password
     */
    public function check($password): array
    {
        $rules = $this->rules();

        foreach ($rules as $name => $rule) {
            switch ($name) {
                case 'min':
                    // Check the min length
                    $status = strlen($password) >= intval($rule['param']);
                    break;

                case 'max':
                    // Check the max length
                    $length = strlen($password);
                    $status = $length && $length <= intval($rule['param']);
                    break;

                case 'lower':
                    // Check if password contains a lower-case character
                    $status = preg_match('/[a-z]/', $password) > 0;
                    break;

                case 'upper':
                    // Check if password contains a upper-case character
                    $status = preg_match('/[A-Z]/', $password) > 0;
                    break;

                case 'digit':
                    // Check if password contains a digit
                    $status = preg_match('/[0-9]/', $password) > 0;
                    break;

                case 'special':
                    // Check if password contains a special character
                    $status = preg_match('/[-~!@#$%^&*_+=`(){}[]|:;"\'`<>,.?\/\\]/', $password) > 0;
                    break;

                case 'last':
                    // TODO: For performance reasons we might consider checking the history
                    //       only when the password passed all other checks
                    $status = $this->checkPasswordHistory($password, (int) $rule['param']);
                    break;

                default:
                    // Ignore unknown rule name
                    $status = true;
            }

            $rules[$name]['status'] = $status;
        }

        return $rules;
    }

    /**
     * Get the list of rules for a password
     *
     * @param bool $all List all supported rules, instead of the enabled ones
     *
     * @return array List of rule definitions
     */
    public function rules(bool $all = false): array
    {
        // All supported password policy rules (with default params)
        $supported = 'min:6,max:255,lower,upper,digit,special,last:3';

        // Get the password policy from the $owner settings
        if ($this->owner) {
            $conf = $this->owner->getSetting('password_policy');
        }

        // Fallback to the configured policy
        if (empty($conf)) {
            $conf = \config('app.password_policy');
        }

        $supported = self::parsePolicy($supported);
        $conf = self::parsePolicy($conf);
        $rules = $all ? $supported : $conf;

        foreach ($rules as $idx => $rule) {
            $param = $rule;

            if ($all && array_key_exists($idx, $conf)) {
                $param = $conf[$idx];
                $enabled = true;
            } else {
                $enabled = !$all;
            }

            $rules[$idx] = [
                'label' => $idx,
                'name' => \trans("app.password-rule-{$idx}", ['param' => $param]),
                'param' => $param,
                'enabled' => $enabled,
            ];
        }

        return $rules;
    }

    /**
     * Parse configured policy string
     *
     * @param ?string $policy Policy specification
     *
     * @return array Policy specification as an array indexed by the policy rule type
     */
    public static function parsePolicy(?string $policy): array
    {
        $policy = explode(',', strtolower((string) $policy));
        $policy = array_map('trim', $policy);
        $policy = array_unique(array_filter($policy));

        return self::mapWithKeys($policy);
    }

    /**
     * Check password agains <count> of old passwords in user history
     *
     * @param string $password The password to check
     * @param int    $count    Number of old passwords to check (including current one)
     *
     * @return bool True if password is unique, False otherwise
     */
    protected function checkPasswordHistory($password, int $count): bool
    {
        $status = strlen($password) > 0;

        // Check if password is not the same as last X passwords
        if ($status && $this->user && $count > 0) {
            // Current password
            if ($this->user->password) {
                $count -= 1;
                if (Hash::check($password, $this->user->password)) {
                    return false;
                }
            }

            // Passwords from the history
            if ($count > 0) {
                $this->user->passwords()->latest()->limit($count)->get()
                    ->each(function ($oldPassword) use (&$status, $password) {
                        if (Hash::check($password, $oldPassword->password)) {
                            $status = false;
                            return false; // stop iteration
                        }
                    });
            }
        }

        return $status;
    }

    /**
     * Convert an array with password policy rules into one indexed by the rule name
     *
     * @param array $rules The rules list
     *
     * @return array
     */
    private static function mapWithKeys(array $rules): array
    {
        $result = [];

        foreach ($rules as $rule) {
            $key = $rule;
            $value = null;

            if (strpos($key, ':')) {
                list($key, $value) = explode(':', $key, 2);
            }

            $result[$key] = $value;
        }

        return $result;
    }
}
