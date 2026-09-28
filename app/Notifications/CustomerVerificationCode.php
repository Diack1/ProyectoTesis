<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class CustomerVerificationCode extends Notification {
    public function __construct(public string $code) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)->subject('Parkeo: verifica tu correo')
            ->line('Tu código de verificación es: '.$this->code)
            ->line('Escríbelo en Parkeo para confirmar tu cuenta. Vence en 10 minutos y sirve una sola vez.')
            ->line('No compartas este código. Si no creaste la cuenta, ignora este mensaje.');
    }
}
