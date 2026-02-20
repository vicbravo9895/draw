<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MagicLinkNotification extends Notification
{
    use Queueable;

    public function __construct(
        protected string $signedUrl
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Acceso al Portal de Empresa')
            ->greeting('Hola!')
            ->line('Has solicitado acceso al portal de consulta de inspecciones.')
            ->action('Acceder al Portal', $this->signedUrl)
            ->line('Este enlace expira en 15 minutos.')
            ->line('Si no solicitaste este acceso, ignora este correo.');
    }
}
