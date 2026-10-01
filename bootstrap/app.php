<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\RoleMiddleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\ActiveAccount::class, \Illuminate\Session\Middleware\AuthenticateSession::class, \App\Http\Middleware\RequireStaffActivation::class, \App\Http\Middleware\RequireStaffAccess::class, \App\Http\Middleware\SecurityTransaction::class]);
        // Local port-forwarding proxies terminate HTTPS before reaching PHP.
        // Trust only loopback peers, not arbitrary clients on the network.
        $middleware->trustProxies(
            at: ['127.0.0.1', '::1'],
            headers: \Illuminate\Http\Request::HEADER_X_FORWARDED_PROTO
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_HOST
                | \Illuminate\Http\Request::HEADER_X_FORWARDED_PORT,
        );
        $middleware->alias([
        'role' => RoleMiddleware::class,]);
    })
    
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['current_password', 'password', 'password_confirmation', 'code']);
    })->create();
