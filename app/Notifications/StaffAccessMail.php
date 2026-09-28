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
                ->line('Tu código de acceso es: '.$this->code)->line('Vence en 5 minutos y sirve una sola vez. No lo compartas.');
        }
        return (new MailMessage)->subject('Parke’o: solicitud de acceso del personal')
            ->line(($this->staffName ?? 'Un miembro del personal').' solicita entrar al panel.')
            ->line('Código de autorización: '.$this->code)
            ->line('Entrégalo únicamente a esa persona si reconoces su solicitud y deseas autorizarla.')
            ->line('Vence en 5 minutos, sirve una sola vez y únicamente en la sesión que lo solicitó. Si no reconoces la solicitud, no compartas el código.');
    }
}
