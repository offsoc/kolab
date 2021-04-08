<?php

namespace App\Traits;

trait DomainConfigTrait
{
    /**
     * A helper to get the domain configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        $spf = $this->getSetting('spf_whitelist');

        $config['spf_whitelist'] = $spf ? json_decode($spf, true) : [];

        return $config;
    }

    /**
     * A helper to update domain configuration.
     *
     * @param array $config An array of configuration options
     *
     * @return array A list of input validation errors
     */
    public function setConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $key => $value) {
            // validate and save SPF whitelist entries
            if ($key === 'spf_whitelist') {
                if (!is_array($value)) {
                    $value = (array) $value;
                }

                foreach ($value as $i => $v) {
                    $v = rtrim($v, '.');

                    if (empty($v)) {
                        unset($value[$i]);
                        continue;
                    }

                    $value[$i] = $v;

                    if ($v[0] !== '.' || !filter_var(substr($v, 1), FILTER_VALIDATE_DOMAIN)) {
                        $errors[$key][$i] = \trans('validation.spf-entry-invalid');
                    }
                }

                if (empty($errors[$key])) {
                    $this->setSetting($key, json_encode($value));
                }
            } else {
                $errors[$key] = \trans('validation.invalid-config-parameter');
            }
        }

        return $errors;
    }
}
