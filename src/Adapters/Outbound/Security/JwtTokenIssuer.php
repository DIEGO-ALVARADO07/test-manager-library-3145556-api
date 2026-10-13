<?php

declare(strict_types=1);

namespace Library\Adapters\Outbound\Security;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Library\Application\Ports\Clock;
use Library\Application\Ports\IssuedToken;
use Library\Application\Ports\TokenClaims;
use Library\Application\Ports\TokenIssuer;
use Library\Domain\ValueObjects\Role;
use Throwable;

/** HS256 JWT, 8 hours, only `sub` and `role` besides the time claims (ADR-007). */
final class JwtTokenIssuer implements TokenIssuer
{
    private const ALGORITHM = 'HS256';

    public function __construct(
        private readonly string $secret,
        private readonly int $ttlSeconds,
        private readonly Clock $clock,
    ) {
    }

    public function issue(string $userId, Role $role): IssuedToken
    {
        $now = $this->clock->now()->getTimestamp();
        $token = JWT::encode([
            'sub' => $userId,
            'role' => $role->value,
            'iat' => $now,
            'exp' => $now + $this->ttlSeconds,
        ], $this->secret, self::ALGORITHM);

        return new IssuedToken($token, $this->ttlSeconds);
    }

    public function verify(string $token): ?TokenClaims
    {
        JWT::$timestamp = $this->clock->now()->getTimestamp();
        try {
            $payload = JWT::decode($token, new Key($this->secret, self::ALGORITHM));
            $role = Role::tryFrom((string) ($payload->role ?? ''));
            if ($role === null || ! is_string($payload->sub ?? null)) {
                return null;
            }

            return new TokenClaims($payload->sub, $role);
        } catch (Throwable) {
            return null;
        } finally {
            JWT::$timestamp = null;
        }
    }
}
