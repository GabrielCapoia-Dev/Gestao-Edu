<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class SistemaNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries;

    public int $timeout;

    public function __construct(
        public string $titulo,
        public string $mensagem,
        public ?string $url = null,
        public ?string $label = null,
        public string $prioridade = 'normal',
        public ?string $escopo = null,
        public array $metadata = [],
    ) {
        $this->tries = (int) config('notifications.tries', 3);
        $this->timeout = (int) config('notifications.timeout', 60);

        $this->afterCommit();
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function viaQueues(): array
    {
        return [
            'database' => (string) config('notifications.queue', 'notifications'),
        ];
    }

    public function backoff(): array
    {
        return config('notifications.backoff', [10, 60, 300]);
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

    public function failed(Throwable $exception): void
    {
        Log::error('Falha ao enviar notificação do sistema.', [
            'titulo' => $this->titulo,
            'url' => $this->url,
            'prioridade' => $this->prioridade,
            'escopo' => $this->escopo,
            'metadata' => $this->metadata,
            'exception' => $exception,
        ]);
    }
}
