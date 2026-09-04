<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Avaliacao;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Pauta;
use App\Models\Turma;
use Illuminate\Support\Facades\DB;

class AvaliacaoAutosaveContextService
{
    /**
     * Resolve, em um unico snapshot SQL, o contexto operacional que ja foi
     * inicializado. A validacao detalhada continua sendo o fallback para
     * dados invalidos e para turmas que ainda dependem da migracao lazy.
     *
     * @param array<string, mixed> $dados
     * @return array{avaliacao:Avaliacao,turma:Turma,ciclo:AvaliacaoTurmaCiclo,aluno:Aluno,pauta:Pauta|null}|null
     */
    public function resolver(array $dados): ?array
    {
        $query = DB::table('avaliacoes as avaliacao')
            ->join('avaliacao_turma as vinculo_turma', function ($join): void {
                $join->on('vinculo_turma.avaliacao_id', '=', 'avaliacao.id');
            })
            ->join('turmas as turma', 'turma.id', '=', 'vinculo_turma.turma_id')
            ->join('avaliacao_turma_ciclos as ciclo', function ($join): void {
                $join
                    ->on('ciclo.avaliacao_id', '=', 'avaliacao.id')
                    ->on('ciclo.turma_avaliativa_id', '=', 'turma.id');
            })
            ->join('avaliacao_turma_tokens_escrita as token', 'token.ciclo_id', '=', 'ciclo.id')
            ->join('alunos as aluno', function ($join) use ($dados): void {
                $join
                    ->on('aluno.id_turma', '=', 'ciclo.turma_origem_id')
                    ->where('aluno.id', (int) $dados['aluno_id']);
            })
            ->where('avaliacao.id', (int) $dados['avaliacao_id'])
            ->where('turma.id', (int) $dados['turma_id'])
            ->whereNotNull('ciclo.operacional_inicializado_em')
            ->whereIn('aluno.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->select([
                'avaliacao.id as avaliacao_id',
                'avaliacao.tipo_avaliacao_id as avaliacao_tipo_id',
                'avaliacao.status as avaliacao_status',
                'avaliacao.data_inicio as avaliacao_data_inicio',
                'avaliacao.data_fim as avaliacao_data_fim',
                'avaliacao.data_inicio_preenchimento as avaliacao_data_inicio_preenchimento',
                'avaliacao.data_fim_preenchimento as avaliacao_data_fim_preenchimento',
                'turma.id as turma_id',
                'turma.id_escola as turma_escola_id',
                'turma.id_serie as turma_serie_id',
                'ciclo.id as ciclo_id',
                'ciclo.avaliacao_id as ciclo_avaliacao_id',
                'ciclo.turma_avaliativa_id as ciclo_turma_avaliativa_id',
                'ciclo.turma_origem_id as ciclo_turma_origem_id',
                'ciclo.status as ciclo_status',
                'ciclo.operacional_inicializado_em as ciclo_operacional_inicializado_em',
                'token.id as token_escrita_bloqueado_id',
                'aluno.id as aluno_id',
                'aluno.id_turma as aluno_turma_id',
                'aluno.status as aluno_status',
                'aluno.pendencia_origem_aluno_id as aluno_pendencia_origem_id',
            ]);

        if ($dados['tipo'] === 'resposta') {
            $query
                ->join('pautas as pauta', function ($join) use ($dados): void {
                    $join
                        ->where('pauta.id', (int) ($dados['pauta_id'] ?? 0))
                        ->where('pauta.status', true);
                })
                ->join('avaliacao_pauta as vinculo_pauta', function ($join): void {
                    $join
                        ->on('vinculo_pauta.avaliacao_id', '=', 'avaliacao.id')
                        ->on('vinculo_pauta.pauta_id', '=', 'pauta.id');
                })
                ->addSelect([
                    'pauta.id as pauta_id',
                    'pauta.tipo_avaliacao_id as pauta_tipo_id',
                    'pauta.serie_id as pauta_serie_id',
                    'pauta.componente_curricular_id as pauta_componente_id',
                    'pauta.status as pauta_status',
                ]);
        }

        $contexto = $query->first();
        if (! $contexto) {
            return null;
        }

        $avaliacao = (new Avaliacao)->newFromBuilder([
            'id' => $contexto->avaliacao_id,
            'tipo_avaliacao_id' => $contexto->avaliacao_tipo_id,
            'status' => $contexto->avaliacao_status,
            'data_inicio' => $contexto->avaliacao_data_inicio,
            'data_fim' => $contexto->avaliacao_data_fim,
            'data_inicio_preenchimento' => $contexto->avaliacao_data_inicio_preenchimento,
            'data_fim_preenchimento' => $contexto->avaliacao_data_fim_preenchimento,
        ]);
        $turma = (new Turma)->newFromBuilder([
            'id' => $contexto->turma_id,
            'id_escola' => $contexto->turma_escola_id,
            'id_serie' => $contexto->turma_serie_id,
        ]);
        $ciclo = (new AvaliacaoTurmaCiclo)->newFromBuilder([
            'id' => $contexto->ciclo_id,
            'avaliacao_id' => $contexto->ciclo_avaliacao_id,
            'turma_avaliativa_id' => $contexto->ciclo_turma_avaliativa_id,
            'turma_origem_id' => $contexto->ciclo_turma_origem_id,
            'status' => $contexto->ciclo_status,
            'operacional_inicializado_em' => $contexto->ciclo_operacional_inicializado_em,
            'token_escrita_bloqueado_id' => $contexto->token_escrita_bloqueado_id,
        ]);
        $aluno = (new Aluno)->newFromBuilder([
            'id' => $contexto->aluno_id,
            'id_turma' => $contexto->aluno_turma_id,
            'status' => $contexto->aluno_status,
            'pendencia_origem_aluno_id' => $contexto->aluno_pendencia_origem_id,
        ]);
        $pauta = $dados['tipo'] === 'resposta'
            ? (new Pauta)->newFromBuilder([
                'id' => $contexto->pauta_id,
                'tipo_avaliacao_id' => $contexto->pauta_tipo_id,
                'serie_id' => $contexto->pauta_serie_id,
                'componente_curricular_id' => $contexto->pauta_componente_id,
                'status' => $contexto->pauta_status,
            ])
            : null;

        return compact('avaliacao', 'turma', 'ciclo', 'aluno', 'pauta');
    }
}
