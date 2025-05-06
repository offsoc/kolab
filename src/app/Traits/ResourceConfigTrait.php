<?php

namespace App\Traits;

use App\User;
use Illuminate\Support\Facades\Validator;

trait ResourceConfigTrait
{
    /**
     * A helper to get a resource configuration.
     */
    public function getConfig(): array
    {
        $config = [];

        $config['invitation_policy'] = $this->getSetting('invitation_policy') ?: 'accept';

        return $config;
    }

    /**
     * A helper to update a resource configuration.
     *
     * @param array $config An array of configuration options
     *
     * @return array A list of input validation errors
     */
    public function setConfig(array $config): array
    {
        $errors = [];

        foreach ($config as $key => $value) {
            // validate and save the invitation policy
            if ($key === 'invitation_policy') {
                $value = (string) $value;
                if ($value === 'accept' || $value === 'reject') {
                    // do nothing
                } elseif (preg_match('/^manual:/', $value, $matches)) {
                    $email = trim(substr($value, 7));
                    if ($error = $this->validateInvitationPolicyUser($email)) {
                        $errors[$key] = $error;
                    } else {
                        $value = "manual:{$email}";
                    }
                } else {
                    $errors[$key] = \trans('validation.ipolicy-invalid');
                }

                if (empty($errors[$key])) {
                    $this->setSetting($key, $value);
                }
            } else {
                $errors[$key] = \trans('validation.invalid-config-parameter');
            }
        }

        return $errors;
    }

    /**
     * Validate an email address for use as a resource owner (with invitation policy)
     *
     * @param string $email Email address
     *
     * @return ?string Error message on validation error
     */
    protected function validateInvitationPolicyUser($email): ?string
    {
        $v = Validator::make(['email' => $email], ['email' => 'required|email']);

        if ($v->fails()) {
            return \trans('validation.emailinvalid');
        }

        $user = User::where('email', \strtolower($email))->first();

        // The user and resource must be in the same wallet
        if ($user && ($wallet = $user->wallet())) {
            if ($wallet->user_id == $this->wallet()->user_id) {
                return null;
            }
        }

        return \trans('validation.notalocaluser');
    }
}
