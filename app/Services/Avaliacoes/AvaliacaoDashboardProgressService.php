<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Support\Avaliacoes\AvaliacaoDashboardProgressData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardProgressService
{
    public function __construct(
        private readonly AvaliacaoDashboardOnDemandQueryService $queries,
    ) {}

    /**
     * Calcula o progresso diretamente dos documentos e da estrutura atual.
     *
     * @param  list<int>  $avaliacaoIds
     * @param  list<int>|null  $escolaIds  Null representa escopo global.
     * @param  list<int>|null  $professorIds  Null representa acompanhamento amplo.
     * @return array<int, AvaliacaoDashboardProgressData>
     */
    public function batch(
        array $avaliacaoIds,
        ?array $escolaIds,
        ?array $professorIds = null,
    ): array {
        $avaliacaoIds = $this->ids($avaliacaoIds);

        if ($avaliacaoIds === []) {
            return [];
        }

        $result = collect($avaliacaoIds)->mapWithKeys(fn (int $avaliacaoId): array => [
            $avaliacaoId => new AvaliacaoDashboardProgressData(
                consolidacaoStatus: 'consolidado',
                percentual: null,
            ),
        ])->all();

        if ($escolaIds === []) {
            return $result;
        }

        $esperados = $this->queries->esperados($avaliacaoIds);
        $respondidos = $this->queries->respostas($avaliacaoIds, somenteCompletas: true);

        if (is_array($escolaIds)) {
            $escolaIds = $this->ids($escolaIds);
            $esperados->whereIn('t.id_escola', $escolaIds);
            $respondidos->whereIn('t.id_escola', $escolaIds);
        }

        if (is_array($professorIds)) {
            $professorIds = $this->ids($professorIds);
            $this->aplicarEscopoProfessor($esperados, $professorIds, 'tcp_progresso_esperado');
            $this->aplicarEscopoProfessor($respondidos, $professorIds, 'tcp_progresso_respondido');
        }

        $distinctEsperado = $this->distinctCombinacaoExpr('at.avaliacao_id', 'at.turma_id', 'p.id', 'aln.id');
        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');
        $totais = $esperados
            ->groupBy('at.avaliacao_id')
            ->selectRaw("at.avaliacao_id, COUNT(DISTINCT {$distinctEsperado}) AS total")
            ->get()
            ->pluck('total', 'avaliacao_id');
        $concluidos = $respondidos
            ->groupBy('ar.avaliacao_id')
            ->selectRaw("ar.avaliacao_id, COUNT(DISTINCT {$distinctRespondido}) AS total")
            ->get()
            ->pluck('total', 'avaliacao_id');

        if (app(AvaliacaoPersistencia::class)->leRelacional() && $professorIds === null) {
            $historico = DB::table('avaliacao_snapshot_resumos_componentes as resumo')
                ->join('avaliacao_turma_ciclos as ciclo', 'ciclo.id', '=', 'resumo.ciclo_id')
                ->join('turmas as turma_historica', 'turma_historica.id', '=', 'ciclo.turma_avaliativa_id')
                ->whereIn('ciclo.avaliacao_id', $avaliacaoIds)
                ->where('ciclo.status', 'concluida')
                ->whereColumn('ciclo.snapshot_evento_atual_id', 'resumo.evento_id')
                ->when(is_array($escolaIds), fn ($query) => $query->whereIn('turma_historica.id_escola', $escolaIds))
                ->groupBy('ciclo.avaliacao_id')
                ->selectRaw('ciclo.avaliacao_id, SUM(resumo.respostas_esperadas) as esperado, SUM(resumo.respostas_concluidas) as concluido')
                ->get()
                ->keyBy('avaliacao_id');

            foreach ($historico as $avaliacaoId => $item) {
                $totais[$avaliacaoId] = (int) ($totais[$avaliacaoId] ?? 0) + (int) $item->esperado;
                $concluidos[$avaliacaoId] = (int) ($concluidos[$avaliacaoId] ?? 0) + (int) $item->concluido;
            }
        }

        foreach ($avaliacaoIds as $avaliacaoId) {
            $total = (int) ($totais[$avaliacaoId] ?? 0);
            $concluido = min((int) ($concluidos[$avaliacaoId] ?? 0), $total);

            $result[$avaliacaoId] = new AvaliacaoDashboardProgressData(
                consolidacaoStatus: 'consolidado',
                percentual: $total > 0 ? round(($concluido / $total) * 100, 2) : null,
            );
        }

        return $result;
    }

    /**
     * Retorna os mesmos totais do progresso, agregados sem materializar a
     * matriz aluno x pauta. Disponível para a navegação operacional.
     *
     * @param  list<int>  $avaliacaoIds
     * @param  list<int>|null  $escolaIds
     * @param  list<int>|null  $professorIds
     * @return array<int, array{preenchidas: int, total: int, percentual: int, escolas: array<int, array>, series: array<int, array>, turmas: array<int, array>}>
     */
    public function detalhadoRapido(
        array $avaliacaoIds,
        ?array $escolaIds,
        ?array $professorIds = null,
    ): array {
        $avaliacaoIds = $this->ids($avaliacaoIds);
        $resultado = collect($avaliacaoIds)->mapWithKeys(fn (int $avaliacaoId): array => [
            $avaliacaoId => $this->resumoVazio(),
        ])->all();

        if ($avaliacaoIds === [] || $escolaIds === [] || ! app(AvaliacaoPersistencia::class)->leRelacional()) {
            return $resultado;
        }

        if (app(AvaliacaoDashboardTurmaResumoService::class)->disponivel()) {
            return $this->detalhadoPorResumos($resultado, $avaliacaoIds, $escolaIds, $professorIds);
        }

        $alunosElegiveis = DB::table('alunos as aluno_progresso')
            ->whereIn('aluno_progresso.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->where(function (Builder $alunos): void {
                $alunos
                    ->where('aluno_progresso.status', '!=', Aluno::STATUS_PENDENTE)
                    ->orWhereNull('aluno_progresso.pendencia_origem_aluno_id')
                    ->orWhere('aluno_progresso.pendencia_origem_aluno_id', '<=', 0);
            })
            ->groupBy('aluno_progresso.id_turma')
            ->selectRaw('aluno_progresso.id_turma as turma_origem_id, COUNT(*) as alunos_total');

        $esperados = DB::table('avaliacao_turma_ciclos as ciclo_progresso')
            ->join('turmas as turma_progresso', 'turma_progresso.id', '=', 'ciclo_progresso.turma_avaliativa_id')
            ->joinSub($alunosElegiveis, 'alunos_progresso', fn ($join) => $join
                ->on('alunos_progresso.turma_origem_id', '=', 'ciclo_progresso.turma_origem_id'))
            ->join('avaliacao_pauta as ap_progresso', 'ap_progresso.avaliacao_id', '=', 'ciclo_progresso.avaliacao_id')
            ->join('pautas as pauta_progresso', 'pauta_progresso.id', '=', 'ap_progresso.pauta_id')
            ->whereIn('ciclo_progresso.avaliacao_id', $avaliacaoIds)
            ->whereIn('ciclo_progresso.status', ['aberta', 'reaberta'])
            ->where('pauta_progresso.status', true)
            ->where(fn (Builder $pautas) => $pautas
                ->whereNull('pauta_progresso.serie_id')
                ->orWhereColumn('pauta_progresso.serie_id', 'turma_progresso.id_serie'));

        if (is_array($escolaIds)) {
            $esperados->whereIn('turma_progresso.id_escola', $this->ids($escolaIds));
        }

        if (is_array($professorIds)) {
            $professorIds = $this->ids($professorIds);
            $professorIds === []
                ? $esperados->whereRaw('1 = 0')
                : $esperados->where(function (Builder $pautas) use ($professorIds): void {
                    $pautas
                        ->whereNull('pauta_progresso.componente_curricular_id')
                        ->orWhereExists(function ($vinculos) use ($professorIds): void {
                            $vinculos
                                ->selectRaw('1')
                                ->from('turma_componente_professor as tcp_progresso')
                                ->whereColumn('tcp_progresso.turma_id', 'turma_progresso.id')
                                ->whereColumn('tcp_progresso.componente_curricular_id', 'pauta_progresso.componente_curricular_id')
                                ->where('tcp_progresso.tem_professor', true)
                                ->whereIn('tcp_progresso.professor_id', $professorIds);
                        });
                });
        }

        $esperados = $esperados
            ->groupBy(
                'ciclo_progresso.avaliacao_id',
                'turma_progresso.id',
                'turma_progresso.id_escola',
                'turma_progresso.id_serie',
                'alunos_progresso.alunos_total',
            )
            ->selectRaw('ciclo_progresso.avaliacao_id, turma_progresso.id as turma_id, turma_progresso.id_escola as escola_id, turma_progresso.id_serie as serie_id')
            ->selectRaw('alunos_progresso.alunos_total * COUNT(DISTINCT pauta_progresso.id) as total')
            ->get();

        $respondidos = $this->queries->respostas($avaliacaoIds, somenteCompletas: true);
        if (is_array($escolaIds)) {
            $respondidos->whereIn('t.id_escola', $this->ids($escolaIds));
        }
        if (is_array($professorIds)) {
            $this->aplicarEscopoProfessor($respondidos, $professorIds, 'tcp_progresso_detalhado');
        }

        $distinctRespondido = $this->distinctCombinacaoExpr('ar.avaliacao_id', 'ar.turma_id', 'ar.pauta_id', 'ar.aluno_id');
        $respondidos = $respondidos
            ->groupBy('ar.avaliacao_id', 't.id', 't.id_escola', 't.id_serie')
            ->selectRaw('ar.avaliacao_id, t.id as turma_id, t.id_escola as escola_id, t.id_serie as serie_id')
            ->selectRaw("COUNT(DISTINCT {$distinctRespondido}) as total")
            ->get()
            ->keyBy(fn (object $item): string => $item->avaliacao_id.':'.$item->turma_id);

        foreach ($esperados as $esperado) {
            $avaliacaoId = (int) $esperado->avaliacao_id;
            $turmaId = (int) $esperado->turma_id;
            $total = (int) $esperado->total;
            $preenchidas = min((int) ($respondidos->get($avaliacaoId.':'.$turmaId)?->total ?? 0), $total);
            $this->acumularResumo($resultado[$avaliacaoId], 'turmas', $turmaId, $preenchidas, $total);
            $this->acumularResumo($resultado[$avaliacaoId], 'series', (int) $esperado->serie_id, $preenchidas, $total);
            $this->acumularResumo($resultado[$avaliacaoId], 'escolas', (int) $esperado->escola_id, $preenchidas, $total);
            $resultado[$avaliacaoId]['preenchidas'] += $preenchidas;
            $resultado[$avaliacaoId]['total'] += $total;
        }

        $historicos = DB::table('avaliacao_snapshot_resumos_componentes as resumo_historico')
            ->join('avaliacao_turma_ciclos as ciclo_historico', 'ciclo_historico.id', '=', 'resumo_historico.ciclo_id')
            ->join('turmas as turma_historica', 'turma_historica.id', '=', 'ciclo_historico.turma_avaliativa_id')
            ->whereIn('ciclo_historico.avaliacao_id', $avaliacaoIds)
            ->where('ciclo_historico.status', 'concluida')
            ->whereColumn('ciclo_historico.snapshot_evento_atual_id', 'resumo_historico.evento_id');

        if (is_array($escolaIds)) {
            $historicos->whereIn('turma_historica.id_escola', $this->ids($escolaIds));
        }
        if (is_array($professorIds)) {
            $professorIds === []
                ? $historicos->whereRaw('1 = 0')
                : $historicos->where(function (Builder $componentes) use ($professorIds): void {
                    $componentes
                        ->whereNull('resumo_historico.componente_curricular_id')
                        ->orWhereExists(function ($vinculos) use ($professorIds): void {
                            $vinculos
                                ->selectRaw('1')
                                ->from('turma_componente_professor as tcp_historico')
                                ->whereColumn('tcp_historico.turma_id', 'turma_historica.id')
                                ->whereColumn('tcp_historico.componente_curricular_id', 'resumo_historico.componente_curricular_id')
                                ->where('tcp_historico.tem_professor', true)
                                ->whereIn('tcp_historico.professor_id', $professorIds);
                        });
                });
        }

        foreach ($historicos
            ->groupBy('ciclo_historico.avaliacao_id', 'turma_historica.id', 'turma_historica.id_escola', 'turma_historica.id_serie', 'resumo_historico.componente_curricular_id')
            ->selectRaw('ciclo_historico.avaliacao_id, turma_historica.id as turma_id, turma_historica.id_escola as escola_id, turma_historica.id_serie as serie_id, COALESCE(resumo_historico.componente_curricular_id, 0) as componente_chave')
            ->selectRaw('SUM(resumo_historico.respostas_esperadas) as total, SUM(resumo_historico.respostas_concluidas) as preenchidas')
            ->get() as $historico) {
            $avaliacaoId = (int) $historico->avaliacao_id;
            $turmaId = (int) $historico->turma_id;
            $total = (int) $historico->total;
            $preenchidas = min((int) $historico->preenchidas, $total);
            $this->acumularResumo($resultado[$avaliacaoId], 'turmas', $turmaId, $preenchidas, $total);
            $this->acumularResumo($resultado[$avaliacaoId], 'series', (int) $historico->serie_id, $preenchidas, $total);
            $this->acumularResumo($resultado[$avaliacaoId], 'escolas', (int) $historico->escola_id, $preenchidas, $total);
            $resultado[$avaliacaoId]['preenchidas'] += $preenchidas;
            $resultado[$avaliacaoId]['total'] += $total;
        }

        return $this->finalizarResumos($resultado);
    }

    private function detalhadoPorResumos(array $resultado, array $avaliacaoIds, ?array $escolaIds, ?array $professorIds): array
    {
        $resumos = DB::table('avaliacao_dashboard_turma_resumos as resumo_rapido')
            ->join('turmas as turma_rapida', 'turma_rapida.id', '=', 'resumo_rapido.turma_id')
            ->whereIn('resumo_rapido.avaliacao_id', $avaliacaoIds);

        if (is_array($escolaIds)) {
            $resumos->whereIn('turma_rapida.id_escola', $this->ids($escolaIds));
        }

        if ($professorIds !== null) {
            $professorIds = $this->ids($professorIds);
            $resumos->where('resumo_rapido.componente_chave', '!=', AvaliacaoDashboardTurmaResumoService::TOTAL_COMPONENT_KEY);
            $professorIds === []
                ? $resumos->whereRaw('1 = 0')
                : $resumos->where(function (Builder $componentes) use ($professorIds): void {
                    $componentes
                        ->whereNull('resumo_rapido.componente_curricular_id')
                        ->orWhereExists(function ($vinculos) use ($professorIds): void {
                            $vinculos
                                ->selectRaw('1')
                                ->from('turma_componente_professor as tcp_resumo')
                                ->whereColumn('tcp_resumo.turma_id', 'turma_rapida.id')
                                ->whereColumn('tcp_resumo.componente_curricular_id', 'resumo_rapido.componente_curricular_id')
                                ->where('tcp_resumo.tem_professor', true)
                                ->whereIn('tcp_resumo.professor_id', $professorIds);
                        });
                });
        }

        foreach ($resumos
            ->groupBy('resumo_rapido.avaliacao_id', 'turma_rapida.id', 'turma_rapida.id_escola', 'turma_rapida.id_serie', 'resumo_rapido.componente_chave')
            ->selectRaw('resumo_rapido.avaliacao_id, turma_rapida.id as turma_id, turma_rapida.id_escola as escola_id, turma_rapida.id_serie as serie_id, resumo_rapido.componente_chave')
            ->selectRaw('SUM(resumo_rapido.preenchimentos_esperados) as total, SUM(resumo_rapido.preenchimentos_respondidos) as preenchidas')
            ->get() as $linha) {
            $avaliacaoId = (int) $linha->avaliacao_id;
            $total = (int) $linha->total;
            $preenchidas = min((int) $linha->preenchidas, $total);
            $componenteId = (int) $linha->componente_chave;
            $this->acumularLinha(
                $resultado[$avaliacaoId],
                $linha,
                $preenchidas,
                $total,
                $componenteId,
                $professorIds !== null || $componenteId === AvaliacaoDashboardTurmaResumoService::TOTAL_COMPONENT_KEY,
            );
        }

        $historicos = DB::table('avaliacao_snapshot_resumos_componentes as resumo_historico')
            ->join('avaliacao_turma_ciclos as ciclo_historico', 'ciclo_historico.id', '=', 'resumo_historico.ciclo_id')
            ->join('turmas as turma_historica', 'turma_historica.id', '=', 'ciclo_historico.turma_avaliativa_id')
            ->whereIn('ciclo_historico.avaliacao_id', $avaliacaoIds)
            ->where('ciclo_historico.status', 'concluida')
            ->whereColumn('ciclo_historico.snapshot_evento_atual_id', 'resumo_historico.evento_id');

        if (is_array($escolaIds)) {
            $historicos->whereIn('turma_historica.id_escola', $this->ids($escolaIds));
        }
        if (is_array($professorIds)) {
            $professorIds === []
                ? $historicos->whereRaw('1 = 0')
                : $historicos->where(function (Builder $componentes) use ($professorIds): void {
                    $componentes
                        ->whereNull('resumo_historico.componente_curricular_id')
                        ->orWhereExists(function ($vinculos) use ($professorIds): void {
                            $vinculos
                                ->selectRaw('1')
                                ->from('turma_componente_professor as tcp_historico_rapido')
                                ->whereColumn('tcp_historico_rapido.turma_id', 'turma_historica.id')
                                ->whereColumn('tcp_historico_rapido.componente_curricular_id', 'resumo_historico.componente_curricular_id')
                                ->where('tcp_historico_rapido.tem_professor', true)
                                ->whereIn('tcp_historico_rapido.professor_id', $professorIds);
                        });
                });
        }

        foreach ($historicos
            ->groupBy('ciclo_historico.avaliacao_id', 'turma_historica.id', 'turma_historica.id_escola', 'turma_historica.id_serie', 'resumo_historico.componente_curricular_id')
            ->selectRaw('ciclo_historico.avaliacao_id, turma_historica.id as turma_id, turma_historica.id_escola as escola_id, turma_historica.id_serie as serie_id, COALESCE(resumo_historico.componente_curricular_id, 0) as componente_chave')
            ->selectRaw('SUM(resumo_historico.respostas_esperadas) as total, SUM(resumo_historico.respostas_concluidas) as preenchidas')
            ->get() as $linha) {
            $avaliacaoId = (int) $linha->avaliacao_id;
            $total = (int) $linha->total;
            $preenchidas = min((int) $linha->preenchidas, $total);
            $componenteId = (int) $linha->componente_chave;
            $this->acumularLinha(
                $resultado[$avaliacaoId], $linha, $preenchidas, $total, $componenteId,
                $professorIds !== null || $componenteId === AvaliacaoDashboardTurmaResumoService::TOTAL_COMPONENT_KEY,
            );
        }

        return $this->finalizarResumos($resultado);
    }

    private function acumularLinha(array &$resumo, object $linha, int $preenchidas, int $total, ?int $componenteId = null, bool $incluirAgregados = true): void
    {
        $turmaId = (int) $linha->turma_id;
        if ($incluirAgregados) {
            $this->acumularResumo($resumo, 'turmas', $turmaId, $preenchidas, $total);
            $this->acumularResumo($resumo, 'series', (int) $linha->serie_id, $preenchidas, $total);
            $this->acumularResumo($resumo, 'escolas', (int) $linha->escola_id, $preenchidas, $total);
        }
        if ($componenteId !== null && $componenteId !== AvaliacaoDashboardTurmaResumoService::TOTAL_COMPONENT_KEY) {
            $this->acumularResumo($resumo, 'componentes', $componenteId, $preenchidas, $total);
            $resumo['componentes_por_turma'][$turmaId][$componenteId] ??= ['preenchidas' => 0, 'total' => 0, 'percentual' => 0];
            $resumo['componentes_por_turma'][$turmaId][$componenteId]['preenchidas'] += $preenchidas;
            $resumo['componentes_por_turma'][$turmaId][$componenteId]['total'] += $total;
        }
        if ($incluirAgregados) {
            $resumo['preenchidas'] += $preenchidas;
            $resumo['total'] += $total;
        }
    }

    private function finalizarResumos(array $resultado): array
    {
        foreach ($resultado as &$resumo) {
            $resumo['percentual'] = $this->percentual($resumo['preenchidas'], $resumo['total']);
            foreach (['escolas', 'series', 'turmas', 'componentes'] as $nivel) {
                foreach ($resumo[$nivel] as &$item) {
                    $item['percentual'] = $this->percentual($item['preenchidas'], $item['total']);
                }
                unset($item);
            }
            foreach ($resumo['componentes_por_turma'] as &$componentes) {
                foreach ($componentes as &$item) {
                    $item['percentual'] = $this->percentual($item['preenchidas'], $item['total']);
                }
                unset($item);
            }
            unset($componentes);
        }
        unset($resumo);

        return $resultado;
    }

    private function resumoVazio(): array
    {
        return ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'escolas' => [], 'series' => [], 'turmas' => [], 'componentes' => [], 'componentes_por_turma' => []];
    }

    private function acumularResumo(array &$resumo, string $nivel, int $id, int $preenchidas, int $total): void
    {
        $resumo[$nivel][$id] ??= ['preenchidas' => 0, 'total' => 0, 'percentual' => 0];
        $resumo[$nivel][$id]['preenchidas'] += $preenchidas;
        $resumo[$nivel][$id]['total'] += $total;
    }

    private function percentual(int $preenchidas, int $total): int
    {
        return $total > 0 ? (int) round(($preenchidas / $total) * 100) : 0;
    }

    /** @param list<int> $professorIds */
    private function aplicarEscopoProfessor(Builder $query, array $professorIds, string $alias): void
    {
        if ($professorIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $query
            ->join("turma_componente_professor as {$alias}", function ($join) use ($alias): void {
                $join->on("{$alias}.turma_id", '=', 't.id')
                    ->where("{$alias}.tem_professor", true)
                    ->where(function ($join) use ($alias): void {
                        $join->whereNull('p.componente_curricular_id')
                            ->orOn("{$alias}.componente_curricular_id", '=', 'p.componente_curricular_id');
                    });
            })
            ->whereIn("{$alias}.professor_id", $professorIds);
    }

    private function distinctCombinacaoExpr(string ...$colunas): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return implode(" || ':' || ", array_map(
                fn (string $coluna): string => "CAST({$coluna} AS TEXT)",
                $colunas,
            ));
        }

        return 'CONCAT_WS(\':\', '.implode(', ', $colunas).')';
    }

    /** @return list<int> */
    private function ids(iterable $ids): array
    {
        return collect($ids)
            ->filter(fn ($id): bool => filled($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
