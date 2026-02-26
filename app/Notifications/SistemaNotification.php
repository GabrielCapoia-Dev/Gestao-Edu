<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\DatabaseMessage;

class SistemaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $titulo,
        public string $mensagem,
        public ?string $url = null
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return [
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'url' => $this->url,
        ];
    }
}