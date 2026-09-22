<?php

namespace App\Services\Avaliacoes;

use App\Models\AvaliacaoTurmaCiclo;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Consolida os fatos operacionais do dashboard em uma única leitura por
 * dimensão. O dashboard pode derivar cards, gráficos e acompanhamento a
 * partir destas linhas sem repetir a matriz aluno x pauta.
 */
class AvaliacaoDashboardAggregationService
{
    public function __construct(
        private readonly AvaliacaoDashboardOnDemandQueryService $queries,
    ) {}

    /**
     * @param  list<int>  $avaliacaoIds
     * @param  array<string, mixed>  $filtros
     * @param  list<int>|null  $escolaIdsPermitidas
     * @return array{
     *     turmas: list<array<string, int|float|string|null>>,
     *     componentes: list<array<string, int|float|string|null>>,
     *     series: list<array<string, int|float|string|null>>,
     *     escolas: list<array<string, int|float|string|null|bool>>,
     *     turnos: array<string, array<string, int|float>>,
     *     historicos: list<array<string, mixed>>
     * }
     */
    public function consolidar(array $avaliacaoIds, array $filtros, ?array $escolaIdsPermitidas = null): array
    {
        $avaliacaoIds = collect($avaliacaoIds)
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->values()
            ->all();

        if ($avaliacaoIds === []) {
            return [
                'turmas' => [],
                'componentes' => [],
                'series' => [],
                'escolas' => [],
                'turnos' => $this->turnosVazios(),
                'historicos' => [],
            ];
        }

        // Agregue no banco antes de trazer os dados para o PHP. A matriz
        // aluno x pauta completa é muito maior que a projeção necessária
        // para os cards e pode estourar o timeout em avaliações grandes.
        $esperadosQuery = $this->aplicarFiltrosEsperados(
            $this->queries->esperados($avaliacaoIds),
            $filtros,
            $escolaIdsPermitidas,
        );
        $respondidosQuery = $this->aplicarFiltrosRespondidos(
            $this->queries->respostas($avaliacaoIds, somenteCompletas: true),
            $filtros,
            $escolaIdsPermitidas,
        );
        $esperados = $this->agruparPorTurmaComponente($esperadosQuery);
        $respondidos = $this->agruparPorTurmaComponente($respondidosQuery, respostas: true);
        $alunosEsperados = $this->agruparPorAluno(
            $this->aplicarFiltrosEsperados(
                $this->queries->esperados($avaliacaoIds),
                $filtros,
                $escolaIdsPermitidas,
            ),
        );
        $alunosRespondidos = $this->agruparPorAluno(
            $this->aplicarFiltrosRespondidos(
                $this->queries->respostas($avaliacaoIds, somenteCompletas: true),
                $filtros,
                $escolaIdsPermitidas,
            ),
            respostas: true,
        );

        $turmas = $this->consolidarTurmas($esperados, $respondidos, $alunosEsperados, $alunosRespondidos);

        return [
            'turmas' => array_values($turmas),
            'componentes' => $this->agruparDimensao($turmas, 'componente_id', 'componente_nome'),
            'series' => $this->agruparDimensao($turmas, 'serie_id', 'serie_nome'),
            'escolas' => $this->consolidarEscolas($turmas),
            'turnos' => $this->consolidarTurnos($turmas, $alunosEsperados, $alunosRespondidos),
            'historicos' => $this->historicos($avaliacaoIds, $filtros, $escolaIdsPermitidas),
        ];
    }

    private function agruparPorTurmaComponente(QueryBuilder $query, bool $respostas = false): Collection
    {
        $turmaId = $respostas ? 'ar.turma_id' : 'at.turma_id';
        $avaliacaoId = $respostas ? 'ar.avaliacao_id' : 'at.avaliacao_id';
        $pautaId = $respostas ? 'ar.pauta_id' : 'p.id';
        $alunoId = $respostas ? 'ar.aluno_id' : 'aln.id';

        return $query
            ->leftJoin('escolas as e', 'e.id', '=', 't.id_escola')
            ->leftJoin('series as s', 's.id', '=', 't.id_serie')
            ->leftJoin('componentes_curriculares as cc', 'cc.id', '=', 'p.componente_curricular_id')
            ->groupBy(
                $avaliacaoId,
                $turmaId,
                't.id_escola',
                'e.nome',
                't.id_serie',
                's.nome',
                't.nome',
                't.turno',
                'av.nome',
                'p.componente_curricular_id',
                'cc.nome',
            )
            ->select([
                $avaliacaoId.' as avaliacao_id',
                $turmaId.' as turma_id',
                'av.nome as avaliacao_nome',
                't.id_escola as escola_id',
                'e.nome as escola_nome',
                't.id_serie as serie_id',
                's.nome as serie_nome',
                't.nome as turma_nome',
                't.turno',
                'p.componente_curricular_id as componente_id',
                'cc.nome as componente_nome',
            ])
            ->selectRaw('COUNT(DISTINCT '.$this->distinctKey($avaliacaoId, $turmaId, $pautaId, $alunoId).') as preenchimentos')
            ->selectRaw('COUNT(DISTINCT '.$pautaId.') as pautas_total')
            ->when(
                $respostas,
                fn (QueryBuilder $query): QueryBuilder => $query->selectRaw('MAX(ar.respondido_em) as ultima_resposta_em'),
                fn (QueryBuilder $query): QueryBuilder => $query->selectRaw('NULL as ultima_resposta_em'),
            )
            ->get()
            ->mapWithKeys(function (object $item): array {
                $chave = implode(':', [
                    (int) $item->avaliacao_id,
                    (int) $item->turma_id,
                    (int) ($item->componente_id ?? 0),
                ]);

                return [$chave => $item];
            });
    }

