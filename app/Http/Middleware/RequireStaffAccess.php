<?php

namespace App\Http\Middleware;

use App\Services\StaffAccessService;
use Closure;
use Illuminate\Http\Request;

class RequireStaffAccess
{
    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if ($user?->tieneRol('admin', 'operador', 'super_admin')
            && config('security.require_staff_mfa') && !$request->hasSession()) {
            return response()->json(['message' => 'Este acceso requiere una sesión. Usa la API IoT para sensores.'], 403);
        }
        $mfa = app(StaffAccessService::class);
        if (! $user || ! $mfa->required($user)) {
            return $next($request);
        }
        // A legacy bearer token is not proof of a second factor. Devices use the sensor-scoped IoT endpoint.
        if (! $request->hasSession()) {
            return response()->json(['message' => 'Este acceso del personal requiere una sesión autorizada. Usa la API IoT para sensores.'], 403);
        }

        if ($request->routeIs('staff-access.*', 'logout')) { return $next($request); }
            $proof = $request->session()->get('staff_access_verified');
            if (!is_string($proof) || !hash_equals(app(\App\Services\StaffAccessService::class)->fingerprint($user), $proof)) {
                return $request->expectsJson()
                    ? response()->json(['message'=>'Tu sesión necesita autorización.', 'redirect'=>route('staff-access.show')], 403)
                    : redirect()->route('staff-access.show');
            }
            return $next($request);

    }
}
