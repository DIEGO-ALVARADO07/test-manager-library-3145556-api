<?php

declare(strict_types=1);

namespace Library\Adapters\Inbound\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Library\Domain\Exceptions\Forbidden;
use Library\Domain\ValueObjects\Role;
use Symfony\Component\HttpFoundation\Response;

/** Usage: role:admin  or  role:admin,librarian. Evaluated before the resource is looked up (a 403 never reveals existence). */
final class RequireRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        /** @var Role|null $role */
        $role = $request->attributes->get('auth_role');
        if ($role === null || ! in_array($role->value, $roles, true)) {
            throw new Forbidden('Su rol no tiene permiso para esta operación.');
        }

        return $next($request);
    }
}
