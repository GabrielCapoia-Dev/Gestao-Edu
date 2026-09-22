<?php

namespace App\Services\Dashboard;

use App\Models\Aluno;
use App\Models\Enums\EventoCalendarioCategoria;
use App\Models\Enums\EventoCalendarioTransporteEscopo;
use App\Models\EventoCalendario;
use App\Models\EventoCalendarioAlunoSnapshot;
use App\Models\EventoCalendarioParticipanteSnapshot;
use App\Models\Turma;
use App\Models\User;
use App\Services\Dashboard\Calendar\Sources\ManualCalendarEventSource;
use App\Support\Dashboard\Calendar\CalendarQueryContext;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;

final class EventoCalendarioDetalhesService
{
    public function __construct(
        private readonly ManualCalendarEventSource $eventos,
        private readonly DashboardUserContextFactory $contextos,
        private readonly EventoCalendarioPublicoService $publico,
    ) {}

    /** @return array<string, mixed> */
    public function resumo(User $user, int $eventoId, string $contexto): array
    {
        $evento = $this->autorizar($user, $eventoId, $contexto);
        $escolasIds = $this->escolasVisiveis($user, $contexto);
        $evento->load([
            'publicoAlvo.escolas:id,nome',
            'escolasAgendadas' => fn ($query) => $query
                ->when($escolasIds !== null, fn ($q) => $q->whereIn('escola_id', $escolasIds))
                ->with('escola:id,nome'),
        ]);
        $escolasEvento = $evento->escolasAgendadas->pluck('escola')->filter();
        if ($escolasEvento->isEmpty()) {
            $escolasEvento = $evento->publicoAlvo?->escolas ?? collect();
            if ($escolasIds !== null) {
                $escolasEvento = $escolasEvento->whereIn('id', $escolasIds);
            }
        }

        $participantes = $evento->participantes_snapshot_em
            ? $this->aplicarEscopoParticipantes($evento->participantesSnapshot(), $escolasIds)->count()
            : $this->publico->destinatarios($evento)
                ->when($escolasIds !== null, fn (Collection $usuarios) => $usuarios->filter(
                    fn (User $usuario): bool => array_intersect($usuario->idsEscolasVinculadas(), $escolasIds) !== [],
                ))
                ->count();
        $alunos = $evento->alunos_snapshot_em
            ? $evento->alunosSnapshot()->when($escolasIds !== null, fn ($q) => $q->whereIn('escola_id', $escolasIds))->count()
            : (int) $evento->escolasAgendadas->where('precisa_transporte', true)->sum('quantidade_estimada_transporte');

        return [
            'id' => (int) $evento->getKey(),
            'titulo' => $evento->titulo,
            'descricao' => $evento->descricao,
            'local' => $evento->local,
            'endereco_mapa' => $evento->endereco_mapa,
            'latitude' => $evento->latitude !== null ? (float) $evento->latitude : null,
            'longitude' => $evento->longitude !== null ? (float) $evento->longitude : null,
            'data' => $evento->data_inicio->format('d/m/Y'),
            'inicio' => $evento->data_inicio->format('H:i'),
            'fim' => $evento->data_fim->format('H:i'),
            'categoria' => $evento->categoria === EventoCalendarioCategoria::OUTRO && filled($evento->categoria_detalhe)
                ? $evento->categoria_detalhe
                : $evento->categoria?->label(),
            'status' => $evento->status?->value,
            'status_label' => $evento->status?->label() ?? 'Não informado',
            'possui_transporte' => $evento->escolasAgendadas->contains('precisa_transporte', true),
            'totais' => [
                'escolas' => $escolasEvento->count(),
                'turmas' => $evento->alunos_snapshot_em
                    ? $evento->alunosSnapshot()->when($escolasIds !== null, fn ($q) => $q->whereIn('escola_id', $escolasIds))->whereNotNull('turma_id')->distinct()->count('turma_id')
                    : Turma::query()->whereIn('id_escola', $escolasEvento->pluck('id'))->count(),
                'participantes' => $participantes,
                'alunos' => $alunos,
            ],
        ];
    }

