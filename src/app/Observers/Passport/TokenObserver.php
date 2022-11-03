<?php

namespace App\Observers\Passport;

use Laravel\Passport\Token;

class TokenObserver
{
    public function creating(Token $token): void
    {
        /** @var \App\Auth\PassportClient */
        $client = $token->client;
        $scopes = $token->scopes;
        if ($scopes) {
            $allowedScopes = $client->getAllowedScopes();
            if (!empty($allowedScopes)) {
                $scopes = array_intersect($scopes, $allowedScopes);
            }
            $scopes = array_unique($scopes, SORT_REGULAR);
            $token->scopes = $scopes;
        }
    }
}
