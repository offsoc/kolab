<?php

namespace App\Traits;

use App\User;
use Illuminate\Support\Facades\Validator;

trait SharedFolderConfigTrait
{
    /**
     * A helper to get a shared folder configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        $settings = $this->getSettings(['acl']);

        $config['acl'] = !empty($settings['acl']) ? json_decode($settings['acl'], true) : [];

        return $config;
    }

    /**
     * A helper to update a shared folder configuration.
     *
     * @param array $config An array of configuration options
     *
     * @return array A list of input validation errors
     */
    public function setConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $key => $value) {
            // validate and save the acl
            if ($key === 'acl') {
                // Here's the list of acl labels supported by kolabd
                //    'all': 'lrsedntxakcpiw',
                //    'append': 'wip',
                //    'full': 'lrswipkxtecdn',
                //    'read': 'lrs',
                //    'read-only': 'lrs',
                //    'read-write': 'lrswitedn',
                //    'post': 'p',
                //    'semi-full': 'lrswit',
                //    'write': 'lrswite',
                // For now we support read-only, read-write, and full

                if (!is_array($value)) {
                    $value = (array) $value;
                }

                $users = [];

                foreach ($value as $i => $v) {
                    if (!is_string($v) || empty($v) || !substr_count($v, ',')) {
                        $errors[$key][$i] = \trans('validation.acl-entry-invalid');
                    } else {
                        [$user, $acl] = explode(',', $v, 2);
                        $user = trim($user);
                        $acl = trim($acl);
                        $error = null;

                        if (
                            !in_array($acl, ['read-only', 'read-write', 'full'])
                            || ($error = $this->validateAclIdentifier($user))
                            || in_array($user, $users)
                        ) {
                            $errors[$key][$i] = $error ?: \trans('validation.acl-entry-invalid');
                        }

                        $value[$i] = "{$user}, {$acl}";
                        $users[] = $user;
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

    /**
     * Validate an ACL identifier.
     *
     * @param string $identifier Email address or a special identifier
     *
     * @return ?string Error message on validation error
     */
    protected function validateAclIdentifier(string $identifier): ?string
    {
        if ($identifier === 'anyone') {
            return null;
        }

        $v = Validator::make(['email' => $identifier], ['email' => 'required|email']);

        if ($v->fails()) {
            return \trans('validation.emailinvalid');
        }

        $user = User::where('email', \strtolower($identifier))->first();

        // The user and shared folder must be in the same wallet
        if ($user && ($wallet = $user->wallet())) {
            if ($wallet->user_id == $this->wallet()->user_id) {
                return null;
            }
        }

        return \trans('validation.notalocaluser');
    }
}
