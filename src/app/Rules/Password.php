<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class Password implements Rule
{
    private $message;
    private $owner;

    /**
     * Class constructor.
     *
     * @param \App\User $owner The account owner (to take the policy from)
     */
    public function __construct(?\App\User $owner = null)
    {
        $this->owner = $owner;
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
                    $pass = strlen($password) >= intval($rule['param']);
                    break;

                case 'max':
                    // Check the max length
                    $length = strlen($password);
                    $pass = $length && $length <= intval($rule['param']);
                    break;

                case 'lower':
                    // Check if password contains a lower-case character
                    $pass = preg_match('/[a-z]/', $password) > 0;
                    break;

                case 'upper':
                    // Check if password contains a upper-case character
                    $pass = preg_match('/[A-Z]/', $password) > 0;
                    break;

                case 'digit':
                    // Check if password contains a digit
                    $pass = preg_match('/[0-9]/', $password) > 0;
                    break;

                case 'special':
                    // Check if password contains a special character
                    $pass = preg_match('/[-~!@#$%^&*_+=`(){}[]|:;"\'`<>,.?\/\\]/', $password) > 0;
                    break;

                default:
                    // Ignore unknown rule name
                    $pass = true;
            }

            $rules[$name]['status'] = $pass;
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
        $supported = 'min:6,max:255,lower,upper,digit,special';

        // Get the password policy from the $owner settings
        if ($this->owner) {
            $conf = $this->owner->getSetting('password_policy');
        }

        // Fallback to the configured policy
        if (empty($conf)) {
            $conf = \config('app.password_policy');
        }

        // Default policy, if not set
        if (empty($conf)) {
            $conf = 'min:6,max:255';
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
