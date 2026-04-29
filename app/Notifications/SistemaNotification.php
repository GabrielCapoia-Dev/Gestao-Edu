<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class SistemaNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $titulo,
        public string $mensagem,
        public ?string $url = null,
        public ?string $label = null,
        public string $prioridade = 'normal',
        public ?string $escopo = null,
        public array $metadata = [],
    ) {}

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toDatabase($notifiable): array
    {
        return array_filter([
            'titulo' => $this->titulo,
            'mensagem' => $this->mensagem,
            'url' => $this->url,
            'label' => $this->label,
            'prioridade' => $this->prioridade,
            'escopo' => $this->escopo,
            ...$this->metadata,
        ], fn ($value): bool => filled($value));
    }
}
