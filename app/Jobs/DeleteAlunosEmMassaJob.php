<?php

namespace App\Jobs;

use App\Models\Aluno;
use App\Models\ExportRequest;
use App\Models\User;
use App\Notifications\SistemaNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class DeleteAlunosEmMassaJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    public int $timeout = 1800;

    /**
     * @param array<int, int> $alunoIds
     */
    public function __construct(
        public readonly array $alunoIds,
        public readonly ?int $usuarioId,
        public readonly ?string $processRequestId = null,
    ) {
        $this->onQueue((string) config('imports.queue', config('exports.queue', 'exports')));
    }

    public function handle(): void
    {
        $usuario = $this->usuario();
        $processo = $this->processo();

        if ($processo && ! $processo->isActive()) {
            return;
        }

        if (! $usuario) {
            return;
        }

        if (! Gate::forUser($usuario)->allows('deleteBulk', Aluno::class)) {
            $processo?->markFailed('Você não possui permissão para excluir alunos em massa.');
            $this->notificarFalha($usuario, 'Você não possui permissão para excluir alunos em massa.');

            return;
        }

        $processo?->markRunning('Excluindo alunos selecionados.');

        $excluidos = 0;
        $ignorados = 0;
        $falhas = 0;
        $total = count($this->alunoIds);

        Aluno::query()
            ->with('turma.componentes')
            ->whereIn('id', $this->alunoIds)
            ->orderBy('id')
            ->chunkById(100, function ($alunos) use ($usuario, $processo, $total, &$excluidos, &$ignorados, &$falhas): void {
                foreach ($alunos as $aluno) {
                    if (! Gate::forUser($usuario)->allows('delete', $aluno)) {
                        $ignorados++;
                        $processo?->updateProgress($excluidos + $ignorados + $falhas, $total, 'Excluindo alunos selecionados.');

                        continue;
                    }

                    try {
                        $aluno->delete();
                        $excluidos++;
                    } catch (Throwable $exception) {
                        $falhas++;

                        Log::warning('Falha ao excluir aluno em massa.', [
                            'aluno_id' => $aluno->id,
                            'usuario_id' => $usuario->id,
                            'exception' => $exception,
                        ]);
                    }

                    $processo?->updateProgress($excluidos + $ignorados + $falhas, $total, 'Excluindo alunos selecionados.');
                }
            });

        $mensagem = $this->mensagemConclusao($excluidos, $ignorados, $falhas);
        $processo?->refresh()->markProcessFinished($mensagem);

        $this->notificarConclusao($usuario, $excluidos, $ignorados, $falhas);
    }

    private function usuario(): ?User
    {
        if (! $this->usuarioId) {
            return null;
        }

        return User::query()->find($this->usuarioId);
    }

    private function notificarConclusao(User $usuario, int $excluidos, int $ignorados, int $falhas): void
    {
        $mensagem = $this->mensagemConclusao($excluidos, $ignorados, $falhas);

        $usuario->notify(new SistemaNotification(
            titulo: $falhas > 0 ? 'Exclusão de alunos concluída com falhas' : 'Exclusão de alunos concluída',
            mensagem: $mensagem,
            url: route('filament.admin.resources.alunos.index'),
            label: 'Ver alunos',
            prioridade: $falhas > 0 ? 'alta' : 'normal',
            escopo: 'alunos',
            metadata: ['tipo' => 'exclusao_alunos_massa'],
        ));
    }

    private function notificarFalha(User $usuario, string $mensagem): void
    {
        $usuario->notify(new SistemaNotification(
            titulo: 'Falha na exclusão de alunos',
            mensagem: $mensagem,
            url: route('filament.admin.resources.alunos.index'),
            label: 'Ver alunos',
            prioridade: 'alta',
            escopo: 'alunos',
            metadata: ['tipo' => 'exclusao_alunos_massa'],
        ));
    }

    private function processo(): ?ExportRequest
    {
        if (! $this->processRequestId) {
            return null;
        }

        return ExportRequest::query()->find($this->processRequestId);
    }

    private function mensagemConclusao(int $excluidos, int $ignorados, int $falhas): string
    {
        $mensagem = "{$excluidos} aluno(s) excluído(s).";

        if ($ignorados > 0) {
            $mensagem .= " {$ignorados} ignorado(s) por permissão, status ou escopo.";
        }

        if ($falhas > 0) {
            $mensagem .= " {$falhas} não puderam ser excluído(s).";
        }

        return $mensagem;
    }
}
