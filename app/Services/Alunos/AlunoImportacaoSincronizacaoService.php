<?php

namespace App\Services\Alunos;

use App\Models\Aluno;
use App\Models\AvaliacaoAlunoDocumentoHistorico;
use App\Models\Turma;
use App\Models\User;
use App\Services\AlunoMovimentacaoService;
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
            ->with('turma')
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
                if ((int) $existente->id_turma !== (int) $turmaDestino->id) {
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

            /*
             * Somente os vínculos que realmente ainda não existem na unidade são
             * enviados ao fluxo em lote. Isso mantém a sincronização rápida mesmo
             * para planilhas com milhares de alunos.
             */
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
     * Sincroniza vínculos de contra turno já existentes e cria os ainda inexistentes.
     * O vínculo de Contra Turno pode utilizar inclusive a mesma turma do Principal,
     * desde que ambos pertençam à mesma escola.
     *
     * Na importação por planilha, um Principal Pendente é um vínculo válido. O
     * status Pendente representa apenas a transferência ainda não concluída e não
     * deve interromper a sincronização dos demais dados enviados pela planilha.
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
            ->with('turma')
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

        $contraTurnosAtivos = Aluno::query()
            ->with('turma')
            ->whereIn('cgm', $cgms)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->get()
            ->keyBy(fn (Aluno $aluno): string => Aluno::normalizarCgm((string) $aluno->cgm));

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
            $contraTurno = $contraTurnosAtivos->get($cgm);

            if ($contraTurno) {
                if ((int) $contraTurno->id_turma !== (int) $turmaDestino->id) {
                    $novo = $this->remanejarContraTurnoPorImportacao(
                        $contraTurno,
                        $principal,
                        $turmaDestino,
                        $linha,
                        $usuario,
                    );

                    $contraTurnosAtivos->put($cgm, $novo);
                    $resultado['total_remanejado']++;

                    continue;
                }

                if ($this->atualizarDadosImportados($contraTurno, $linha)) {
                    $resultado['total_atualizado']++;
                } else {
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

            $contraTurnosAtivos->put($cgm, $novo->fresh(['turma']));
            $resultado['total_importado']++;
        }

        return $resultado;
    }

    /**
     * Cria o vínculo secundário diretamente no fluxo de sincronização da planilha.
     *
     * O fluxo manual de vinculação pode manter regras próprias de interface. Para
     * a planilha, porém, o Principal Pendente não é impedimento: ele já representa
     * a matrícula cadastrada na escola de destino aguardando a transferência.
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

        $principal->forceFill([
            'permite_contra_turno' => true,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Contra turno sincronizado pela planilha de alunos.',
        ])->save();

        return Aluno::query()->create([
            'nome' => $linha['nome'] ?? $principal->nome,
            'cgm' => $principal->cgm,
            'data_nascimento' => $linha['data_nascimento'] ?? $principal->data_nascimento?->toDateString(),
            'sexo' => $linha['sexo'] ?? $principal->sexo,
            'data_matricula' => $linha['data_matricula'] ?? $principal->data_matricula?->toDateString(),
            'id_turma' => (int) $turmaDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'permite_contra_turno' => false,
            'status' => Aluno::STATUS_MATRICULADO,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Vínculo de contra turno criado por sincronização da planilha de alunos.',
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

        /*
         * Um vínculo de contra turno ativo aponta para o Principal atual.
         * Se o Principal foi recriado pelo remanejamento, atualizamos essa referência.
         */
        Aluno::query()
            ->where('cgm_contra_turno_ativo', $novo->cgm)
            ->where('tipo_vinculo', Aluno::TIPO_VINCULO_CONTRA_TURNO)
            ->where('status', Aluno::STATUS_MATRICULADO)
            ->update([
                'aluno_origem_id' => (int) $novo->id,
                'turma_origem_id' => (int) $novo->id_turma,
                'updated_at' => now(),
            ]);

        return $novo->fresh(['turma']);
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

        if (! $contraTurno->isContraTurno() || ! $contraTurno->estaMatriculado()) {
            throw new RuntimeException('Somente vínculos ativos de Contra Turno podem ser remanejados pela importação.');
        }

        if (! $principal->turma || (int) $principal->turma->id_escola !== (int) $turmaDestino->id_escola) {
            throw new RuntimeException('A turma de Contra Turno precisa pertencer à mesma escola da matrícula Principal.');
        }

        $contraTurno->forceFill([
            'status' => Aluno::STATUS_REMANEJADO,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Contra turno remanejado automaticamente pela sincronização da planilha.',
        ])->save();

        $principal->forceFill([
            'permite_contra_turno' => true,
        ])->save();

        /*
         * Mantém o mesmo contrato de vincularContraTurno(): o vínculo secundário
         * ativo aponta para o Principal atual. O registro anterior permanece como
         * REMANEJADO e o histórico avaliativo registra a movimentação entre os IDs.
         */
        $novo = Aluno::query()->create([
            'nome' => $linha['nome'] ?? $contraTurno->nome,
            'cgm' => $contraTurno->cgm,
            'data_nascimento' => $linha['data_nascimento'] ?? $contraTurno->data_nascimento?->toDateString(),
            'sexo' => $linha['sexo'] ?? $contraTurno->sexo,
            'data_matricula' => $linha['data_matricula'] ?? $contraTurno->data_matricula?->toDateString(),
            'id_turma' => (int) $turmaDestino->id,
            'tipo_vinculo' => Aluno::TIPO_VINCULO_CONTRA_TURNO,
            'permite_contra_turno' => false,
            'status' => Aluno::STATUS_MATRICULADO,
            'status_alterado_em' => now(),
            'status_alterado_por' => $usuario?->id,
            'status_motivo' => 'Vínculo de contra turno atualizado por remanejamento da sincronização da planilha.',
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

        return $novo->fresh(['turma']);
    }

    /**
     * Atualiza apenas os dados realmente diferentes. Data da matrícula vazia na
     * planilha não apaga uma data já cadastrada, pois a coluna continua opcional.
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

        return true;
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
