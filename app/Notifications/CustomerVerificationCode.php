<?php
namespace App\Notifications;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
class CustomerVerificationCode extends Notification {
    public function __construct(public string $code) {}
    public function via(object $notifiable): array { return ['mail']; }
    public function toMail(object $notifiable): MailMessage {
        return (new MailMessage)->subject('Parke’o: verifica tu correo')
            ->view(['emails.access-code', 'emails.access-code-text'], [
                'title' => 'Confirma tu correo',
                'intro' => 'Introduce este código en Parke’o para verificar tu correo y activar tu cuenta.',
                'code' => $this->code,
                'minutes' => 10,
                'notice' => 'No compartas este código. Si no creaste la cuenta, puedes ignorar este mensaje.',
            ]);
    }
}
