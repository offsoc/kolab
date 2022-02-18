<?php

namespace App\Rules;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SharedFolderName implements Rule
{
    private $message;
    private $owner;
    private $domain;

    private const FORBIDDEN_CHARS = '+/^%*!`@(){}|\\?<;"';

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
     * @param mixed  $name      Shared folder name input
     *
     * @return bool
     */
    public function passes($attribute, $name): bool
    {
        if (empty($name) || !is_string($name) || $name == 'Resources') {
            $this->message = \trans('validation.nameinvalid');
            return false;
        }

        if (strcspn($name, self::FORBIDDEN_CHARS) < strlen($name)) {
            $this->message = \trans('validation.nameinvalid');
            return false;
        }

        // Check the max length, according to the database column length
        if (strlen($name) > 191) {
            $this->message = \trans('validation.max.string', ['max' => 191]);
            return false;
        }

        // Check if specified domain belongs to the user
        if (!$this->owner->domains(true, false)->where('namespace', $this->domain)->exists()) {
            $this->message = \trans('validation.domaininvalid');
            return false;
        }

        // Check if the name is unique in the domain
        // FIXME: Maybe just using the whole shared_folders table would be faster than sharedFolders()?
        $exists = $this->owner->sharedFolders()
            ->where('name', $name)
            ->where('email', 'like', '%@' . $this->domain)
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