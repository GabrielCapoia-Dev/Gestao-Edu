<?php

namespace App\Services\Alunos;

use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoMovimentacaoService;
use Illuminate\Support\Str;
use RuntimeException;

class AlunoImportacaoSincronizacaoService
{
    public function __construct(
        private readonly AlunoMovimentacaoService $movimentacaoService,
    ) {}

    /**
     * Sincroniza vínculos principais já existentes e cria os ainda inexistentes.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array{total_importado:int,total_atualizado:int,total_remanejado:int,total_sem_alteracao:int,total_pendente:int}
     */
    public function sincronizarPrincipais(array $linhas, ?User $usuario = null): array
    {
        $resultado = $this->resultadoVazio();

        if ($linhas === []) {
            return $resultado;
        }

        $cgms = collect($linhas)
            ->pluck('cgm')
            ->map(fn ($cgm): string => Aluno::normalizarCgm((string) $cgm))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $existentes = Aluno::query()
            ->with('turma.serie')
            ->whereIn('cgm', $cgms)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->whereIn('status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->get();

        $porUnidade = $existentes
            ->filter(fn (Aluno $aluno): bool => $aluno->turma !== null)
            ->keyBy(fn (Aluno $aluno): string => $this->chaveUnidade(
                (int) $aluno->turma->id_escola,
                (string) $aluno->cgm,
            ));

        $novasMatriculas = [];

        foreach ($linhas as $linha) {
            /** @var Turma|null $turmaDestino */
            $turmaDestino = $linha['turma'] ?? null;

            if (! $turmaDestino instanceof Turma) {
                throw new RuntimeException('Turma de destino não encontrada durante a sincronização da importação.');
            }

            $cgm = Aluno::normalizarCgm((string) ($linha['cgm'] ?? ''));
            $chave = $this->chaveUnidade((int) $turmaDestino->id_escola, $cgm);

            /** @var Aluno|null $existente */
            $existente = $porUnidade->get($chave);

            if ($existente) {
                if (! $this->mesmaTurmaLogica($existente->turma, $turmaDestino)) {
                    $novo = $this->remanejarPrincipalPorImportacao(
                        $existente,
                        $turmaDestino,
                        $linha,
                        $usuario,
                    );

                    $porUnidade->put($chave, $novo);
                    $resultado['total_remanejado']++;

                    continue;
                }

                if ($this->atualizarDadosImportados($existente, $linha)) {
                    $resultado['total_atualizado']++;
                } else {
                    $resultado['total_sem_alteracao']++;
                }

                continue;
            }

            $novasMatriculas[] = [
                'nome' => $linha['nome'],
                'cgm' => $cgm,
                'data_nascimento' => $linha['data_nascimento'],
                'sexo' => $linha['sexo'],
                'data_matricula' => $linha['data_matricula'],
                'id_turma' => (int) $turmaDestino->id,
                'status_motivo' => 'Matrícula criada por sincronização da planilha de alunos.',
            ];
        }

        if ($novasMatriculas !== []) {
            $lote = $this->movimentacaoService->criarMatriculaEmLote(
                $novasMatriculas,
                $usuario,
            );

            $resultado['total_importado'] += (int) ($lote['total_importado'] ?? 0);
            $resultado['total_pendente'] += (int) ($lote['total_pendente'] ?? 0);
        }

        return $resultado;
    }

    /**
     * Sincroniza vínculos de Contra Turno já existentes e cria os inexistentes.
     * O status do Contra Turno sempre reflete o Principal da mesma escola:
     * Matriculado -> Matriculado; Pendente -> Pendente.
     *
     * @param  array<int, array<string, mixed>>  $linhas
     * @return array{total_importado:int,total_atualizado:int,total_remanejado:int,total_sem_alteracao:int,total_pendente:int}
     */
    public function sincronizarContraTurnos(array $linhas, ?User $usuario = null): array
    {
        $resultado = $this->resultadoVazio();

        if ($linhas === []) {
            return $resultado;
        }

        $cgms = collect($linhas)
            ->pluck('cgm')
            ->map(fn ($cgm): string => Aluno::normalizarCgm((string) $cgm))
            ->filter()
            ->unique()
            ->values()
            ->all();

        $principais = Aluno::query()
            ->with('turma.serie')
            ->whereIn('cgm', $cgms)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->whereIn('status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->get();

        $principaisPorUnidade = $principais
            ->filter(fn (Aluno $aluno): bool => $aluno->turma !== null)
            ->keyBy(fn (Aluno $aluno): string => $this->chaveUnidade(
                (int) $aluno->turma->id_escola,
                (string) $aluno->cgm,
            ));

        /*
         * Contra Turno Pendente também é vínculo existente. A chave inclui escola,
         * pois durante uma transferência pode coexistir um Contra Turno Matriculado
         * na origem e outro Pendente no destino.
         */
        $contraTurnosPorUnidade = Aluno::query()
            ->with('turma.serie')
            ->whereIn('cgm', $cgms)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->whereIn('status', [
                Aluno::STATUS_MATRICULADO,
                Aluno::STATUS_PENDENTE,
            ])
            ->get()
            ->filter(fn (Aluno $aluno): bool => $aluno->turma !== null)
            ->keyBy(fn (Aluno $aluno): string => $this->chaveUnidade(
                (int) $aluno->turma->id_escola,
                (string) $aluno->cgm,
            ));

        foreach ($linhas as $linha) {
            /** @var Turma|null $turmaDestino */
            $turmaDestino = $linha['turma'] ?? null;

            if (! $turmaDestino instanceof Turma) {
                throw new RuntimeException('Turma de destino não encontrada durante a sincronização do contra turno.');
            }

            $numeroLinha = (int) ($linha['numero_linha'] ?? 0);
            $cgm = Aluno::normalizarCgm((string) ($linha['cgm'] ?? ''));
            $chave = $this->chaveUnidade((int) $turmaDestino->id_escola, $cgm);

            /** @var Aluno|null $principal */
            $principal = $principaisPorUnidade->get($chave);

            if (! $principal) {
                throw new RuntimeException(
                    "Linha {$numeroLinha}: o aluno informado como Contra Turno precisa possuir uma matrícula Principal, matriculada ou pendente, na mesma escola."
                );
            }

            /** @var Aluno|null $contraTurno */
            $contraTurno = $contraTurnosPorUnidade->get($chave);

            if ($contraTurno) {
                if (! $this->mesmaTurmaLogica($contraTurno->turma, $turmaDestino)) {
                    $novo = $this->remanejarContraTurnoPorImportacao(
                        $contraTurno,
                        $principal,
                        $turmaDestino,
                        $linha,
                        $usuario,
                    );

                    $contraTurnosPorUnidade->put($chave, $novo);
                    $resultado['total_remanejado']++;

                    if ($novo->estaPendente()) {
                        $resultado['total_pendente']++;
                    }

                    continue;
                }

                /* Garante que um vínculo legado divergente herde o estado do Principal. */
                if ((string) $contraTurno->status !== (string) $principal->status) {
                    $contraTurno->forceFill([
                        'status' => $principal->status,
                        'status_alterado_em' => now(),
                        'status_alterado_por' => $usuario?->id,
                        'status_motivo' => 'Status corrigido pela sincronização para refletir o vínculo Principal.',
                        'aluno_origem_id' => (int) $principal->id,
                        'turma_origem_id' => (int) $principal->id_turma,
                    ])->save();
                    $resultado['total_atualizado']++;
                }

                if ($this->atualizarDadosImportados($contraTurno, $linha)) {
                    $resultado['total_atualizado']++;
                } elseif ((string) $contraTurno->status === (string) $principal->status) {
                    $resultado['total_sem_alteracao']++;
                }

                continue;
            }

            $novo = $this->criarContraTurnoPorImportacao(
                $principal,
                $turmaDestino,
                $linha,
                $usuario,
            );

            $contraTurnosPorUnidade->put($chave, $novo->fresh(['turma.serie']));
            $resultado['total_importado']++;

            if ($novo->estaPendente()) {
                $resultado['total_pendente']++;
            }
        }

        return $resultado;
    }

    /**
     * Cria o vínculo secundário diretamente no fluxo da planilha. Principal
     * Pendente não bloqueia o cadastro; o Contra Turno nasce Pendente também.
     *
     * @param  array<string, mixed>  $linha
     */
    private function criarContraTurnoPorImportacao(
        Aluno $principal,
        Turma $turmaDestino,
        array $linha,
        ?User $usuario,
    ): Aluno {
        $principal->refresh()->loadMissing('turma');

        if (! $principal->isPrincipal()) {
            throw new RuntimeException('O vínculo de referência do Contra Turno precisa ser Principal.');
        }

        if (! $principal->estaMatriculado() && ! $principal->estaPendente()) {
            throw new RuntimeException('O vínculo Principal precisa estar Matriculado ou Pendente para ser sincronizado pela planilha.');
        }

        if (! $principal->turma || (int) $principal->turma->id_escola !== (int) $turmaDestino->id_escola) {
            throw new RuntimeException('A turma de Contra Turno precisa pertencer à mesma escola da matrícula Principal.');
        }

        /*
         * Se a linha de Contra Turno trouxer dados pessoais atualizados, ela também
         * pode corrigir o Principal. Em seguida o novo vínculo nasce como reflexo.
         */
        $this->atualizarDadosImportados($principal, $linha);
        $principal->refresh();

        $principal->forceFill([
            'permite_contra_turno' => true,
        ])->save();

        return Aluno::query()->create([
            'nome' => $principal->nome,
            'cgm' => $principal->cgm,
            'data_nascimento' => $principal->data_nascimento,
            'sexo' => $principal->sexo,
            'data_matricula' => $principal->data_matricula,
            'id_turma' => (int) $turmaDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'permite_contra_turno' => false,
            'status' => $principal->status,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => $principal->estaPendente()
                ? 'Vínculo de contra turno criado como Pendente, refletindo a matrícula Principal.'
                : 'Vínculo de contra turno criado por sincronização da planilha de alunos.',
            'aluno_origem_id' => (int) $principal->id,
            'turma_origem_id' => (int) $principal->id_turma,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_CONTRA_TURNO,
        ]);
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function remanejarPrincipalPorImportacao(
        Aluno $aluno,
        Turma $turmaDestino,
        array $linha,
        ?User $usuario,
    ): Aluno {
        $aluno->refresh()->loadMissing('turma');

        if (! $aluno->isPrincipal()) {
            throw new RuntimeException('Somente o vínculo Principal pode ser remanejado como Principal pela importação.');
        }

        if (! $aluno->estaMatriculado() && ! $aluno->estaPendente()) {
            throw new RuntimeException('Somente matrículas Principais ativas ou pendentes podem ser sincronizadas pela importação.');
        }

        if (! $aluno->turma || (int) $aluno->turma->id_escola !== (int) $turmaDestino->id_escola) {
            throw new RuntimeException('A sincronização de turma do vínculo Principal só pode ocorrer dentro da mesma escola.');
        }

        $statusDestino = (string) $aluno->status;
        $pendenciaOrigemId = $aluno->pendencia_origem_aluno_id;
        $permiteContraTurno = (bool) $aluno->permite_contra_turno;
        $turmaOrigemId = (int) $aluno->id_turma;

        $aluno->forceFill([
            'status' => Aluno::STATUS_REMANEJADO,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Aluno remanejado automaticamente pela sincronização da planilha.',
        ])->save();

        $novo = Aluno::query()->create([
            'nome' => $linha['nome'] ?? $aluno->nome,
            'cgm' => $aluno->cgm,
            'data_nascimento' => $linha['data_nascimento'] ?? $aluno->data_nascimento?->toDateString(),
            'sexo' => $linha['sexo'] ?? $aluno->sexo,
            'data_matricula' => $linha['data_matricula'] ?? $aluno->data_matricula?->toDateString(),
            'id_turma' => (int) $turmaDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_PRINCIPAL,
            'permite_contra_turno' => $permiteContraTurno,
            'status' => $statusDestino,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Matrícula atualizada por remanejamento da sincronização da planilha.',
            'aluno_origem_id' => (int) $aluno->id,
            'turma_origem_id' => $turmaOrigemId,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_REMANEJAMENTO,
            'pendencia_origem_aluno_id' => $pendenciaOrigemId,
        ]);

        $this->movimentacaoService->moverDocumentosAvaliativos(
            $aluno,
            $novo,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO,
            $usuario,
        );

        $this->movimentacaoService->alinharContraTurnoAoPrincipal(
            $novo,
            $usuario,
            'Vínculo atualizado após remanejamento do Principal pela planilha.'
        );

        return $novo->fresh(['turma.serie']);
    }

    /**
     * @param  array<string, mixed>  $linha
     */
    private function remanejarContraTurnoPorImportacao(
        Aluno $contraTurno,
        Aluno $principal,
        Turma $turmaDestino,
        array $linha,
        ?User $usuario,
    ): Aluno {
        $contraTurno->refresh()->loadMissing('turma');
        $principal->refresh()->loadMissing('turma');

        if (
            ! $contraTurno->isContraTurno()
            || (! $contraTurno->estaMatriculado() && ! $contraTurno->estaPendente())
        ) {
            throw new RuntimeException('Somente vínculos de Contra Turno matriculados ou pendentes podem ser remanejados pela importação.');
        }

        if (! $principal->turma || (int) $principal->turma->id_escola !== (int) $turmaDestino->id_escola) {
            throw new RuntimeException('A turma de Contra Turno precisa pertencer à mesma escola da matrícula Principal.');
        }

        /* A linha do Contra Turno também pode atualizar os dados do Principal. */
        $this->atualizarDadosImportados($principal, $linha);
        $principal->refresh();

        $contraTurno->forceFill([
            'status' => Aluno::STATUS_REMANEJADO,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Contra turno remanejado automaticamente pela sincronização da planilha.',
        ])->save();

        $principal->forceFill([
            'permite_contra_turno' => true,
        ])->save();

        $novo = Aluno::query()->create([
            'nome' => $principal->nome,
            'cgm' => $principal->cgm,
            'data_nascimento' => $principal->data_nascimento,
            'sexo' => $principal->sexo,
            'data_matricula' => $principal->data_matricula,
            'id_turma' => (int) $turmaDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'permite_contra_turno' => false,
            'status' => $principal->status,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => $principal->estaPendente()
                ? 'Vínculo de contra turno remanejado e mantido Pendente junto ao Principal.'
                : 'Vínculo de contra turno atualizado por remanejamento da sincronização da planilha.',
            'aluno_origem_id' => (int) $principal->id,
            'turma_origem_id' => (int) $principal->id_turma,
            'movimentacao_origem' => AlunoMovimentacaoService::MOVIMENTACAO_CONTRA_TURNO,
        ]);

        $this->movimentacaoService->moverDocumentosAvaliativos(
            $contraTurno,
            $novo,
            AvaliacaoAlunoDocumentoHistorico::MOVIMENTACAO_REMANEJAMENTO,
            $usuario,
        );

        return $novo->fresh(['turma.serie']);
    }

    /**
     * Atualiza somente dados pessoais realmente diferentes e reflete a alteração no
     * outro vínculo da mesma escola. Turma e tipo de vínculo permanecem independentes.
     *
     * @param  array<string, mixed>  $linha
     */
    private function atualizarDadosImportados(Aluno $aluno, array $linha): bool
    {
        $dados = [
            'nome' => $linha['nome'] ?? $aluno->nome,
            'data_nascimento' => $linha['data_nascimento'] ?? $aluno->data_nascimento?->toDateString(),
            'sexo' => $linha['sexo'] ?? $aluno->sexo,
        ];

        if (filled($linha['data_matricula'] ?? null)) {
            $dados['data_matricula'] = $linha['data_matricula'];
        }

        $aluno->fill($dados);

        if (! $aluno->isDirty()) {
            return false;
        }

        $aluno->save();
        $this->movimentacaoService->sincronizarDadosCompartilhados($aluno);

        return true;
    }

    /**
     * Duas turmas são iguais para a sincronização quando representam a mesma
     * escola, série, turma e turno, mesmo que seus IDs internos sejam diferentes.
     */
    private function mesmaTurmaLogica(?Turma $atual, Turma $destino): bool
    {
        if (! $atual) {
            return false;
        }

        if ((int) $atual->id === (int) $destino->id) {
            return true;
        }

        $atual->loadMissing('serie');
        $destino->loadMissing('serie');

        return (int) $atual->id_escola === (int) $destino->id_escola
            && $this->normalizarTexto($atual->serie?->nome) === $this->normalizarTexto($destino->serie?->nome)
            && $this->normalizarTexto($atual->nome) === $this->normalizarTexto($destino->nome)
            && $this->normalizarTexto($atual->turno) === $this->normalizarTexto($destino->turno);
    }

    private function normalizarTexto(mixed $valor): string
    {
        return Str::of((string) $valor)
            ->ascii()
            ->lower()
            ->replaceMatches('/\s+/', ' ')
            ->trim()
            ->value();
    }

    private function chaveUnidade(int $escolaId, string $cgm): string
    {
        return $escolaId.'|'.Aluno::normalizarCgm($cgm);
    }

    /**
     * @return array{total_importado:int,total_atualizado:int,total_remanejado:int,total_sem_alteracao:int,total_pendente:int}
     */
    private function resultadoVazio(): array
    {
        return [
            'total_importado' => 0,
            'total_atualizado' => 0,
            'total_remanejado' => 0,
            'total_sem_alteracao' => 0,
            'total_pendente' => 0,
        ];
    }
}
