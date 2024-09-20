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
        $settings = $this->getSettings([
                'greylist_enabled',
                'guam_enabled',
                'password_policy',
                'max_password_age',
                'limit_geo'
        ]);

        $config = [
            'greylist_enabled' => $settings['greylist_enabled'] !== 'false',
            'guam_enabled' => $settings['guam_enabled'] === 'true',
            'limit_geo' => $settings['limit_geo'] ? json_decode($settings['limit_geo'], true) : [],
            'max_password_age' => $settings['max_password_age'],
            'password_policy' => $settings['password_policy'],
        ];

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
                $this->setSetting($key, $value ? 'true' : 'false');
            } elseif ($key == 'guam_enabled') {
                $this->setSetting($key, $value ? 'true' : null);
            } elseif ($key == 'limit_geo') {
                if ($error = $this->validateLimitGeo($value)) {
                    $errors[$key] = $error;
                    continue;
                }

                $this->setSetting($key, !empty($value) ? json_encode($value) : null);
            } elseif ($key == 'max_password_age') {
                $this->setSetting($key, intval($value) > 0 ? ((string) intval($value)) : null);
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

                $this->setSetting($key, $value);
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

    /**
     * Validates limit_geo value
     *
     * @param mixed $value Geo-lock input
     *
     * @return ?string An error message on error, Null otherwise
     */
    protected function validateLimitGeo(&$value): ?string
    {
        if (!is_array($value)) {
            return \trans('validation.invalid-limit-geo');
        }

        foreach ($value as $idx => $country) {
            if (!preg_match('/^[a-zA-Z]{2}$/', $country)) {
                return \trans('validation.invalid-limit-geo');
            }

            $value[$idx] = \strtoupper($country);
        }

        if (count($value) > 250) {
            return \trans('validation.invalid-limit-geo');
        } elseif (count($value)) {
            // There MUST be country of the current connection included
            $currentCountry = \App\Utils::countryForRequest();

            if (!in_array($currentCountry, $value)) {
                return \trans('validation.invalid-limit-geo-missing-current', ['code' => $currentCountry]);
            }
        }

        return null;
    }
}
