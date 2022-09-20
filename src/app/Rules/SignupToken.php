<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class SignupToken implements Rule
{
    protected $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $token     The value to validate
     *
     * @return bool
     */
    public function passes($attribute, $token): bool
    {
        // Check the max length, according to the database column length
        if (!is_string($token) || strlen($token) > 191) {
            $this->message = \trans('validation.signuptokeninvalid');
            return false;
        }

        // Check the list of tokens for token existence
        $file = storage_path('signup-tokens.txt');
        $list = [];
        $token = \strtoupper($token);

        if (file_exists($file)) {
            $list = file($file);
            $list = array_map('trim', $list);
            $list = array_map('strtoupper', $list);
        } else {
            \Log::error("Signup tokens file ({$file}) does not exist");
        }

        if (!in_array($token, $list)) {
            $this->message = \trans('validation.signuptokeninvalid');
            return false;
        }

        // Check if the token has been already used for registration (exclude deleted users)
        $used = \App\User::select()
            ->join('user_settings', 'users.id', '=', 'user_settings.user_id')
            ->where('user_settings.key', 'signup_token')
            ->where('user_settings.value', $token)
            ->exists();

        if ($used) {
            $this->message = \trans('validation.signuptokeninvalid');
            return false;
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
}
