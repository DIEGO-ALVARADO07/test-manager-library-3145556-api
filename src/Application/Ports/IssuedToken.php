<?php

declare(strict_types=1);

namespace Library\Application\Ports;

final readonly class IssuedToken
{
    public function __construct(public string $token, public int $expiresIn)
    {
    }
}
