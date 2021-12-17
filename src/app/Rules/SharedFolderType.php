<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SharedFolderType implements Rule
{
    private $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $type      Shared folder type input
     *
     * @return bool
     */
    public function passes($attribute, $type): bool
    {
        if (empty($type) || !is_string($type) || !in_array($type, \App\SharedFolder::SUPPORTED_TYPES)) {
            $this->message = \trans('validation.entryinvalid', ['attribute' => $attribute]);
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
