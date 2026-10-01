<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class CheckMailConnection extends Command
{
    protected $signature = 'security:check-mail-connection {--reset-access-cooldown : Limpiar espera de envío del dueño solo tras autenticar SMTP} {--send-test : Enviar un único mensaje de diagnóstico al propietario activo}';
    protected $description = 'Comprueba autenticación SMTP sin enviar correos ni mostrar credenciales';

    public function handle(): int
    {
        if (config('mail.default') !== 'smtp') {
            $this->warn('El transporte configurado no es SMTP. Esta prueba no aplica.');
            return self::FAILURE;
        }
        try {
            $transport = Mail::mailer()->getSymfonyTransport();
            $transport->start();
            $transport->stop();
            if ($this->option('send-test')) {
                $owner = app(\App\Services\StaffAccessService::class)->owner();
                if (!$owner) {
                    $this->error('No hay un único propietario activo. No se envió ningún mensaje.');
                    return self::FAILURE;
                }
                Mail::raw('Prueba de envío de Parke’o. El servicio de correo ha aceptado este mensaje. No es un código de acceso. Solicita tu código desde la página.', function ($message) use ($owner) {
                    $message->to($owner->email)->subject('Parke’o · Comprobación del servicio de correo');
                });
                $this->info('El proveedor aceptó el mensaje de prueba para el propietario. La recepción debe comprobarse en su buzón.');
            }
            if ($this->option('reset-access-cooldown')) {
                $owner = app(\App\Services\StaffAccessService::class)->owner();
                if ($owner) {
                    \Illuminate\Support\Facades\RateLimiter::clear('staff-code-send:'.$owner->id);
                    \Illuminate\Support\Facades\RateLimiter::clear('staff-code-send:'.$owner->id.':hour');
                    $this->info('Espera de envío del dueño restablecida. La verificación del código sigue siendo obligatoria.');
                }
            }
            $this->info($this->option('send-test') ? 'Prueba SMTP completa.' : 'Conexión y autenticación SMTP correctas. No se envió ningún correo.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            // Never print the exception: SMTP diagnostics may contain credentials.
            $message = $e->getMessage();
            $this->error(preg_match('/535|534|authenticate|credentials/i', $message)
                ? 'El proveedor rechazó la autenticación. Actualiza la contraseña de aplicación de la cuenta remitente.'
                : 'No se pudo conectar con el proveedor. Revisa host, puerto, TLS y conectividad.');
            return self::FAILURE;
        }
    }
}
