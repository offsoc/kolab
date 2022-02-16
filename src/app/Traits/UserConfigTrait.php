<?php

namespace App\Traits;

use App\Policy\Greylist;

trait UserConfigTrait
{
    /**
     * A helper to get the user configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        // TODO: Should we store the default value somewhere in config?

        $config['greylist_enabled'] = $this->getSetting('greylist_enabled') !== 'false';
        $config['password_policy'] = $this->getSetting('password_policy');

        return $config;
    }

    /**
     * A helper to update user configuration.
     *
     * @param array $config An array of configuration options
     *
     * @return array A list of input validation error messages
     */
    public function setConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $key => $value) {
            if ($key == 'greylist_enabled') {
                $this->setSetting('greylist_enabled', $value ? 'true' : 'false');
            } elseif ($key == 'password_policy') {
                // Validate the syntax and make sure min and max is included
                if (
                    !is_string($value)
                    || strpos($value, 'min:') === false
                    || strpos($value, 'max:') === false
                    || !preg_match('/^[a-z0-9:,]+$/', $value)
                ) {
                    $errors[$key] = \trans('validation.invalid-password-policy');
                    continue;
                }

                foreach (explode(',', $value) as $rule) {
                    if ($error = $this->validatePasswordPolicyRule($rule)) {
                        $errors[$key] = $error;
                        continue 2;
                    }
                }

                $this->setSetting('password_policy', $value);
            } else {
                $errors[$key] = \trans('validation.invalid-config-parameter');
            }
        }

        return $errors;
    }

    /**
     * Validates password policy rule.
     *
     * @param string $rule Policy rule
     *
     * @return ?string An error message on error, Null otherwise
     */
    protected function validatePasswordPolicyRule(string $rule): ?string
    {
        $regexp = [
            'min:[0-9]+', 'max:[0-9]+', 'upper', 'lower', 'digit', 'special', 'last:[0-9]+'
        ];

        if (empty($rule) || !preg_match('/^(' . implode('|', $regexp) . ')$/', $rule)) {
            return \trans('validation.invalid-password-policy');
        }

        $systemPolicy = \App\Rules\Password::parsePolicy(\config('app.password_policy'));

        // Min/Max values cannot exceed the system defaults, i.e. if system policy
        // is min:5, user's policy cannot be set to a smaller number.
        if (!empty($systemPolicy['min']) && strpos($rule, 'min:') === 0) {
            $value = trim(substr($rule, 4));
            if ($value < $systemPolicy['min']) {
                return \trans('validation.password-policy-min-len-error', ['min' => $systemPolicy['min']]);
            }
        }

        if (!empty($systemPolicy['max']) && strpos($rule, 'max:') === 0) {
            $value = trim(substr($rule, 4));
            if ($value > $systemPolicy['max']) {
                return \trans('validation.password-policy-max-len-error', ['max' => $systemPolicy['max']]);
            }
        }

        if (!empty($systemPolicy['last']) && strpos($rule, 'last:') === 0) {
            $value = trim(substr($rule, 5));
            if ($value < $systemPolicy['last']) {
                return \trans('validation.password-policy-last-error', ['last' => $systemPolicy['last']]);
            }
        }

        return null;
    }
}
