<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class FileName implements Rule
{
    private $message;
    private $owner;

    /**
     * Class constructor.
     *
     * @param \App\User $owner  The file owner
     */
    public function __construct($owner)
    {
        $this->owner = $owner;
    }

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

        // Check if the name is unique
        $exists = $this->owner->fsItems()
            ->join('fs_properties', 'fs_items.id', '=', 'fs_properties.item_id')
            ->where('key', 'name')
            ->where('value', $name)
            ->exists();

        if ($exists) {
            $this->message = \trans('validation.file-name-exists');
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
