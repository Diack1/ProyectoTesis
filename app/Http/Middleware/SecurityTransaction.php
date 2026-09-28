<?php
namespace App\Http\Middleware;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
class SecurityTransaction {
    public function handle(Request $request, Closure $next) {
        if (!$request->isMethodSafe() && $request->routeIs('profile.*', 'superadmin.*', 'admin.pagos.*', 'admin.reembolsos.*', 'admin.sensores.*', 'password.update', 'password.store')) {
            return DB::transaction(fn () => $next($request));
        }
        return $next($request);
    }
}
