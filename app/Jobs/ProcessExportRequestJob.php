<?php

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Notifications\SistemaNotification;
use App\Services\Exports\ExportManager;
use App\Support\UserActorSnapshot;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessExportRequestJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout;

    public bool $failOnTimeout = true;

    public function __construct(
        public readonly string $exportRequestId,
    ) {
        $this->timeout = (int) config('exports.job_timeout', 900);
        $this->onQueue((string) config('exports.queue', 'exports'));
    }

    public function middleware(): array
    {
        $exportRequest = ExportRequest::query()->find($this->exportRequestId);

        if (! $exportRequest) {
            return [];
        }

        return [
            (new WithoutOverlapping('export:' . $exportRequest->fingerprint))
                ->expireAfter((int) config('exports.lock_expiration', 1200)),
        ];
    }

    public function handle(ExportManager $manager): void
    {
        $exportRequest = ExportRequest::query()
            ->with('user')
            ->find($this->exportRequestId);

        if (! $exportRequest || ! $exportRequest->isActive()) {
            return;
        }

        if ($exportRequest->cancel_requested_at) {
            $exportRequest->forceFill([
                'status' => ExportRequest::STATUS_CANCELLED,
                'status_message' => 'Exportação cancelada antes do processamento.',
                'finished_at' => now(),
            ])->save();

            return;
        }

        try {
            $exportRequest->markRunning('Processando exportação.');

            $result = $manager->handlerFor($exportRequest->type)->handle($exportRequest->refresh());

            $exportRequest->refresh()->markFinished($result->toDatabasePayload());
            $this->notifySuccess($exportRequest->refresh());
        } catch (Throwable $exception) {
            $exportRequest->refresh()->markFailed($exception->getMessage());

            Log::error('Falha ao processar exportação.', [
                'export_request_id' => $exportRequest->getKey(),
                'type' => $exportRequest->type,
                'format' => $exportRequest->format,
                'exception' => $exception,
            ]);

            $this->notifyFailure($exportRequest->refresh());

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $exportRequest = ExportRequest::query()
            ->with('user')
            ->find($this->exportRequestId);

        if (! $exportRequest || ! $exportRequest->isActive()) {
            return;
        }

        $message = $exception?->getMessage();

        if (! filled($message)) {
            $message = 'A exportação foi interrompida antes de concluir o processamento.';
        }

        $exportRequest->markFailed($message);
        $this->notifyFailure($exportRequest->refresh());

        Log::error('Exportação finalizada com falha pelo worker.', [
            'export_request_id' => $exportRequest->getKey(),
            'type' => $exportRequest->type,
            'format' => $exportRequest->format,
            'message' => $message,
            'exception' => $exception,
        ]);
    }

    private function notifySuccess(ExportRequest $exportRequest): void
    {
        $user = $exportRequest->user;

        if (! UserActorSnapshot::canReceiveNotification($user)) {
            return;
        }

        $user->notify(new SistemaNotification(
            titulo: 'Exportação pronta',
            mensagem: ($exportRequest->label ?: 'Seu arquivo') . ' já pode ser baixado.',
            url: route('exports.download', $exportRequest),
            label: 'Baixar arquivo',
            escopo: 'exports',
            metadata: ['export_request_id' => $exportRequest->getKey()],
        ));
    }

    private function notifyFailure(ExportRequest $exportRequest): void
    {
        $user = $exportRequest->user;

        if (! UserActorSnapshot::canReceiveNotification($user)) {
            return;
        }

        $user->notify(new SistemaNotification(
            titulo: 'Falha na exportação',
            mensagem: ($exportRequest->label ?: 'O arquivo solicitado') . ' não pode ser gerado.',
            url: route('filament.admin.pages.minhas-exportacoes'),
            label: 'Ver exportacoes',
            prioridade: 'alta',
            escopo: 'exports',
            metadata: ['export_request_id' => $exportRequest->getKey()],
        ));
    }
}
