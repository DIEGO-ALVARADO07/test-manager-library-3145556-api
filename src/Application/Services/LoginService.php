<?php

declare(strict_types=1);

namespace Library\Application\Services;

use Library\Application\Ports\IssuedToken;
use Library\Application\Ports\PasswordHasher;
use Library\Application\Ports\TokenIssuer;
use Library\Application\Ports\UserRepository;
use Library\Domain\Exceptions\InvalidCredentials;
use Library\Domain\User;
use Library\Domain\ValueObjects\Role;

/** HU-01. The failure message never says whether the user or the password was wrong (CA-01.2). */
final class LoginService
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly PasswordHasher $hasher,
        private readonly TokenIssuer $tokens,
    ) {
    }

    /** @return array{token: IssuedToken, role: Role} */
    public function login(string $username, string $password): array
    {
        $user = $this->users->findByUsername(User::normalize($username));
        if ($user === null) {
            // Spend the same time as a real check so the response does not reveal which part failed.
            $this->hasher->verify($password, $this->hasher->hash($password));
            throw new InvalidCredentials('Credenciales inválidas.');
        }
        if (! $this->hasher->verify($password, $user->passwordHash)) {
            throw new InvalidCredentials('Credenciales inválidas.');
        }

        return ['token' => $this->tokens->issue($user->id, $user->role), 'role' => $user->role];
    }
}