    private function agruparPorAluno(QueryBuilder $query, bool $respostas = false): Collection
    {
        $turmaId = $respostas ? 'ar.turma_id' : 'at.turma_id';
        $avaliacaoId = $respostas ? 'ar.avaliacao_id' : 'at.avaliacao_id';
        $pautaId = $respostas ? 'ar.pauta_id' : 'p.id';
        $alunoId = $respostas ? 'ar.aluno_id' : 'aln.id';

        return $query
            ->groupBy($avaliacaoId, $turmaId, 't.turno', $alunoId)
            ->select([
                $avaliacaoId.' as avaliacao_id',
                $turmaId.' as turma_id',
                't.turno',
                $alunoId.' as aluno_id',
            ])
            ->selectRaw('COUNT(DISTINCT '.$this->distinctKey($avaliacaoId, $turmaId, $pautaId, $alunoId).') as preenchimentos')
            ->get()
            ->mapWithKeys(function (object $item): array {
                $chave = implode(':', [
                    (int) $item->avaliacao_id,
                    (int) $item->turma_id,
                    (int) $item->aluno_id,
                ]);

                return [$chave => $item];
            });
    }

    private function consolidarTurmas(Collection $esperados, Collection $respondidos, Collection $alunosEsperados, Collection $alunosRespondidos): array
    {
        $linhas = [];

        foreach ($esperados->keys()->merge($respondidos->keys())->unique() as $chave) {
            $esperado = $esperados->get($chave);
            $respondido = $respondidos->get($chave);
            $fonte = $esperado ?? $respondido;
            $turmaChave = implode(':', [(int) $fonte->avaliacao_id, (int) $fonte->turma_id]);
            $componenteId = (int) ($fonte->componente_id ?? 0);
            $esperadas = (int) ($esperado->preenchimentos ?? 0);
            $respondidas = min((int) ($respondido->preenchimentos ?? 0), $esperadas);

            if (! isset($linhas[$turmaChave])) {
                $linhas[$turmaChave] = [
                    'avaliacao_id' => (int) $fonte->avaliacao_id,
                    'turma_id' => (int) $fonte->turma_id,
                    'avaliacao_nome' => (string) ($fonte->avaliacao_nome ?? '-'),
                    'escola_id' => (int) ($fonte->escola_id ?? 0),
                    'escola_nome' => (string) ($fonte->escola_nome ?? '-'),
                    'serie_id' => (int) ($fonte->serie_id ?? 0),
                    'serie_nome' => (string) ($fonte->serie_nome ?? '-'),
                    'turma_nome' => (string) ($fonte->turma_nome ?? '-'),
                    'turno' => (string) ($fonte->turno ?? '-'),
                    'preenchimentos_esperados' => 0,
                    'preenchimentos_respondidos' => 0,
                    'pautas_total' => 0,
                    'ultima_resposta_em' => null,
                    'componentes' => [],
                ];
            }

            $linhas[$turmaChave]['preenchimentos_esperados'] += $esperadas;
            $linhas[$turmaChave]['preenchimentos_respondidos'] += $respondidas;
            $linhas[$turmaChave]['pautas_total'] += (int) ($esperado->pautas_total ?? 0);
            $linhas[$turmaChave]['ultima_resposta_em'] = max(
                (string) ($linhas[$turmaChave]['ultima_resposta_em'] ?? ''),
                (string) ($respondido->ultima_resposta_em ?? ''),
            ) ?: null;
            $linhas[$turmaChave]['componentes'][$componenteId] = [
                'id' => $componenteId,
                'nome' => (string) ($fonte->componente_nome ?? '-'),
                'preenchimentos_esperados' => $esperadas,
                'preenchimentos_respondidos' => $respondidas,
            ];
        }

        $alunosEsperadosPorTurma = $alunosEsperados->groupBy(fn (object $item): string => implode(':', [(int) $item->avaliacao_id, (int) $item->turma_id]));
        $alunosRespondidosPorTurma = $alunosRespondidos->groupBy(fn (object $item): string => implode(':', [(int) $item->avaliacao_id, (int) $item->turma_id]));

        foreach ($linhas as $chave => &$linha) {
            $alunosDaTurma = $alunosEsperadosPorTurma->get($chave, collect());
            $respondidosDaTurma = $alunosRespondidosPorTurma->get($chave, collect())->keyBy(fn (object $item): string => implode(':', [(int) $item->avaliacao_id, (int) $item->turma_id, (int) $item->aluno_id]));
            $linha['alunos_total'] = $alunosDaTurma->count();
            $linha['alunos_pendentes'] = $alunosDaTurma->filter(function (object $aluno) use ($respondidosDaTurma): bool {
                $respondido = $respondidosDaTurma->get(implode(':', [(int) $aluno->avaliacao_id, (int) $aluno->turma_id, (int) $aluno->aluno_id]));

                return (int) ($respondido->preenchimentos ?? 0) < (int) $aluno->preenchimentos;
            })->count();
        }
        unset($linha);

        return $linhas;
    }

