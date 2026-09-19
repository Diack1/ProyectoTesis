<?php

namespace App\Providers;

use App\Models\Sensor;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

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
        RateLimiter::for('sensor-readings', function (Request $request) {
            $sensor = $request->route('sensor');
            $code = $sensor instanceof Sensor ? $sensor->codigo_sensor : (string) $sensor;

            return Limit::perMinute(120)->by($request->ip().':'.$code);
        });
    }
}
