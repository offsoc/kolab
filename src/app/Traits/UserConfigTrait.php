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
            } else {
                $errors[$key] = \trans('validation.invalid-config-parameter');
            }
        }

        return $errors;
    }
}
