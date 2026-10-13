<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\ValueObjects\Role;

final readonly class TokenClaims
{
    public function __construct(public string $userId, public Role $role)
    {
    }
}
