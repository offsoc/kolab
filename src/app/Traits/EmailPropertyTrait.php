<?php

namespace App\Traits;

trait EmailPropertyTrait
{
    /** @var ?string Domain name for the to-be-created object */
    public $domainName;


    /**
     * Boot function from Laravel.
     */
    protected static function bootEmailPropertyTrait()
    {
        static::creating(function ($model) {
            if (empty($model->email) && defined('static::EMAIL_TEMPLATE')) {
                $template = static::EMAIL_TEMPLATE; // @phpstan-ignore-line
                $defaults = [
                    'type' => 'mail',
                ];

                foreach (['id', 'domainName', 'type'] as $prop) {
                    if (strpos($template, "{{$prop}}") === false) {
                        continue;
                    }

                    $value = $model->{$prop} ?? ($defaults[$prop] ?? '');

                    if ($value === '' || $value === null) {
                        throw new \Exception("Missing '{$prop}' property for " . static::class);
                    }

                    $template = str_replace("{{$prop}}", $value, $template);
                }

                $model->email = strtolower($template);
            }
        });
    }

    /**
     * Returns the object's domain (including soft-deleted).
     *
     * @return ?\App\Domain The domain to which the object belongs to, NULL if it does not exist
     */
    public function domain(): ?\App\Domain
    {
        if (empty($this->email) && isset($this->domainName)) {
            $domainName = $this->domainName;
        } else {
            list($local, $domainName) = explode('@', $this->email);
        }

        return \App\Domain::withTrashed()->where('namespace', $domainName)->first();
    }

    /**
     * Find whether an email address exists as a model object (including soft-deleted).
     *
     * @param string $email         Email address
     * @param bool   $return_object Return model instance instead of a boolean
     *
     * @return object|bool True or Model object if found, False otherwise
     */
    public static function emailExists(string $email, bool $return_object = false)
    {
        if (strpos($email, '@') === false) {
            return false;
        }

        $email = \strtolower($email);

        $object = static::withTrashed()->where('email', $email)->first();

        if ($object) {
            return $return_object ? $object : true;
        }

        return false;
    }

    /**
     * Ensure the email is appropriately cased.
     *
     * @param string $email Email address
     */
    public function setEmailAttribute(string $email): void
    {
        $this->attributes['email'] = strtolower($email);
    }
}
