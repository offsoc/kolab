<?php

namespace App\Auth;

use Illuminate\Database\Eloquent\Collection;

/**
 * Passport Client extended with allowed scopes
 */
class PassportClient extends \Laravel\Passport\Client
{
    /** @var array<string, string> The attributes that should be cast */
    protected $casts = [
        'allowed_scopes' => 'array',
    ];

    /**
     * The allowed scopes for tokens instantiated by this client
     */
    public function getAllowedScopes(): array
    {
        if ($this->allowed_scopes) {
            return $this->allowed_scopes;
        }
        return [];
    }
}
