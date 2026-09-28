<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class SecurityCheckProduction extends Command
{
    protected $signature = 'security:check-production';

    protected $description = 'Comprobar requisitos locales de publicación sin mostrar secretos ni modificar datos';

    public function handle(): int
    {
        $connection = config('database.default');
        $checks = [
            'APP_ENV=production' => app()->environment('production'),
            'Debug desactivado' => ! config('app.debug'),
            'Clave de aplicación configurada' => filled(config('app.key')),
            'APP_URL utiliza HTTPS' => parse_url(config('app.url'), PHP_URL_SCHEME) === 'https',
            'Cookie Secure activada explícitamente' => config('session.secure') === true,
            'Cookie HttpOnly y SameSite restrictiva' => config('session.http_only') && in_array(config('session.same_site'), ['lax', 'strict']),
            'Usuario de base de datos distinto de root' => $connection !== 'mysql' || (filled(config("database.connections.$connection.username")) && config("database.connections.$connection.username") !== 'root'),
            'Verificación de acceso obligatoria para el personal' => (bool) config('security.require_staff_mfa'),
            'Correo real configurado' => ! in_array(config('mail.default'), ['log', 'array', null]),
            'Destino de alertas válido' => (bool) filter_var(config('security.alert_email'), FILTER_VALIDATE_EMAIL),
            'Cola persistente configurada' => in_array(config('queue.default'), ['database', 'redis', 'sqs', 'beanstalkd']),
        ];
        try {
            $checks['Propietario único activo y correo de acceso habilitado'] = app(\App\Services\StaffAccessService::class)->owner() !== null && (bool)config('security.access_mail_ready');
        } catch (\Throwable) {
            $checks['Base de datos de seguridad accesible'] = false;
        }
        foreach ($checks as $label => $ok) {
            $this->line(($ok ? '[OK] ' : '[PENDIENTE] ').$label);
        }
        $this->warn('Esto no comprueba TLS real, privilegios SQL, entrega de correo, trabajador de colas, restauración de copias ni MFA del hosting. Verificarlos por separado.');

        return in_array(false, $checks, true) ? self::FAILURE : self::SUCCESS;
    }
}
