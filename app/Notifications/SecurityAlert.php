<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SecurityAlert extends Notification
{
    public function __construct(public int $eventId) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Parke’o: cambio de seguridad para revisar')
            ->line('Se registró el evento de seguridad #'.$this->eventId.'.')
            ->line('Revisa Configuración → Seguridad. Si no reconoces el cambio, revisa el acceso de las cuentas involucradas.')
            ->action('Abrir actividad de seguridad', route('admin.seguridad'));
    }
}
