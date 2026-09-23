<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoAlunoDocumento;
use App\Models\Turma;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class AvaliacaoParecerSnapshotService
{
    public function __construct(
        private readonly ParecerResponsaveisResolver $responsaveisResolver,
        private readonly AvaliacaoAlunoDocumentoService $documentoService,
    ) {}

    /**
     * @param  Collection<int, Aluno>  $alunos
     * @return Collection<int, AvaliacaoAlunoDocumento>
     */
    public function capturarParaAlunos(Avaliacao|int $avaliacao, Turma $turma, Collection $alunos): Collection
    {
        $avaliacaoId = $avaliacao instanceof Avaliacao ? (int) $avaliacao->id : (int) $avaliacao;

        return DB::transaction(function () use ($avaliacaoId, $turma, $alunos): Collection {
            $alunos = $alunos->values();

            if ($alunos->isEmpty()) {
                return collect();
            }

            $alunoIds = $alunos->pluck('id')->map(fn ($id): int => (int) $id)->all();
            Aluno::query()
                ->whereIn('id', $alunoIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get(['id']);

            $existentes = AvaliacaoAlunoDocumento::query()
                ->where('avaliacao_id', $avaliacaoId)
                ->whereIn('aluno_id', $alunoIds)
                ->lockForUpdate()
                ->get();

            $precisaCapturar = $existentes->count() !== count($alunoIds)
                || $existentes->contains(
                    fn (AvaliacaoAlunoDocumento $documento): bool => empty($documento->responsaveis_snapshot)
                );

            if (! $precisaCapturar) {
                return $existentes;
            }

            $snapshot = $this->responsaveisResolver->resolver($turma, bloquear: true);
            $capturadoEm = $snapshot['capturado_em'] ?? now()->toIso8601String();

            $documentos = $this->documentoService
                ->obterOuCriarEmMassa($avaliacaoId, $alunos, somentePrincipal: false)
                ->values();

            $documentos = AvaliacaoAlunoDocumento::query()
                ->whereIn('id', $documentos->pluck('id')->all())
                ->lockForUpdate()
                ->get();

            foreach ($documentos as $documento) {
                if (! empty($documento->responsaveis_snapshot)) {
                    continue;
                }

                $documento->forceFill([
                    'responsaveis_snapshot' => $snapshot,
                    'responsaveis_snapshot_em' => $capturadoEm,
                ])->save();
            }

            return $documentos;
        });
    }

    public function capturarParaAluno(Avaliacao|int $avaliacao, Turma $turma, Aluno $aluno): AvaliacaoAlunoDocumento
    {
        /** @var AvaliacaoAlunoDocumento $documento */
        $documento = $this->capturarParaAlunos($avaliacao, $turma, collect([$aluno]))->firstOrFail();

        return $documento;
    }
}
