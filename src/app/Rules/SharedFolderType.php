<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class SharedFolderType implements Rule
{
    private $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $type      Shared folder type input
     */
    public function passes($attribute, $type): bool
    {
        if (empty($type) || !is_string($type) || !in_array($type, \config('app.shared_folder_types'))) {
            $this->message = \trans('validation.entryinvalid', ['attribute' => $attribute]);
            return false;
        }

        return true;
    }

    /**
     * Get the validation error message.
     */
    public function message(): ?string
    {
        return $this->message;
    }
}