    private function agruparDimensao(array $turmas, string $idKey, string $nameKey): array
    {
        $agregado = [];

        foreach ($turmas as $turma) {
            $componentes = $turma['componentes'] ?? [];
            foreach ($componentes as $componente) {
                $id = (int) ($componente['id'] ?? 0);
                $chave = (string) $id;
                $agregado[$chave] ??= [
                    'id' => $id,
                    'nome' => (string) ($componente['nome'] ?? '-'),
                    'preenchimentos_esperados' => 0,
                    'preenchimentos_respondidos' => 0,
                ];
                $agregado[$chave]['preenchimentos_esperados'] += (int) ($componente['preenchimentos_esperados'] ?? 0);
                $agregado[$chave]['preenchimentos_respondidos'] += (int) ($componente['preenchimentos_respondidos'] ?? 0);
            }
        }

        if ($idKey === 'componente_id') {
            return array_values($agregado);
        }

        $porDimensao = [];
        foreach ($turmas as $turma) {
            $id = (int) ($turma[$idKey] ?? 0);
            $chave = (string) $id;
            $porDimensao[$chave] ??= [
                'id' => $id,
                'nome' => (string) ($turma[$nameKey] ?? '-'),
                'preenchimentos_esperados' => 0,
                'preenchimentos_respondidos' => 0,
            ];
            $porDimensao[$chave]['preenchimentos_esperados'] += (int) $turma['preenchimentos_esperados'];
            $porDimensao[$chave]['preenchimentos_respondidos'] += (int) $turma['preenchimentos_respondidos'];
        }

        return array_values($porDimensao);
    }

    private function consolidarEscolas(array $turmas): array
    {
        $escolas = [];

        foreach ($turmas as $turma) {
            $id = (int) $turma['escola_id'];
            $escolas[$id] ??= [
                'id' => $id,
                'nome' => (string) $turma['escola_nome'],
                'preenchimentos_esperados' => 0,
                'preenchimentos_respondidos' => 0,
                'turmas_esperadas' => 0,
                'turmas_com_resposta' => 0,
                'turmas_preenchidas' => 0,
                'turmas_incompletas' => 0,
            ];
            $escolas[$id]['preenchimentos_esperados'] += (int) $turma['preenchimentos_esperados'];
            $escolas[$id]['preenchimentos_respondidos'] += (int) $turma['preenchimentos_respondidos'];
            $escolas[$id]['turmas_esperadas']++;
            $escolas[$id]['turmas_com_resposta'] += (int) $turma['preenchimentos_respondidos'] > 0 ? 1 : 0;
            $escolas[$id]['turmas_preenchidas'] += (int) $turma['preenchimentos_esperados'] > 0
                && (int) $turma['preenchimentos_respondidos'] >= (int) $turma['preenchimentos_esperados'] ? 1 : 0;
            $escolas[$id]['turmas_incompletas'] += (int) $turma['preenchimentos_esperados'] <= 0
                || (int) $turma['preenchimentos_respondidos'] < (int) $turma['preenchimentos_esperados'] ? 1 : 0;
        }

        return array_values(array_map(function (array $escola): array {
            $esperadas = (int) $escola['preenchimentos_esperados'];
            $respondidas = min((int) $escola['preenchimentos_respondidos'], $esperadas);
            $turmas = (int) $escola['turmas_esperadas'];
            $incompletas = (int) $escola['turmas_incompletas'];

            return [
                ...$escola,
                'preenchimentos_respondidos' => $respondidas,
                'preenchimentos_pendentes' => max($esperadas - $respondidas, 0),
                'percentual_preenchimento' => $esperadas > 0 ? round(($respondidas / $esperadas) * 100, 1) : 0.0,
                'percentual_pendentes' => $esperadas > 0 ? round((max($esperadas - $respondidas, 0) / $esperadas) * 100, 1) : 0.0,
                'percentual_turmas' => $turmas > 0 ? round(((int) $escola['turmas_preenchidas'] / $turmas) * 100, 1) : 0.0,
                'percentual_turmas_incompletas' => $turmas > 0 ? round(($incompletas / $turmas) * 100, 1) : 0.0,
                'respostas_total' => $respondidas,
                'esta_preenchida' => $turmas > 0 && $incompletas === 0,
                'escolas_nao_preenchidas' => 0,
            ];
        }, $escolas));
    }

