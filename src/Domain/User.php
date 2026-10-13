<?php

declare(strict_types=1);

namespace Library\Domain;

use Library\Domain\Exceptions\InvalidValue;
use Library\Domain\ValueObjects\Role;

final class User
{
    public readonly string $username;

    public function __construct(
        public readonly string $id,
        string $username,
        public readonly string $passwordHash,
        public readonly Role $role,
    ) {
        $this->username = self::normalize($username);
        if ($this->username === '' || mb_strlen($this->username) > 120) {
            throw new InvalidValue('El nombre de usuario es obligatorio y admite hasta 120 caracteres.', [
                ['field' => 'username', 'issue' => 'obligatorio, máximo 120 caracteres'],
            ]);
        }
    }

    /** Lowercase and trimmed: "Ana " becomes "ana" (CA-02.2). */
    public static function normalize(string $username): string
    {
        return mb_strtolower(trim($username));
    }
}
