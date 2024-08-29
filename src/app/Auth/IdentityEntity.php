<?php

namespace App\Auth;

use App\User;
use League\OAuth2\Server\Entities\Traits\EntityTrait;
use OpenIDConnect\Claims\Traits\WithClaims;
use OpenIDConnect\Interfaces\IdentityEntityInterface;

class IdentityEntity implements IdentityEntityInterface
{
    use EntityTrait;
    use WithClaims;

    /**
     * The user to collect the additional information for
     */
    protected User $user;

    /**
     * The identity repository creates this entity and provides the user id
     * @param mixed $identifier
     */
    public function setIdentifier($identifier): void
    {
        $this->identifier = $identifier;
        $this->user = User::findOrFail($identifier);
    }

    /**
     * When building the id_token, this entity's claims are collected
     */
    public function getClaims(): array
    {
        // TODO: Other claims
        // TODO: Should we use this in AuthController::oauthUserInfo() for some de-duplicaton?

        $claims = [
            'email' => $this->user->email,
        ];

        // Short living password for IMAP/SMTP
        // We use same TTL as for the OAuth tokens, so clients can get a new password on token refresh
        // TODO: We should create the password only when the access token scope requests it
        $ttl = config('auth.token_expiry_minutes') * 60;
        $claims['auth.token'] = Utils::tokenCreate((string) $this->user->id, $ttl);

        return $claims;
    }
}
