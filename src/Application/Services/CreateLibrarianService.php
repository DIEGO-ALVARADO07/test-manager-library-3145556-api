<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\UserRepository;
use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\Exceptions\UsernameTaken;
use Library\Domain\User;
use Library\Domain\ValueObjects\Role;
use Library\Domain\ValueObjects\Uuid;

/** HU-02. The admin role can never be requested through the API (CA-02.3). */
final class CreateLibrarianService
{
    public function __construct(private readonly UserRepository $users, private readonly PasswordHasher $hasher)
    {
    }

    public function create(string $username, string $password, string $role): User
    {
        if ($role !== Role::Librarian->value) {
            throw new InvalidValue('Solo se puede crear un usuario con el rol librarian.', [
                ['field' => 'role', 'issue' => 'solo se admite librarian'],
            ]);
        }

        $user = new User(Uuid::v4(), $username, $this->hasher->hash($password), Role::Librarian);
        if ($this->users->findByUsername($user->username) !== null) {
            throw new UsernameTaken('El nombre de usuario ya existe.');
        }
        $this->users->insert($user);

        return $user;
    }
}
