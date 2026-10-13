<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Middleware;

use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Library\Application\Ports\TokenIssuer;
use Symfony\Component\HttpFoundation\Response;

/** CA-01.3: every endpoint but login and health needs a valid Bearer token. */
final class JwtAuth
{
    public function __construct(private readonly TokenIssuer $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $claims = $token === null || $token === '' ? null : $this->tokens->verify($token);
        if ($claims === null) {
            throw new AuthenticationException();
        }

        $request->attributes->set('auth_user_id', $claims->userId);
        $request->attributes->set('auth_role', $claims->role);

        return $next($request);
    }
}