    /** @return array{items: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int, ultima_pagina: int} */
    public function participantes(User $user, int $eventoId, string $contexto, string $busca, int $pagina, int $porPagina): array
    {
        $evento = $this->autorizar($user, $eventoId, $contexto);
        $escolasIds = $this->escolasVisiveis($user, $contexto);

        if ($evento->participantes_snapshot_em) {
            $query = EventoCalendarioParticipanteSnapshot::query()
                ->where('evento_calendario_id', $eventoId)
                ->when($busca !== '', fn (Builder $q): Builder => $q->where('nome', 'like', '%'.$busca.'%'))
                ->orderBy('escola_nome')->orderBy('cargo_nome')->orderBy('nome');
            $this->aplicarEscopoParticipantes($query, $escolasIds);
            $total = (clone $query)->count();
            $pagina = $this->paginaValida($pagina, $porPagina, $total);
            $items = $query->forPage($pagina, $porPagina)->get()->map(fn ($item): array => [
                'nome' => $item->nome,
                'escola' => $item->escola_nome ?: 'Sem escola',
                'cargo' => $item->cargo_nome ?: 'Sem cargo',
                'turno' => $item->turno ?: 'Não informado',
            ])->all();

            return $this->paginado($items, $total, $pagina, $porPagina);
        }

        $items = $this->publico->destinatarios($evento)
            ->when($escolasIds !== null, fn (Collection $usuarios) => $usuarios->filter(
                fn (User $u): bool => array_intersect($u->idsEscolasVinculadas(), $escolasIds) !== [],
            ))
            ->filter(fn (User $u): bool => $busca === '' || str_contains(mb_strtolower($u->name), mb_strtolower($busca)));
        $total = $items->count();
        $pagina = $this->paginaValida($pagina, $porPagina, $total);
        $items = $items->forPage($pagina, $porPagina)->map(fn (User $u): array => [
            'nome' => $u->name,
            'escola' => $u->escola?->nome ?? 'Sem escola',
            'cargo' => $u->servidores->flatMap->funcoesAtivas->pluck('nome')->first() ?? 'Sem cargo',
            'turno' => 'Não informado',
        ])->values()->all();

        return $this->paginado($items, $total, $pagina, $porPagina);
    }

    /** @return array{items: list<array<string, mixed>>, total: int, legado: bool} */
    public function alunos(User $user, int $eventoId, string $contexto, string $busca, int $limite): array
    {
        $evento = $this->autorizar($user, $eventoId, $contexto);
        $escolasIds = $this->escolasVisiveis($user, $contexto);
        $transporte = $this->transporte($evento, $escolasIds);

        if ($evento->alunos_snapshot_em) {
            $query = EventoCalendarioAlunoSnapshot::query()
                ->where('evento_calendario_id', $eventoId)
                ->when($escolasIds !== null, fn (Builder $q): Builder => $q->whereIn('escola_id', $escolasIds))
                ->when($busca !== '', fn (Builder $q): Builder => $q->where(function (Builder $filtro) use ($busca): void {
                    $filtro->where('aluno_nome', 'like', '%'.$busca.'%')->orWhere('cgm', 'like', '%'.$busca.'%');
                }))
                ->orderBy('escola_nome')->orderBy('serie_nome')->orderBy('turma_nome')->orderBy('aluno_nome');
            $total = (clone $query)->count();
            $items = $query->limit($limite)->get()->map(fn ($item): array => [
                'nome' => $item->aluno_nome, 'cgm' => $item->cgm, 'escola' => $item->escola_nome,
                'serie' => $item->serie_nome, 'turma' => $item->turma_nome, 'turno' => $item->turno,
            ])->all();

            return compact('items', 'total', 'transporte') + ['legado' => false];
        }

        $agendamentos = $evento->escolasAgendadas()
            ->where('precisa_transporte', true)
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('escola_id', $escolasIds))
            ->with(['series:id', 'turmas:id'])->get();
        $query = $this->queryAlunosDoEscopo($agendamentos)
            ->when($busca !== '', fn (Builder $q): Builder => $q->where(function (Builder $filtro) use ($busca): void {
                $filtro->where('alunos.nome', 'like', '%'.$busca.'%')->orWhere('alunos.cgm', 'like', '%'.$busca.'%');
            }));
        $total = (clone $query)->reorder()->count('alunos.id');
        $items = $query->limit($limite)->get()->map(fn ($item): array => [
            'nome' => $item->nome, 'cgm' => $item->cgm, 'escola' => $item->escola_nome,
            'serie' => $item->serie_nome, 'turma' => $item->turma_nome, 'turno' => $item->turno,
        ])->all();

