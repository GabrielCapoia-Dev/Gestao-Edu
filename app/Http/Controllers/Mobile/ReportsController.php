<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Avaliacao;
use App\Models\ComponenteCurricular;
use App\Models\Escola;
use App\Models\Professor;
use App\Models\Serie;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class ReportsController extends Controller
{
    public function index(Request $request): View
    {
        return view('mobile.reports.index', [
            'cards' => $this->getCards($request),
        ]);
    }

    public function dashboard(Request $request): View
    {
        $this->authorize('viewReportsDashboard', Avaliacao::class);

        return view('mobile.reports.dashboard', [
            'stats' => [
                'totalProfessores' => Professor::count(),
                'totalTurmas' => Turma::count(),
                'totalComponentes' => ComponenteCurricular::count(),
                'totalEscolas' => Escola::where('ativo', true)->count(),
                'vinculos' => TurmaComponenteProfessor::count(),
                'semProfessor' => TurmaComponenteProfessor::whereNull('professor_id')->count(),
                'comProfessor' => TurmaComponenteProfessor::whereNotNull('professor_id')->count(),
            ],
            'topComponentes' => DB::table('turma_componente_professor as tcp')
                ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
                ->select(
                    'cc.nome',
                    DB::raw('COUNT(*) as total'),
                    DB::raw('SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor'),
                    DB::raw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor')
                )
                ->groupBy('cc.id', 'cc.nome')
                ->orderByDesc('sem_professor')
                ->limit(5)
                ->get(),
        ]);
    }

    public function professorByClass(Request $request): View
    {
        $this->authorize('viewProfessorComponentTurmaReport', Avaliacao::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'escola_id' => $request->integer('escola_id') ?: null,
            'serie_id' => $request->integer('serie_id') ?: null,
            'turno' => trim((string) $request->string('turno')),
        ];

        $baseQuery = $this->buildProfessorByClassQuery($filters);

        $records = (clone $baseQuery)
            ->orderBy('escolas.nome')
            ->orderBy('series.nome')
            ->orderBy('turmas.nome')
            ->orderBy('componentes_curriculares.nome')
            ->paginate(12)
            ->withQueryString();

        return view('mobile.reports.professor-componente-turma', [
            'filters' => $filters,
            'records' => $records,
            'escolas' => Escola::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'series' => Serie::orderBy('nome')->get(['id', 'nome']),
            'stats' => [
                'total' => (clone $baseQuery)->count(),
                'semProfessor' => (clone $baseQuery)->whereNull('turma_componente_professor.professor_id')->count(),
                'comProfessor' => (clone $baseQuery)->whereNotNull('turma_componente_professor.professor_id')->count(),
            ],
        ]);
    }

    public function missingTeachers(Request $request): View
    {
        $this->authorize('viewMissingTeachersReport', Avaliacao::class);

        $filters = [
            'search' => trim((string) $request->string('search')),
            'escola_id' => $request->integer('escola_id') ?: null,
            'serie_id' => $request->integer('serie_id') ?: null,
            'turno' => trim((string) $request->string('turno')),
            'situacao' => trim((string) $request->string('situacao')),
            'componentes_sort' => trim((string) $request->string('componentes_sort', 'sem_professor')),
            'componentes_dir' => trim((string) $request->string('componentes_dir', 'desc')),
            'turmas_sort' => trim((string) $request->string('turmas_sort', 'componentes_sem_professor')),
            'turmas_dir' => trim((string) $request->string('turmas_dir', 'desc')),
        ];

        $baseVinculos = $this->baseVinculosQuery($filters);

        return view('mobile.reports.componentes-faltando', [
            'filters' => $filters,
            'escolas' => Escola::where('ativo', true)->orderBy('nome')->get(['id', 'nome']),
            'series' => Serie::orderBy('nome')->get(['id', 'nome']),
            'stats' => [
                'totalProfessores' => Professor::count(),
                'totalTurmas' => Turma::count(),
                'totalComponentes' => ComponenteCurricular::count(),
                'totalEscolas' => Escola::where('ativo', true)->count(),
                'vinculos' => (clone $baseVinculos)->count(),
                'comProfessor' => (clone $baseVinculos)->whereNotNull('tcp.professor_id')->count(),
                'semProfessor' => (clone $baseVinculos)->whereNull('tcp.professor_id')->count(),
                'totalTurmasFaltando' => $this->countSubquery($this->turmasQuery($filters, applySorting: false)),
            ],
            'componentes' => $this->paginateQuery(
                $this->componentesQuery($filters),
                8,
                'componentes_page',
            ),
            'turmas' => $this->paginateQuery(
                $this->turmasQuery($filters),
                8,
                'turmas_page',
            ),
        ]);
    }

    protected function getCards(Request $request): array
    {
        return array_values(array_filter([
            Gate::allows('viewReportsDashboard', Avaliacao::class) ? $this->makeCard(
                title: 'Dashboard',
                description: 'Panorama geral dos vínculos, escolas, turmas e componentes.',
                url: route('mobile.reports.dashboard'),
                badge: 'DG',
                tone: 'emerald',
            ) : null,
            Gate::allows('viewProfessorComponentTurmaReport', Avaliacao::class) ? $this->makeCard(
                title: 'Professor por turma',
                description: 'Consulta detalhada com filtros de escola, série, turno e busca.',
                url: route('mobile.reports.professor-by-class'),
                badge: 'PT',
                tone: 'violet',
            ) : null,
            Gate::allows('viewMissingTeachersReport', Avaliacao::class) ? $this->makeCard(
                title: 'Componentes faltando',
                description: 'Dois painéis: componentes afetados e turmas com falta de cobertura.',
                url: route('mobile.reports.missing-teachers'),
                badge: 'CF',
                tone: 'rose',
            ) : null,
        ]));
    }

    protected function makeCard(
        string $title,
        string $description,
        string $url,
        string $badge,
        string $tone,
    ): array {
        return compact('title', 'description', 'url', 'badge', 'tone');
    }

    protected function buildProfessorByClassQuery(array $filters)
    {
        return TurmaComponenteProfessor::query()
            ->join('turmas', 'turmas.id', '=', 'turma_componente_professor.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('series', 'series.id', '=', 'turmas.id_serie')
            ->join('componentes_curriculares', 'componentes_curriculares.id', '=', 'turma_componente_professor.componente_curricular_id')
            ->leftJoin('professores', 'professores.id', '=', 'turma_componente_professor.professor_id')
            ->when($filters['search'] !== '', function ($query) use ($filters): void {
                $search = "%{$filters['search']}%";

                $query->where(function ($subQuery) use ($search): void {
                    $subQuery
                        ->where('escolas.nome', 'like', $search)
                        ->orWhere('series.nome', 'like', $search)
                        ->orWhere('turmas.nome', 'like', $search)
                        ->orWhere('componentes_curriculares.nome', 'like', $search)
                        ->orWhere('professores.nome', 'like', $search);
                });
            })
            ->when($filters['escola_id'], fn ($query, $value) => $query->where('turmas.id_escola', $value))
            ->when($filters['serie_id'], fn ($query, $value) => $query->where('turmas.id_serie', $value))
            ->when($filters['turno'] !== '', fn ($query) => $query->where('turmas.turno', $filters['turno']))
            ->select([
                'turma_componente_professor.id',
                'escolas.nome as escola_nome',
                'series.nome as serie_nome',
                'turmas.nome as turma_nome',
                'turmas.turno',
                'componentes_curriculares.nome as componente_nome',
                DB::raw("COALESCE(professores.nome, 'Sem professor') as professor_nome"),
                DB::raw("COALESCE(professores.email, 'Não informado') as professor_email"),
                DB::raw("COALESCE(professores.matricula, 'Não informada') as professor_matricula"),
            ]);
    }

    protected function baseVinculosQuery(array $filters): QueryBuilder
    {
        $query = DB::table('turma_componente_professor as tcp')
            ->join('componentes_curriculares as cc', 'cc.id', '=', 'tcp.componente_curricular_id')
            ->join('turmas', 'turmas.id', '=', 'tcp.turma_id')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->join('series', 'series.id', '=', 'turmas.id_serie');

        if ($filters['escola_id']) {
            $query->where('turmas.id_escola', $filters['escola_id']);
        }

        if ($filters['serie_id']) {
            $query->where('turmas.id_serie', $filters['serie_id']);
        }

        if ($filters['turno'] !== '') {
            $query->where('turmas.turno', $filters['turno']);
        }

        if ($filters['search'] !== '') {
            $search = '%'.$filters['search'].'%';

            $query->where(function (QueryBuilder $subQuery) use ($search): void {
                $subQuery
                    ->where('cc.nome', 'like', $search)
                    ->orWhere('turmas.nome', 'like', $search)
                    ->orWhere('escolas.nome', 'like', $search)
                    ->orWhere('series.nome', 'like', $search);
            });
        }

        return $query;
    }

    protected function componentesQuery(array $filters, bool $applySorting = true): QueryBuilder
    {
        $query = $this->baseVinculosQuery($filters)
            ->selectRaw('
                cc.id as componente_id,
                cc.nome as componente_nome,
                COUNT(*) as total,
                SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) as com_professor,
                SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) as sem_professor,
                ROUND(
                    COALESCE(
                        (
                            SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) * 100.0
                        ) / NULLIF(COUNT(*), 0),
                        0
                    ),
                    2
                ) as cobertura_percentual
            ')
            ->groupBy('cc.id', 'cc.nome')
            ->havingRaw('SUM(CASE WHEN tcp.professor_id IS NULL THEN 1 ELSE 0 END) > 0');

        if ($filters['situacao'] === 'sem_professor') {
            $query->havingRaw('SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) = 0');
        } elseif ($filters['situacao'] === 'com_professor') {
            $query->havingRaw('SUM(CASE WHEN tcp.professor_id IS NOT NULL THEN 1 ELSE 0 END) > 0');
        }

        if ($applySorting) {
            $this->applyComponentesOrdering($query, $filters['componentes_sort'], $filters['componentes_dir']);
        }

        return $query;
    }

    protected function turmasQuery(array $filters, bool $applySorting = true): QueryBuilder
    {
        $query = $this->baseVinculosQuery($filters)
            ->whereNull('tcp.professor_id')
            ->selectRaw('
                turmas.id as turma_id,
                turmas.nome as turma_nome,
                turmas.turno as turma_turno,
                escolas.nome as escola_nome,
                series.nome as serie_nome,
                COUNT(*) as componentes_sem_professor
            ')
            ->groupBy('turmas.id', 'turmas.nome', 'turmas.turno', 'escolas.nome', 'series.nome');

        if ($applySorting) {
            $this->applyTurmasOrdering($query, $filters['turmas_sort'], $filters['turmas_dir']);
        }

        return $query;
    }

    protected function applyComponentesOrdering(QueryBuilder $query, string $field, string $direction): void
    {
        $direction = $this->normalizeSortDirection($direction);

        match ($field) {
            'componente_nome' => $query->orderBy('componente_nome', $direction),
            'total' => $query->orderBy('total', $direction)->orderBy('componente_nome'),
            'com_professor' => $query->orderBy('com_professor', $direction)->orderBy('componente_nome'),
            'cobertura_percentual' => $query->orderBy('cobertura_percentual', $direction)->orderBy('componente_nome'),
            default => $query->orderBy('sem_professor', $direction)->orderBy('componente_nome'),
        };
    }

    protected function applyTurmasOrdering(QueryBuilder $query, string $field, string $direction): void
    {
        $direction = $this->normalizeSortDirection($direction);

        match ($field) {
            'escola_nome' => $query->orderBy('escola_nome', $direction)->orderBy('serie_nome'),
            'serie_nome' => $query->orderBy('serie_nome', $direction)->orderBy('turma_nome'),
            'turma_nome' => $query->orderBy('turma_nome', $direction),
            'turma_turno' => $query->orderBy('turma_turno', $direction)->orderBy('turma_nome'),
            default => $query->orderBy('componentes_sem_professor', $direction)->orderBy('escola_nome'),
        };
    }

    protected function normalizeSortDirection(string $direction): string
    {
        return $direction === 'asc' ? 'asc' : 'desc';
    }

    protected function countSubquery(QueryBuilder $query): int
    {
        return DB::query()->fromSub($query, 'sub')->count();
    }

    protected function paginateQuery(QueryBuilder $query, int $perPage, string $pageName): LengthAwarePaginator
    {
        $page = LengthAwarePaginator::resolveCurrentPage($pageName);
        $items = (clone $query)
            ->offset(($page - 1) * $perPage)
            ->limit($perPage)
            ->get();

        return new LengthAwarePaginator(
            items: $items,
            total: $this->countSubquery($query),
            perPage: $perPage,
            currentPage: $page,
            options: [
                'path' => request()->url(),
                'pageName' => $pageName,
                'query' => request()->query(),
            ],
        );
    }

}
