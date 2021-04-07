<?php

namespace App\Traits;

trait UserConfigTrait
{
    /**
     * A helper to get the user configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        // TODO: Should we store the default value somewhere in config?

        $config['greylisting'] = $this->getSetting('greylisting') !== 'false';

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
            if ($key == 'greylisting') {
                $this->setSetting('greylisting', $value ? 'true' : 'false');
            } else {
                $errors[$key] = \trans('validation.invalid-config-parameter');
            }
        }

        return $errors;
    }
}
