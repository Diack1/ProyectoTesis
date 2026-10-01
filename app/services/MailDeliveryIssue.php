<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class MailDeliveryIssue
{
    public static function message(\Throwable $error): string
    {
        $auth = (bool) preg_match('/535|534|authenticate|credentials/i', $error->getMessage());
        // The raw exception may contain SMTP credentials or personal data.
        Log::warning('access_mail_failed', ['reason' => $auth ? 'authentication_rejected' : 'transport_unavailable']);
        return $auth
            ? 'El servicio de correo necesita actualizar sus credenciales de envío. Comunícate con el responsable del sistema. No se envió el código.'
            : 'No se pudo conectar con el servicio de correo. Inténtalo más tarde. No se envió el código.';
    }
}
