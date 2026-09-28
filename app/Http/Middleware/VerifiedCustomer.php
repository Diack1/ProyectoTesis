<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
class VerifiedCustomer {
    public function handle(Request $request, Closure $next) {
        if ($request->user()?->esUsuario() && !$request->user()->hasVerifiedEmail()) {
            return $request->expectsJson()
                ? response()->json(['message'=>'Verifica tu correo para reservar.','redirect'=>route('verification.notice')],403)
                : redirect()->route('verification.notice');
        }
        return $next($request);
    }
}
