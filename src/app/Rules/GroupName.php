<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GroupName implements Rule
{
    private $message;
    private $owner;
    private $domain;

    /**
     * Class constructor.
     *
     * @param \App\User $owner  The account owner
     * @param string    $domain The domain name of the group
     */
    public function __construct($owner, $domain)
    {
        $this->owner = $owner;
        $this->domain = Str::lower($domain);
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
            $this->message = \trans('validation.nameinvalid');
            return false;
        }

        // Check the max length, according to the database column length
        if (strlen($name) > 191) {
            $this->message = \trans('validation.max.string', ['max' => 191]);
            return false;
        }

        // Check if the name is unique in the domain
        // FIXME: Maybe just using the whole groups table would be faster than groups()?
        $exists = $this->owner->groups()
            ->where('groups.name', $name)
            ->where('groups.email', 'like', '%@' . $this->domain)
            ->exists();

        if ($exists) {
            $this->message = \trans('validation.nameexists');
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
