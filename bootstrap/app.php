<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Library\Adapters\Inbound\Http\Controllers\AuthController;
use Library\Adapters\Inbound\Http\ErrorMapper;
use Library\Adapters\Inbound\Http\Middleware\JwtAuth;
use Library\Adapters\Inbound\Http\Middleware\RequireRole;
use Library\Domain\Exceptions\DomainError;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        then: function (): void {
            // H-02: the health check is also reachable outside the versioned prefix.
            Route::get('/health', [AuthController::class, 'health']);
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'auth.jwt' => JwtAuth::class,
            'role' => RequireRole::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // RNF-4: every error, framework or business, uses the single body {code, message, details}.
        $exceptions->shouldRenderJsonWhen(static fn (Request $request): bool => true);
        $exceptions->dontReport([DomainError::class]);
        $exceptions->render(static fn (Throwable $e, Request $request) => ErrorMapper::respond($e));
    })->create();
