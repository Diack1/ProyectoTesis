<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequirePasswordChange
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user()?->must_change_password && ! $request->routeIs('password.first.*', 'logout')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Debes cambiar tu contraseña inicial antes de continuar.'], 403)
                : redirect()->route('password.first.show');
        }
        return $next($request);
    }
}