        return compact('items', 'total', 'transporte') + ['legado' => true];
    }

    /** @return array{items: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int, ultima_pagina: int} */
    public function escolas(User $user, int $eventoId, string $contexto, int $pagina, int $porPagina): array
    {
        $evento = $this->autorizar($user, $eventoId, $contexto);
        $escolasIds = $this->escolasVisiveis($user, $contexto);
        $agendamentos = $evento->escolasAgendadas()
            ->when($escolasIds !== null, fn ($q) => $q->whereIn('escola_id', $escolasIds))
            ->with(['escola:id,nome', 'series:id,nome', 'turmas:id,nome,id_escola,id_serie,turno', 'turmas.serie:id,nome'])
            ->orderBy('escola_id')->get();
        if ($agendamentos->isEmpty() && $evento->enviar_todas_escolas) {
            $evento->load('publicoAlvo.escolas:id,nome');
            $escolas = $evento->publicoAlvo?->escolas ?? collect();
            if ($escolasIds !== null) {
                $escolas = $escolas->whereIn('id', $escolasIds);
            }
            $turmasPorEscola = Turma::query()->whereIn('id_escola', $escolas->modelKeys())
                ->with('serie:id,nome')->orderBy('id_serie')->orderBy('nome')->get()->groupBy('id_escola');

            $linhas = $escolas->flatMap(fn ($escola) => $turmasPorEscola->get($escola->id, collect())->map(fn ($turma): array => [
                'escola' => $escola->nome,
                'serie' => $turma->serie?->nome ?? 'Não informada',
                'turma' => $turma->nome,
                'turno' => $this->turno($turma->turno),
                'quantidade_alunos' => null,
            ]))->values();

            return $this->paginarColecao($linhas, $pagina, $porPagina);
        }
        $turmasPorEscola = Turma::query()
            ->whereIn('id_escola', $agendamentos->pluck('escola_id'))
            ->with('serie:id,nome')
            ->orderBy('id_serie')->orderBy('nome')->get()->groupBy('id_escola');

        $quantidades = $evento->alunos_snapshot_em
            ? $evento->alunosSnapshot()
                ->when($escolasIds !== null, fn ($query) => $query->whereIn('escola_id', $escolasIds))
                ->selectRaw('turma_id, count(*) as total')
                ->whereNotNull('turma_id')
                ->groupBy('turma_id')
                ->pluck('total', 'turma_id')
            : collect();
        $linhas = $agendamentos->flatMap(function ($item) use ($turmasPorEscola, $quantidades): \Illuminate\Support\Collection {
            $turmas = $turmasPorEscola->get($item->escola_id, collect());
            if ($item->escopo_transporte === EventoCalendarioTransporteEscopo::SERIES && $item->series->isNotEmpty()) {
                $turmas = $turmas->whereIn('id_serie', $item->series->modelKeys());
            } elseif ($item->escopo_transporte === EventoCalendarioTransporteEscopo::TURMAS && $item->turmas->isNotEmpty()) {
                $turmas = $turmas->whereIn('id', $item->turmas->modelKeys());
            }

            return $turmas->map(fn ($turma): array => [
                'escola' => $item->escola?->nome ?? 'Escola não informada',
                'serie' => $turma->serie?->nome ?? 'Não informada',
                'turma' => $turma->nome,
                'turno' => $this->turno($turma->turno),
                'quantidade_alunos' => $item->precisa_transporte ? (int) ($quantidades[$turma->id] ?? 0) : null,
            ]);
        })->sortBy(['escola', 'serie', 'turma'])->values();

        return $this->paginarColecao($linhas, $pagina, $porPagina);
    }

    private function autorizar(User $user, int $eventoId, string $contexto): EventoCalendario
    {
        abort_unless(in_array($contexto, ['pessoal', 'rede'], true), 422);
        $evento = EventoCalendario::query()->findOrFail($eventoId);
        Gate::forUser($user)->authorize('view', $evento);

        if ($contexto === 'rede') {
            return $evento;
        }

        $agora = CarbonImmutable::now();
        $calendarContext = new CalendarQueryContext(
            user: $user,
            userContext: $this->contextos->make($user),
            inicio: $agora->startOfDay(),
            fim: $agora->endOfDay(),
            redeCompleta: $contexto === 'rede',
        );
        abort_unless($this->eventos->detail($calendarContext, (string) $eventoId), 404);

        return $evento;
    }

    /** @return list<int>|null */
    private function escolasVisiveis(User $user, string $contexto): ?array
    {
        return $contexto === 'rede' ? null : $this->contextos->make($user)->escolaIds;
    }

    private function queryAlunosDoEscopo(Collection $agendamentos): Builder
    {
        $query = Aluno::query()
            ->join('turmas', 'turmas.id', '=', 'alunos.id_turma')
            ->join('escolas', 'escolas.id', '=', 'turmas.id_escola')
            ->leftJoin('series', 'series.id', '=', 'turmas.id_serie')
            ->where('alunos.tipo_vinculo', Aluno::TIPO_VINCULO_PRINCIPAL)
            ->where('alunos.status', Aluno::STATUS_MATRICULADO)
            ->where(function (Builder $selecao) use ($agendamentos): void {
                foreach ($agendamentos as $agendamento) {
                    $selecao->orWhere(function (Builder $escola) use ($agendamento): void {
                        $escola->where('turmas.id_escola', $agendamento->escola_id);
                        if ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::SERIES) {
                            $escola->whereIn('turmas.id_serie', $agendamento->series->modelKeys());
                        } elseif ($agendamento->escopo_transporte === EventoCalendarioTransporteEscopo::TURMAS) {
                            $escola->whereIn('turmas.id', $agendamento->turmas->modelKeys());
                        }
                    });
                }
            })
            ->select(['alunos.id', 'alunos.nome', 'alunos.cgm', 'turmas.nome as turma_nome', 'turmas.turno', 'escolas.nome as escola_nome', 'series.nome as serie_nome'])
            ->orderBy('escolas.nome')->orderBy('series.nome')->orderBy('turmas.nome')->orderBy('alunos.nome');

        return $agendamentos->isEmpty() ? $query->whereRaw('1 = 0') : $query;
    }

    /** @param list<int>|null $escolasIds */
    private function aplicarEscopoParticipantes(Builder|HasMany $query, ?array $escolasIds): Builder|HasMany
    {
        if ($escolasIds === null) {
            return $query;
        }

        return $query->where(function ($escopo) use ($escolasIds): void {
            $escopo->whereIn('escola_id', $escolasIds);
            foreach ($escolasIds as $escolaId) {
                $escopo->orWhereJsonContains('escola_ids', $escolaId);
            }
        });
    }

    /** @param list<int>|null $escolasIds @return array{status: string, alocacoes: list<array<string, string>>} */
    private function transporte(EventoCalendario $evento, ?array $escolasIds): array
    {
        $alocacoes = $evento->alocacoesTransporteAtivas()
            ->when($escolasIds !== null, fn ($q) => $q->whereHas(
                'turmas',
                fn ($turmas) => $turmas->whereIn('turmas.id_escola', $escolasIds),
            ))
            ->with(['veiculo:id,placa,identificacao', 'motorista:id,nome', 'turmas:id,nome,id_escola'])
            ->get()
            ->map(fn ($item): array => [
                'veiculo' => $item->veiculo?->identificacao ?: ($item->veiculo?->placa ?? 'Veículo não informado'),
                'motorista' => $item->motoristaNomeExibicao(),
                'turmas' => $item->turmas->pluck('nome')->join(', '),
            ])->values()->all();

        return ['status' => $evento->status?->label() ?? 'Não informado', 'alocacoes' => $alocacoes];
    }

    /** @param list<array<string, mixed>> $items @return array{items: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int, ultima_pagina: int} */
    private function paginado(array $items, int $total, int $pagina, int $porPagina): array
    {
        return [
            'items' => $items,
            'total' => $total,
            'pagina' => $pagina,
            'por_pagina' => $porPagina,
            'ultima_pagina' => max(1, (int) ceil($total / $porPagina)),
        ];
    }

    /** @param Collection<int, array<string, mixed>> $items @return array{items: list<array<string, mixed>>, total: int, pagina: int, por_pagina: int, ultima_pagina: int} */
    private function paginarColecao(Collection $items, int $pagina, int $porPagina): array
    {
        $total = $items->count();
        $pagina = $this->paginaValida($pagina, $porPagina, $total);

        return $this->paginado(
            $items->forPage($pagina, $porPagina)->values()->all(),
            $total,
            $pagina,
            $porPagina,
        );
    }

    private function paginaValida(int $pagina, int $porPagina, int $total): int
    {
        $porPagina = in_array($porPagina, [5, 10, 25, 50], true) ? $porPagina : 10;

        return min(max(1, $pagina), max(1, (int) ceil($total / $porPagina)));
    }

    private function turno(?string $turno): string
    {
        return match ($turno) {
            'manha' => 'Manhã',
            'tarde' => 'Tarde',
            'noite' => 'Noite',
            'integral' => 'Integral',
            default => 'Não informado',
        };
    }
}
