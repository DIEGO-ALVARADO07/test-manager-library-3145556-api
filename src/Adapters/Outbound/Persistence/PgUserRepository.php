<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Persistence;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Library\Application\Ports\UserRepository;
use Library\Domain\Exceptions\UsernameTaken;
use Library\Domain\User;
use Library\Domain\ValueObjects\Role;

final class PgUserRepository implements UserRepository
{
    public function findByUsername(string $username): ?User
    {
        $row = DB::table('user')->where('username', $username)->first();

        return $row === null ? null : new User($row->id, $row->username, $row->password_hash, Role::from($row->role));
    }

    public function insert(User $user): void
    {
        try {
            DB::table('user')->insert([
                'id' => $user->id,
                'username' => $user->username,
                'password_hash' => $user->passwordHash,
                'role' => $user->role->value,
            ]);
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new UsernameTaken('El nombre de usuario ya existe.');
            }
            throw $e;
        }
    }
}
