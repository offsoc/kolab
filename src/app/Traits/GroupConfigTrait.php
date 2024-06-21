<?php

namespace App\Traits;

trait GroupConfigTrait
{
    /**
     * A helper to get the group configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        $sp = $this->getSetting('sender_policy');

        $config['sender_policy'] = array_filter(
            $sp ? json_decode($sp, true) : [],
            function ($item) {
                // remove the special "-" entry, it's an implementation detail
                return $item !== '-';
            }
        );

        return $config;
    }

    /**
     * A helper to update a group configuration.
     *
     * @param array $config An array of configuration options
     *
     * @return array A list of input validation errors
     */
    public function setConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $key => $value) {
            // validate and save SMTP sender policy entries
            if ($key === 'sender_policy') {
                if (!is_array($value)) {
                    $value = (array) $value;
                }

                foreach ($value as $i => $v) {
                    if (!is_string($v)) {
                        $errors[$key][$i] = \trans('validation.sp-entry-invalid');
                    }
                }

                if (empty($errors[$key])) {
                    // remove empty entries, and '-' entry
                    $value = array_filter($value, function ($item) {
                        return strlen($item) > 0 && $item !== '-';
                    });

                    if (!empty($value)) {
                        $value[] = '-'; // Block anyone not on the list
                    }

                    $this->setSetting($key, json_encode($value));
                }
            } else {
                $errors[$key] = \trans('validation.invalid-config-parameter');
            }
        }

        return $errors;
    }
}
