<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireStaffActivation
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->tieneRol('admin', 'operador') && !$user->hasVerifiedEmail()
            && !$request->routeIs('verification.*', 'logout')) {
            return $request->expectsJson()
                ? response()->json(['message' => 'Activa tu cuenta verificando tu correo.'], 403)
                : redirect()->route('verification.notice');
        }
        return $next($request);
    }
}
