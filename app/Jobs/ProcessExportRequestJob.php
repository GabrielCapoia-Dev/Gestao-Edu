<?php

namespace App\Jobs;

use App\Models\ExportRequest;
use App\Notifications\SistemaNotification;
use App\Services\Exports\ExportManager;
use App\Services\Exports\ExportSessionService;
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

    public int $tries = 3;

    public int $timeout;

    public bool $failOnTimeout = false;

    /** @var array<int, int> */
    public array $backoff = [30, 120, 300];

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
            (new WithoutOverlapping('export:'.$exportRequest->fingerprint))
                ->expireAfter((int) config('exports.lock_expiration', 1200)),
        ];
    }

    public function handle(ExportManager $manager, ?ExportSessionService $sessions = null): void
    {
        $sessions ??= app(ExportSessionService::class);

        $exportRequest = ExportRequest::query()
            ->with('user')
            ->find($this->exportRequestId);

        if (! $exportRequest || ! $exportRequest->isActive()) {
            return;
        }

        if (! $sessions->isActive($exportRequest)) {
            $sessions->expireForEndedSession($exportRequest);

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

            $exportRequest->refresh();

            if (! $sessions->isActive($exportRequest)) {
                $sessions->discardGeneratedFile($exportRequest, $result);

                return;
            }

            $exportRequest->markFinished($result->toDatabasePayload());
            $this->notifySuccess($exportRequest->refresh());
        } catch (Throwable $exception) {
            $exportRequest->refresh();

            if (! $sessions->isActive($exportRequest)) {
                $sessions->expireForEndedSession($exportRequest);

                return;
            }

            $exportRequest->markQueuedForRetry(
                'Falha temporária. Uma nova tentativa será executada automaticamente.',
                $exception->getMessage(),
            );

            Log::warning('Falha temporária ao processar exportação; o job será tentado novamente.', [
                'export_request_id' => $exportRequest->getKey(),
                'type' => $exportRequest->type,
                'format' => $exportRequest->format,
                'exception' => $exception,
            ]);

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

        $sessions = app(ExportSessionService::class);

        if (! $sessions->isActive($exportRequest)) {
            $sessions->expireForEndedSession($exportRequest);

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
            mensagem: ($exportRequest->label ?: 'Seu arquivo').' já pode ser baixado.',
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
            mensagem: ($exportRequest->label ?: 'O arquivo solicitado').' não pode ser gerado.',
            url: url('/admin'),
            label: 'Abrir painel',
            prioridade: 'alta',
            escopo: 'exports',
            metadata: ['export_request_id' => $exportRequest->getKey()],
        ));
    }
}
