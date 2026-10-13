<?php

declare(strict_types=1);

namespace Library\Application\Ports;

use Library\Domain\User;

interface UserRepository
{
    public function findByUsername(string $username): ?User;

    /** @throws \Library\Domain\Exceptions\UsernameTaken */
    public function insert(User $user): void;
}
