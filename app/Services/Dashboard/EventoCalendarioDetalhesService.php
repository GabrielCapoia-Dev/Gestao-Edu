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
            'criadoPor:id,name',
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
            'criado_por' => $evento->criadoPor?->name ?? 'Usuário não informado',
            'criado_em' => $evento->created_at?->format('d/m/Y H:i'),
            'possui_transporte' => $evento->escolasAgendadas->contains('precisa_transporte', true),
            'totais' => [
                'escolas' => $escolasEvento->count(),
                'turmas' => $evento->alunos_snapshot_em
                    ? $evento->alunosSnapshot()->when($escolasIds !== null, fn ($q) => $q->whereIn('escola_id', $escolasIds))->whereNotNull('turma_id')->distinct()->count('turma_id')
                    : Turma::query()->whereIn('id_escola', $escolasEvento->pluck('id'))->count(),
                'participantes' => $participantes,
                'alunos' => $alunos,
            ],
            'snapshot_legado' => ! $evento->participantes_snapshot_em || ! $evento->alunos_snapshot_em,
        ];
    }

    /** @return array{items: list<array<string, mixed>>, total: int, legado: bool} */
    public function participantes(User $user, int $eventoId, string $contexto, string $busca, int $limite): array
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
            $items = $query->limit($limite)->get()->map(fn ($item): array => [
                'nome' => $item->nome,
                'email' => $item->email,
                'escola' => $item->escola_nome ?: 'Sem escola',
                'cargo' => $item->cargo_nome ?: 'Sem cargo',
            ])->all();

            return compact('items', 'total') + ['legado' => false];
        }

        $items = $this->publico->destinatarios($evento)
            ->when($escolasIds !== null, fn (Collection $usuarios) => $usuarios->filter(
                fn (User $u): bool => array_intersect($u->idsEscolasVinculadas(), $escolasIds) !== [],
            ))
            ->filter(fn (User $u): bool => $busca === '' || str_contains(mb_strtolower($u->name), mb_strtolower($busca)));
        $total = $items->count();
        $items = $items->take($limite)->map(fn (User $u): array => [
            'nome' => $u->name,
            'email' => $u->email,
            'escola' => $u->escola?->nome ?? 'Sem escola',
            'cargo' => $u->servidores->flatMap->funcoesAtivas->pluck('nome')->first() ?? 'Sem cargo',
        ])->values()->all();

        return compact('items', 'total') + ['legado' => true];
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

    /** @return list<array<string, mixed>> */
    public function escolas(User $user, int $eventoId, string $contexto): array
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

            return $escolas->map(fn ($escola): array => [
                'nome' => $escola->nome,
                'horario' => $evento->data_inicio->format('H:i').'–'.$evento->data_fim->format('H:i'),
                'precisa_transporte' => false,
                'estimativa' => 0,
                'series' => $turmasPorEscola->get($escola->id, collect())->pluck('serie.nome')->filter()->unique()->values()->all(),
                'turmas' => $turmasPorEscola->get($escola->id, collect())->map(fn ($turma): array => [
                    'nome' => $turma->nome, 'serie' => $turma->serie?->nome, 'turno' => $turma->turno,
                ])->values()->all(),
            ])->values()->all();
        }
        $turmasPorEscola = Turma::query()
            ->whereIn('id_escola', $agendamentos->pluck('escola_id'))
            ->with('serie:id,nome')
            ->orderBy('id_serie')->orderBy('nome')->get()->groupBy('id_escola');

        return $agendamentos->map(function ($item) use ($turmasPorEscola): array {
            $turmas = $turmasPorEscola->get($item->escola_id, collect());
            if ($item->escopo_transporte === EventoCalendarioTransporteEscopo::SERIES && $item->series->isNotEmpty()) {
                $turmas = $turmas->whereIn('id_serie', $item->series->modelKeys());
            } elseif ($item->escopo_transporte === EventoCalendarioTransporteEscopo::TURMAS && $item->turmas->isNotEmpty()) {
                $turmas = $turmas->whereIn('id', $item->turmas->modelKeys());
            }

            return [
                'nome' => $item->escola?->nome ?? 'Escola não informada',
                'horario' => substr((string) $item->hora_inicio, 0, 5).'–'.substr((string) $item->hora_fim, 0, 5),
                'precisa_transporte' => (bool) $item->precisa_transporte,
                'estimativa' => (int) ($item->quantidade_estimada_transporte ?? 0),
                'series' => $turmas->pluck('serie.nome')->filter()->unique()->values()->all(),
                'turmas' => $turmas->map(fn ($turma): array => [
                'nome' => $turma->nome, 'serie' => $turma->serie?->nome, 'turno' => $turma->turno,
                ])->values()->all(),
            ];
        })->values()->all();
    }

    /** @return list<array<string, mixed>> */
    public function historico(User $user, int $eventoId, string $contexto): array
    {
        $evento = $this->autorizar($user, $eventoId, $contexto);

        return $evento->historicos()->with('usuario:id,name')->limit(100)->get()->map(fn ($item): array => [
            'acao' => $item->acao?->label() ?? 'Atualização',
            'usuario' => $item->usuario?->name ?? 'Sistema',
            'data' => $item->created_at?->format('d/m/Y H:i'),
            'motivo' => $item->motivo,
        ])->all();
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
}