    private function consolidarTurnos(array $turmas, Collection $alunosEsperados, Collection $alunosRespondidos): array
    {
        $turnos = $this->turnosVazios();

        foreach ($turmas as $turma) {
            $turno = (string) $turma['turno'];
            if (! isset($turnos[$turno])) {
                continue;
            }
            $turnos[$turno]['esperadas'] += (int) $turma['preenchimentos_esperados'];
            $turnos[$turno]['respondidas'] += (int) $turma['preenchimentos_respondidos'];
        }

        foreach ($alunosEsperados as $aluno) {
            $turno = (string) $aluno->turno;
            if (! isset($turnos[$turno])) {
                continue;
            }
            $chave = implode(':', [(int) $aluno->avaliacao_id, (int) $aluno->turma_id, (int) $aluno->aluno_id]);
            $respondido = $alunosRespondidos->get($chave);
            $turnos[$turno]['alunos_total']++;
            if ((int) ($respondido->preenchimentos ?? 0) < (int) $aluno->preenchimentos) {
                $turnos[$turno]['alunos_pendentes']++;
            }
        }

        foreach ($turnos as &$turno) {
            $turno['respondidas'] = min((int) $turno['respondidas'], (int) $turno['esperadas']);
            $turno['percentual_preenchimento'] = (int) $turno['esperadas'] > 0
                ? round(((int) $turno['respondidas'] / (int) $turno['esperadas']) * 100, 1)
                : 0.0;
        }
        unset($turno);

        return $turnos;
    }

    private function aplicarFiltrosEsperados(QueryBuilder $query, array $filtros, ?array $escolaIdsPermitidas): QueryBuilder
    {
        $this->aplicarFiltrosEstruturais($query, $filtros, $escolaIdsPermitidas);

        if (($filtros['professores_ids'] ?? []) !== []) {
            $query
                ->join('turma_componente_professor as tcp_dashboard', 'tcp_dashboard.turma_id', '=', 't.id')
                ->whereIn('tcp_dashboard.professor_id', $filtros['professores_ids'])
                ->where('tcp_dashboard.tem_professor', true)
                ->where(function (QueryBuilder $query): void {
                    $query->whereNull('p.componente_curricular_id')
                        ->orWhereColumn('tcp_dashboard.componente_curricular_id', 'p.componente_curricular_id');
                });
        }

        return $query;
    }

    private function aplicarFiltrosRespondidos(QueryBuilder $query, array $filtros, ?array $escolaIdsPermitidas): QueryBuilder
    {
        $this->aplicarFiltrosEstruturais($query, $filtros, $escolaIdsPermitidas);

        if (($filtros['professores_ids'] ?? []) !== []) {
            $query->whereIn('ar.professor_id', $filtros['professores_ids']);
        }
        if (($filtros['alternativas_ids'] ?? []) !== []) {
            $query->whereIn('ar.alternativa_id', $filtros['alternativas_ids']);
        }

        return $query;
    }

