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
        $this->onConnection('database');
        $this->onQueue((string) config('imports.queue', 'imports'));
    }

    public function handle(AlunoImportacaoSpreadsheetService $service): void
    {
        $usuario = $this->usuario();
        $processo = $this->processo();

        try {
            $processo?->markRunning('Importando alunos da planilha.');

            $resultado = $service->importar($this->caminhoArquivo, $usuario, $this->disk);
            $mensagem = "{$resultado['total_importado']} aluno(s) importado(s). Series criadas: {$resultado['series_criadas']}. Turmas criadas: {$resultado['turmas_criadas']}. Pendentes: {$resultado['total_pendente']}. CGMs duplicados ignorados: {$resultado['duplicados_ignorados']}.";

            $processo?->refresh()->markProcessFinished($mensagem);

            $usuario?->notify(new SistemaNotification(
                titulo: 'Importacao de alunos concluida',
                mensagem: $mensagem,
                url: route('filament.admin.resources.alunos.index'),
                label: 'Ver alunos',
                escopo: 'alunos',
                metadata: ['tipo' => 'importacao_alunos'],
            ));
        } catch (InvalidArgumentException|ValidationException $exception) {
            $processo?->refresh()->markFailed($exception->getMessage());

            $usuario?->notify(new SistemaNotification(
                titulo: 'Falha na importacao de alunos',
                mensagem: $exception->getMessage(),
                url: route('filament.admin.resources.alunos.index'),
                label: 'Ver alunos',
                prioridade: 'alta',
                escopo: 'alunos',
                metadata: ['tipo' => 'importacao_alunos'],
            ));
        } catch (Throwable $exception) {
            $processo?->refresh()->markFailed('A importacao nao pode ser concluida. Tente novamente ou acione o suporte.');

            Log::error('Falha inesperada na importacao de alunos.', [
                'caminho_arquivo' => $this->caminhoArquivo,
                'usuario_id' => $this->usuarioId,
                'exception' => $exception,
            ]);

            $usuario?->notify(new SistemaNotification(
                titulo: 'Falha na importacao de alunos',
                mensagem: 'A importacao nao pode ser concluida. Tente novamente ou acione o suporte.',
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
