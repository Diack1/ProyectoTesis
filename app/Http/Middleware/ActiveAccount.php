<?php
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class ActiveAccount
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && ! $request->user()->activo) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            return $request->expectsJson()
                ? response()->json(['message' => 'La cuenta está desactivada.'], 401)
                : redirect()->route('login')->withErrors(['email' => 'La cuenta está desactivada.']);
        }
        $authenticated = $request->user() !== null;
        $response = $next($request);
        if ($authenticated) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        return $response;
    }
}