    private function aplicarFiltrosEstruturais(QueryBuilder $query, array $filtros, ?array $escolaIdsPermitidas): void
    {
        if ($escolaIdsPermitidas !== null) {
            $query->whereIn('t.id_escola', $escolaIdsPermitidas);
        }
        if (($filtros['series_ids'] ?? []) !== []) {
            $query->whereIn('t.id_serie', $filtros['series_ids']);
        }
        if (($filtros['turnos'] ?? []) !== []) {
            $query->whereIn('t.turno', $filtros['turnos']);
        }
        if (($filtros['escolas_ids'] ?? []) !== []) {
            $query->whereIn('t.id_escola', $filtros['escolas_ids']);
        }
        if (($filtros['componentes_ids'] ?? []) !== []) {
            $query->whereIn('p.componente_curricular_id', $filtros['componentes_ids']);
        }
        if (($filtros['pautas_ids'] ?? []) !== []) {
            $query->whereIn('p.id', $filtros['pautas_ids']);
        }
    }

    /** @return list<array<string, mixed>> */
    private function historicos(array $avaliacaoIds, array $filtros, ?array $escolaIdsPermitidas): array
    {
        if (! DB::getSchemaBuilder()->hasTable('avaliacao_turma_ciclos')) {
            return [];
        }

        return DB::table('avaliacao_turma_ciclos as ciclo')
            ->join('avaliacoes as av', 'av.id', '=', 'ciclo.avaliacao_id')
            ->join('turmas as t', 't.id', '=', 'ciclo.turma_avaliativa_id')
            ->leftJoin('avaliacao_snapshot_eventos as evento', 'evento.id', '=', 'ciclo.snapshot_evento_atual_id')
            ->leftJoin('escolas as e', 'e.id', '=', 't.id_escola')
            ->leftJoin('series as s', 's.id', '=', 't.id_serie')
            ->leftJoin('avaliacao_snapshot_resumos_componentes as resumo', function ($join): void {
                $join->on('resumo.ciclo_id', '=', 'ciclo.id')
                    ->on('resumo.evento_id', '=', 'ciclo.snapshot_evento_atual_id');
            })
            ->whereIn('ciclo.avaliacao_id', $avaliacaoIds)
            ->where('ciclo.status', AvaliacaoTurmaCiclo::STATUS_CONCLUIDA)
            ->when($escolaIdsPermitidas !== null, fn (QueryBuilder $query): QueryBuilder => $query->whereIn('t.id_escola', $escolaIdsPermitidas))
            ->when(($filtros['series_ids'] ?? []) !== [], fn (QueryBuilder $query): QueryBuilder => $query->whereIn('t.id_serie', $filtros['series_ids']))
            ->when(($filtros['turnos'] ?? []) !== [], fn (QueryBuilder $query): QueryBuilder => $query->whereIn('t.turno', $filtros['turnos']))
            ->when(($filtros['escolas_ids'] ?? []) !== [], fn (QueryBuilder $query): QueryBuilder => $query->whereIn('t.id_escola', $filtros['escolas_ids']))
            ->groupBy('ciclo.id', 'ciclo.avaliacao_id', 'ciclo.turma_avaliativa_id', 'av.nome', 't.nome', 't.turno', 'e.id', 'e.nome', 's.id', 's.nome', 'evento.total_alunos')
            ->select([
                'ciclo.avaliacao_id',
                'ciclo.turma_avaliativa_id as turma_id',
                'av.nome as avaliacao_nome',
                'e.id as escola_id',
                'e.nome as escola_nome',
                's.id as serie_id',
                's.nome as serie_nome',
                't.nome as turma_nome',
                't.turno',
            ])
            ->selectRaw('ciclo.status as ciclo_status')
            ->selectRaw('COALESCE(SUM(resumo.respostas_esperadas), 0) as preenchimentos_esperados')
            ->selectRaw('COALESCE(SUM(resumo.respostas_concluidas), 0) as preenchimentos_respondidos')
            ->selectRaw('COALESCE(SUM(resumo.respostas_esperadas), 0) as pautas_total')
            ->selectRaw('COALESCE(evento.total_alunos, 0) as alunos_total')
            ->selectRaw('MAX(ciclo.concluida_em) as ultima_resposta_em')
            ->get()
            ->map(fn (object $item): array => (array) $item)
            ->all();
    }

    private function distinctKey(string ...$columns): string
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            return implode(" || '|#|' || ", $columns);
        }

        return 'CONCAT('.implode(", '|#|', ", $columns).')';
    }

    /** @return array<string, array<string, int|float>> */
    private function turnosVazios(): array
    {
        return [
            'manha' => ['esperadas' => 0, 'respondidas' => 0, 'percentual_preenchimento' => 0.0, 'alunos_total' => 0, 'alunos_pendentes' => 0],
            'tarde' => ['esperadas' => 0, 'respondidas' => 0, 'percentual_preenchimento' => 0.0, 'alunos_total' => 0, 'alunos_pendentes' => 0],
        ];
    }
}
