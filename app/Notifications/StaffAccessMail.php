<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class StaffAccessMail extends Notification
{
    public function __construct(public string $requestId, public string $code, public string $staffName, public bool $isOwner = false) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage
    {
        if ($this->isOwner) {
            return (new MailMessage)->subject('Parke’o: código de acceso del propietario')
                ->view(['emails.access-code', 'emails.access-code-text'], [
                    'title' => 'Confirma tu acceso',
                    'intro' => 'Introduce este código en Parke’o para acceder a tu cuenta de propietario.',
                    'code' => $this->code,
                    'minutes' => 5,
                    'notice' => 'No compartas este código. Si no intentaste iniciar sesión, no lo utilices y revisa la seguridad de tu cuenta.',
                ]);
        }
        return (new MailMessage)->subject('Parke’o: solicitud de acceso del personal')
            ->view(['emails.access-code', 'emails.access-code-text'], [
                'title' => 'Solicitud de acceso del personal',
                'intro' => $this->staffName.' solicita entrar al panel. Entrégale este código únicamente si reconoces la solicitud y deseas autorizarla.',
                'code' => $this->code,
                'minutes' => 5,
                'notice' => 'El código solo funciona en la sesión que lo solicitó. Si no reconoces la solicitud, no lo compartas.',
            ]);
    }
}
