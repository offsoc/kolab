<?php

namespace App\Auth;

use OpenIDConnect\Interfaces\IdentityEntityInterface;
use OpenIDConnect\Interfaces\IdentityRepositoryInterface;

class IdentityRepository implements IdentityRepositoryInterface
{
    public function getByIdentifier(string $identifier): IdentityEntityInterface
    {
        $identityEntity = new IdentityEntity();
        $identityEntity->setIdentifier($identifier);

        return $identityEntity;
    }
}
