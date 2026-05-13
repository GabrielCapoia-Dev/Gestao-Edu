<?php

namespace App\Jobs;

use App\Models\NotificacaoEnvio;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class SendManualNotificationBatchJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries;

    public int $timeout;

    public function __construct(
        public readonly string $notificacaoEnvioId,
    ) {
        $this->tries = (int) config('notifications.tries', 3);
        $this->timeout = max(120, (int) config('notifications.timeout', 60));
        $this->onQueue((string) config('notifications.queue', 'notifications'));
    }

    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('manual-notification:'.$this->notificacaoEnvioId))
                ->expireAfter($this->timeout + 300),
        ];
    }

    public function backoff(): array
    {
        return config('notifications.backoff', [10, 60, 300]);
    }

    public function handle(): void
    {
        $envio = NotificacaoEnvio::query()
            ->with('autor:id,name')
            ->find($this->notificacaoEnvioId);

        if (! $envio) {
            return;
        }

        if ($envio->status === NotificacaoEnvio::STATUS_PROCESSED && $envio->processed_at) {
            return;
        }

        $envio->forceFill([
            'status' => NotificacaoEnvio::STATUS_PROCESSING,
            'processing_started_at' => now(),
            'failed_at' => null,
            'error_message' => null,
        ])->save();

        $destinatariosIds = collect($envio->destinatarios_ids ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        if ($destinatariosIds->isEmpty()) {
            $this->markProcessed($envio, 0, 0);

            return;
        }

        $enviadas = 0;
        $ignoradas = 0;

        User::query()
            ->whereIn('id', $destinatariosIds)
            ->whereNotNull('email')
            ->orderBy('id')
            ->chunkById(100, function (Collection $destinatarios) use ($envio, &$enviadas, &$ignoradas): void {
                $jaEnviadas = $this->existingNotificationUserIds($destinatarios->pluck('id'), (string) $envio->id);

                foreach ($destinatarios as $destinatario) {
                    if (isset($jaEnviadas[(int) $destinatario->id])) {
                        $ignoradas++;

                        continue;
                    }

                    Notification::sendNow($destinatario, new SistemaNotification(
                        titulo: $envio->titulo,
                        mensagem: $envio->mensagem,
                        url: filled($envio->url) ? $envio->url : null,
                        label: filled($envio->label) ? $envio->label : 'Ver detalhes',
                        prioridade: $envio->prioridade ?: 'normal',
                        escopo: $envio->destino_label,
                        metadata: [
                            'envio_id' => (string) $envio->id,
                            'enviado_por_id' => $envio->user_id,
                            'enviado_por_nome' => $envio->autor?->name,
                            'manual' => true,
                        ],
                    ));

                    $enviadas++;
                }
            });

        $this->markProcessed($envio, $enviadas, $ignoradas);
    }

    public function failed(Throwable $exception): void
    {
        NotificacaoEnvio::query()
            ->whereKey($this->notificacaoEnvioId)
            ->update([
                'status' => NotificacaoEnvio::STATUS_FAILED,
                'failed_at' => now(),
                'error_message' => mb_substr($exception->getMessage(), 0, 2000),
                'updated_at' => now(),
            ]);

        Log::error('Falha ao processar lote manual de notificacoes.', [
            'notificacao_envio_id' => $this->notificacaoEnvioId,
            'exception' => $exception,
        ]);
    }

    private function existingNotificationUserIds(Collection $userIds, string $envioId): array
    {
        if ($userIds->isEmpty()) {
            return [];
        }

        return DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $userIds->map(fn ($id): int => (int) $id)->all())
            ->where('data', 'like', '%"envio_id":"'.$envioId.'"%')
            ->pluck('notifiable_id')
            ->mapWithKeys(fn ($id): array => [(int) $id => true])
            ->all();
    }

    private function markProcessed(NotificacaoEnvio $envio, int $enviadas, int $ignoradas): void
    {
        $envio->forceFill([
            'status' => NotificacaoEnvio::STATUS_PROCESSED,
            'processed_at' => now(),
            'error_message' => null,
        ])->save();

        Log::info('Lote manual de notificacoes processado.', [
            'notificacao_envio_id' => $envio->id,
            'enviadas' => $enviadas,
            'ignoradas_por_idempotencia' => $ignoradas,
        ]);
    }
}
