<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;

class FileName implements Rule
{
    private $message;

    /**
     * Determine if the validation rule passes.
     *
     * @param string $attribute Attribute name
     * @param mixed  $name      The value to validate
     *
     * @return bool
     */
    public function passes($attribute, $name): bool
    {
        if (empty($name) || !is_string($name)) {
            $this->message = \trans('validation.file-name-invalid');
            return false;
        }

        // Check the max length, according to the database column length
        if (strlen($name) > 512) {
            $this->message = \trans('validation.max.string', ['max' => 512]);
            return false;
        }

        // Non-allowed characters
        if (preg_match('|[\x00-\x1F\/*"\x7F]|', $name)) {
            $this->message = \trans('validation.file-name-invalid');
            return false;
        }

        // Leading/trailing spaces, or all spaces
        if (preg_match('|^\s+$|', $name) || preg_match('|^\s+|', $name) || preg_match('|\s+$|', $name)) {
            $this->message = \trans('validation.file-name-invalid');
            return false;
        }

        // FIXME: Should we require a dot?

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
