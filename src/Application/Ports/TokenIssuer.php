<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\ValueObjects\Role;

interface TokenIssuer
{
    public function issue(string $userId, Role $role): IssuedToken;

    /** Returns null when the token is malformed, altered or expired. */
    public function verify(string $token): ?TokenClaims;
}
