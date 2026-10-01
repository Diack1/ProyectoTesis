<?php

namespace App\Providers;

use App\Models\Sensor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if (app()->environment('production')) {
            URL::forceScheme('https');
        }
        \Illuminate\Pagination\Paginator::defaultView('partials.pagination');
        \Illuminate\Pagination\Paginator::defaultSimpleView('partials.pagination');
        foreach ([\App\Models\User::class, \App\Models\ConfiguracionPago::class, \App\Models\Pago::class, \App\Models\Reembolso::class, \App\Models\Sensor::class] as $model) {
            $model::observe(\App\Observers\SecurityObserver::class);
        }
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Lockout::class, function ($event) {
            $key = 'security-lockout:'.hash('sha256', strtolower((string) $event->request->input('email')).'|'.$event->request->ip());
            if (\Illuminate\Support\Facades\Cache::add($key, true, 3600)) {
                \App\Services\SecurityEvents::record('Authentication', 0, 'lockout', ['login_attempts'], true);
            }
        });
        RateLimiter::for('mfa', function (Request $request) {
            $blocked = function (Request $request, array $headers) {
                if (\Illuminate\Support\Facades\Cache::add('mfa-alert:'.$request->user()->id, true, 3600)) {
                    \App\Services\SecurityEvents::record('User', $request->user()->id, 'lockout', ['mfa_attempts'], true);
                }
                $headers['Cache-Control'] = 'private, no-store';
                $seconds = (int) ($headers['Retry-After'] ?? 60);
                return $request->expectsJson()
                    ? response()->json(['message' => 'Demasiados intentos. Espera antes de volver a verificar.', 'retry_after' => $seconds], 429, $headers)
                    : response()->view('auth.access-wait', compact('seconds'), 429, $headers);
            };
            return [
                Limit::perMinute(5)->by('mfa-user:'.$request->user()->id)->response($blocked),
                Limit::perHour(30)->by('mfa-hour:'.$request->user()->id)->response($blocked),
                Limit::perMinute(30)->by('mfa-ip:'.$request->ip())->response($blocked),
            ];
        });
        RateLimiter::for('customer-verify', fn (Request $request) => Limit::perMinute(5)->by('customer-verify:'.$request->user()->id));
        RateLimiter::for('customer-code-send', fn (Request $request) => Limit::perMinute(5)->by('customer-code-send:'.$request->user()->id));
        RateLimiter::for('account-registration', fn (Request $request) => Limit::perHour(10)->by($request->ip()));
        RateLimiter::for('booking-actions', fn (Request $request) => Limit::perMinute(10)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('payment-upload', fn (Request $request) => Limit::perMinute(5)->by((string) ($request->user()?->id ?? $request->ip())));
        RateLimiter::for('sensor-readings', function (Request $request) {
            $sensor = $request->route('sensor');
            $code = $sensor instanceof Sensor ? $sensor->codigo_sensor : (string) $sensor;

            return Limit::perMinute(120)->by($request->ip().':'.$code);
        });
    }
}
