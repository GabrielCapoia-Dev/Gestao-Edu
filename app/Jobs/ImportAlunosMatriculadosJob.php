<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\ExportRequest;
use App\Notifications\SistemaNotification;
use App\Services\Alunos\AlunoImportacaoSpreadsheetService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use Throwable;

class ImportAlunosMatriculadosJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    public function __construct(
        public readonly string $caminhoArquivo,
        public readonly ?int $usuarioId,
        public readonly string $disk = 'local',
        public readonly ?string $processRequestId = null,
    ) {
        $this->onQueue((string) config('imports.queue', config('exports.queue', 'exports')));
    }

    public function handle(AlunoImportacaoSpreadsheetService $service): void
    {
        $usuario = $this->usuario();
        $processo = $this->processo();

        if ($processo && ! $processo->isActive()) {
            return;
        }

        try {
            $processo?->markRunning('Sincronizando alunos da planilha.');

            $resultado = $service->importar($this->caminhoArquivo, $usuario, $this->disk, $this->processRequestId);
            $mensagem = implode(' ', [
                "Novos: {$resultado['total_importado']}.",
                "Atualizados: {$resultado['total_atualizado']}.",
                "Remanejados: {$resultado['total_remanejado']}.",
                "Sem alterações: {$resultado['total_sem_alteracao']}.",
                "Pendentes aguardando transferência: {$resultado['total_pendente']}.",
                "Séries criadas: {$resultado['series_criadas']}.",
                "Turmas criadas: {$resultado['turmas_criadas']}.",
                "Linhas duplicadas ignoradas: {$resultado['duplicados_ignorados']}.",
            ]);

            $processo?->refresh()->markProcessFinished($mensagem);

            $usuario?->notify(new SistemaNotification(
                titulo: 'Sincronização de alunos concluída',
                mensagem: $mensagem,
                url: route('filament.admin.resources.alunos.index'),
                label: 'Ver alunos',
                escopo: 'alunos',
                metadata: ['tipo' => 'importacao_alunos'],
            ));
        } catch (InvalidArgumentException|ValidationException $exception) {
            $processo?->refresh()->markFailed($exception->getMessage());

            $usuario?->notify(new SistemaNotification(
                titulo: 'Falha na sincronização de alunos',
                mensagem: $exception->getMessage(),
                url: route('filament.admin.resources.alunos.index'),
                label: 'Ver alunos',
                prioridade: 'alta',
                escopo: 'alunos',
                metadata: ['tipo' => 'importacao_alunos'],
            ));
        } catch (Throwable $exception) {
            $processo?->refresh()->markFailed('A sincronização não pode ser concluída. Tente novamente ou acione o suporte.');

            Log::error('Falha inesperada na sincronização de alunos.', [
                'caminho_arquivo' => $this->caminhoArquivo,
                'usuario_id' => $this->usuarioId,
                'exception' => $exception,
            ]);

            $usuario?->notify(new SistemaNotification(
                titulo: 'Falha na sincronização de alunos',
                mensagem: 'A sincronização não pode ser concluída. Tente novamente ou acione o suporte.',
                url: route('filament.admin.resources.alunos.index'),
                label: 'Ver alunos',
                prioridade: 'alta',
                escopo: 'alunos',
                metadata: ['tipo' => 'importacao_alunos'],
            ));

            throw $exception;
        }
    }

    private function usuario(): ?User
    {
        if (! $this->usuarioId) {
            return null;
        }

        return User::query()->find($this->usuarioId);
    }

    private function processo(): ?ExportRequest
    {
        if (! $this->processRequestId) {
            return null;
        }

        return ExportRequest::query()->find($this->processRequestId);
    }
}
