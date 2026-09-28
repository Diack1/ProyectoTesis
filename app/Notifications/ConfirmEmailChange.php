<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class ConfirmEmailChange extends Notification {
    public function __construct(public string $code) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)->subject('Confirma tu nuevo correo en Parke’o')
            ->line('Introduce este código en tu perfil para confirmar el cambio de correo:')
            ->line($this->code)->line('Vence en 10 minutos. Si no solicitaste el cambio, ignora este mensaje.');
    }
}
