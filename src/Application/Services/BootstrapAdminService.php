<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\UserRepository;
use Library\Domain\User;
use Library\Domain\ValueObjects\Role;
use Library\Domain\ValueObjects\Uuid;

/** D-10: the initial admin comes from the environment; nobody grants it at runtime. Idempotent. */
final class BootstrapAdminService
{
    public function __construct(private readonly UserRepository $users, private readonly PasswordHasher $hasher)
    {
    }

    /** @return bool true when the admin was created, false when it already existed */
    public function ensure(string $username, string $password): bool
    {
        $username = User::normalize($username);
        if ($this->users->findByUsername($username) !== null) {
            return false;
        }
        $this->users->insert(new User(Uuid::v4(), $username, $this->hasher->hash($password), Role::Admin));

        return true;
    }
}
