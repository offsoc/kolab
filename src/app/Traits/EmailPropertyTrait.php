<?php

namespace App\Traits;

use App\Domain;

trait EmailPropertyTrait
{
    /** @var ?string Domain name for the to-be-created object */
    public $domainName;

    /**
     * Boot function from Laravel.
     */
    protected static function bootEmailPropertyTrait()
    {
        static::creating(static function ($model) {
            if (empty($model->email) && defined('static::EMAIL_TEMPLATE')) {
                $template = static::EMAIL_TEMPLATE; // @phpstan-ignore-line
                $defaults = [
                    'type' => 'mail',
                ];

                foreach (['id', 'domainName', 'type'] as $prop) {
                    if (!str_contains($template, "{{$prop}}")) {
                        continue;
                    }

                    $value = $model->{$prop} ?? ($defaults[$prop] ?? '');

                    if ($value === '') {
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
     * @return ?Domain The domain to which the object belongs to, NULL if it does not exist
     */
    public function domain(): ?Domain
    {
        if ($domain = $this->domainNamespace()) {
            return Domain::withTrashed()->where('namespace', $domain)->first();
        }

        return null;
    }

    /**
     * Returns the object's domain namespace.
     *
     * @return ?string The domain to which the object belongs to if it has email property is set
     */
    public function domainNamespace(): ?string
    {
        if (empty($this->email) && isset($this->domainName)) {
            return $this->domainName;
        }

        if (strpos($this->email, '@')) {
            [$local, $domain] = explode('@', $this->email);
            return $domain;
        }

        return null;
    }

    /**
     * Find whether an email address exists as a model object (including soft-deleted).
     *
     * @param string $email         Email address
     * @param bool   $return_object Return model instance instead of a boolean
     *
     * @return static|bool True or Model object if found, False otherwise
     */
    public static function emailExists(string $email, bool $return_object = false)
    {
        if (!str_contains($email, '@')) {
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
