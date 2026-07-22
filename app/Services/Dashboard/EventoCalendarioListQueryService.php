<?php

namespace App\Services\Dashboard;

use App\Models\Enums\EventoCalendarioStatus;
use App\Models\Escola;
use App\Models\EventoCalendario;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class EventoCalendarioListQueryService
{
    public function __construct(
        private readonly EventoCalendarioAccessService $access,
    ) {}

    public function query(User $user): Builder
    {
        $inversaoFila = DB::table('eventos_calendario as evento_anterior')
            ->selectRaw('1')
            ->whereNull('evento_anterior.deleted_at')
            ->whereExists(function ($transporteAtual): void {
                $transporteAtual
                    ->selectRaw('1')
                    ->from('evento_calendario_escolas as escola_evento_atual')
                    ->whereColumn(
                        'escola_evento_atual.evento_calendario_id',
                        'eventos_calendario.id',
                    )
                    ->where('escola_evento_atual.precisa_transporte', true);
            })
            ->whereExists(function ($transporteAnterior): void {
                $transporteAnterior
                    ->selectRaw('1')
                    ->from('evento_calendario_escolas as escola_evento_anterior')
                    ->whereColumn(
                        'escola_evento_anterior.evento_calendario_id',
                        'evento_anterior.id',
                    )
                    ->where('escola_evento_anterior.precisa_transporte', true);
            })
            ->whereColumn(
                'evento_anterior.data_inicio',
                '>',
                'eventos_calendario.data_inicio',
            )
            ->where(function ($ordemCriacao): void {
                $ordemCriacao
                    ->whereColumn(
                        'evento_anterior.created_at',
                        '<',
                        'eventos_calendario.created_at',
                    )
                    ->orWhere(function ($desempate): void {
                        $desempate
                            ->whereColumn(
                                'evento_anterior.created_at',
                                'eventos_calendario.created_at',
                            )
                            ->whereColumn(
                                'evento_anterior.id',
                                '<',
                                'eventos_calendario.id',
                            );
                    });
            })
            ->limit(1);

        if (! $this->access->podeListarGeral($user) && ! $this->access->podeListarTransporte($user)) {
            $inversaoFila->where('evento_anterior.criado_por_id', $user->getKey());
        }

        $query = EventoCalendario::query()
            ->with([
                'criadoPor:id,name,email',
                'escolasResumo' => function (HasMany $escolas): void {
                    $escolas->select([
                        'evento_calendario_escolas.id',
                        'evento_calendario_escolas.evento_calendario_id',
                        'evento_calendario_escolas.escola_id',
                        'evento_calendario_escolas.precisa_transporte',
                        'evento_calendario_escolas.quantidade_estimada_transporte',
                    ])
                        ->with('escola:id,nome')
                        ->orderBy('evento_calendario_escolas.id')
                        ->limit(2);
                },
            ])
            ->withCount('escolasAgendadas')
            ->withExists([
                'escolasAgendadas as possui_transporte' => fn (Builder $escolas): Builder => $escolas
                    ->where('precisa_transporte', true),
            ])
            ->withSum([
                'escolasAgendadas as total_estudantes_transporte' => fn (Builder $escolas): Builder => $escolas
                    ->where('precisa_transporte', true),
            ], 'quantidade_estimada_transporte')
            ->addSelect([
                'escolas_publico_count' => DB::table('publico_alvo_escola as publico_escolas')
                    ->selectRaw('COUNT(*)')
                    ->whereColumn(
                        'publico_escolas.publico_alvo_id',
                        'eventos_calendario.publico_alvo_id',
                    ),
                'possui_inversao_fila' => $inversaoFila,
            ]);

        return $this->access->aplicarEscopo($user, $query);
    }

    public function detalhes(User $user, int $id): EventoCalendario
    {
        $evento = $this->access
            ->aplicarEscopo($user, EventoCalendario::query())
            ->findOrFail($id);

        Gate::forUser($user)->authorize('view', $evento);

        return $evento->load([
            'criadoPor:id,name,email',
            'atualizadoPor:id,name,email',
            'escola:id,nome',
            'publicoAlvo.escolas:id,nome',
            'escolasAgendadas' => function (HasMany $escolas): void {
                $escolas->orderBy('evento_calendario_escolas.escola_id');
            },
            'escolasAgendadas.escola:id,nome',
            'escolasAgendadas.series:id,nome',
            'escolasAgendadas.turmas:id,nome,id_escola,id_serie,turno',
            'historicos.usuario:id,name,email',
        ]);
    }

    /**
     * @return array{pendentes: int, publicados: int, rejeitados: int, total_transporte: int}
     */
    public function indicadores(Builder $query): array
    {
        $eventos = clone $query;
        $eventos->setEagerLoads([]);
        $eventos->reorder();
        $eventos
            ->select([
                'eventos_calendario.id',
                'eventos_calendario.status',
            ])
            ->withExists([
                'escolasAgendadas as possui_transporte' => fn (Builder $escolas): Builder => $escolas
                    ->where('precisa_transporte', true),
            ]);

        $resumo = DB::query()
            ->fromSub($eventos->toBase(), 'eventos_filtrados')
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN possui_transporte = 1 AND status = ? THEN 1 ELSE 0 END), 0) AS pendentes',
                [EventoCalendarioStatus::PENDENTE_APROVACAO->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN possui_transporte = 1 AND status = ? THEN 1 ELSE 0 END), 0) AS publicados',
                [EventoCalendarioStatus::PUBLICADO->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN possui_transporte = 1 AND status = ? THEN 1 ELSE 0 END), 0) AS rejeitados',
                [EventoCalendarioStatus::REJEITADO->value],
            )
            ->selectRaw(
                'COALESCE(SUM(CASE WHEN possui_transporte = 1 THEN 1 ELSE 0 END), 0) AS total_transporte',
            )
            ->first();

        return [
            'pendentes' => (int) ($resumo->pendentes ?? 0),
            'publicados' => (int) ($resumo->publicados ?? 0),
            'rejeitados' => (int) ($resumo->rejeitados ?? 0),
            'total_transporte' => (int) ($resumo->total_transporte ?? 0),
        ];
    }

    /** @return array<int, string> */
    public function schoolOptions(User $user): array
    {
        $eventosVisiveis = $this->access->aplicarEscopo($user, EventoCalendario::query());

        $eventosEspecificos = (clone $eventosVisiveis)
            ->where('eventos_calendario.enviar_todas_escolas', false)
            ->select('eventos_calendario.id');

        $publicosAbrangentes = (clone $eventosVisiveis)
            ->where('eventos_calendario.enviar_todas_escolas', true)
            ->select('eventos_calendario.publico_alvo_id');

        return Escola::query()
            ->where('ativo', true)
            ->where(function (Builder $escolas) use ($eventosEspecificos, $publicosAbrangentes): void {
                $escolas
                    ->whereIn(
                        'escolas.id',
                        DB::table('evento_calendario_escolas')
                            ->select('escola_id')
                            ->whereIn('evento_calendario_id', $eventosEspecificos),
                    )
                    ->orWhereIn(
                        'escolas.id',
                        DB::table('publico_alvo_escola')
                            ->select('escola_id')
                            ->whereIn('publico_alvo_id', $publicosAbrangentes),
                    );
            })
            ->orderBy('escolas.nome')
            ->orderBy('escolas.id')
            ->pluck('escolas.nome', 'escolas.id')
            ->mapWithKeys(fn (string $nome, int|string $id): array => [(int) $id => $nome])
            ->all();
    }

    /** @return array<int, string> */
    public function creatorOptions(User $user): array
    {
        $criadoresVisiveis = $this->access
            ->aplicarEscopo($user, EventoCalendario::query())
            ->whereNotNull('eventos_calendario.criado_por_id')
            ->select('eventos_calendario.criado_por_id');

        return User::query()
            ->whereIn('users.id', $criadoresVisiveis)
            ->orderBy('users.name')
            ->orderBy('users.id')
            ->pluck('users.name', 'users.id')
            ->mapWithKeys(fn (string $nome, int|string $id): array => [(int) $id => $nome])
            ->all();
    }

    public function applySchoolFilter(Builder $query, int $schoolId): Builder
    {
        if ($schoolId <= 0) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $eventos) use ($schoolId): void {
            $eventos
                ->whereHas(
                    'escolasAgendadas',
                    fn (Builder $escolas): Builder => $escolas->where('escola_id', $schoolId),
                )
                ->orWhere(function (Builder $abrangentes) use ($schoolId): void {
                    $abrangentes
                        ->where('eventos_calendario.enviar_todas_escolas', true)
                        ->whereHas(
                            'publicoAlvo.escolas',
                            fn (Builder $escolas): Builder => $escolas->whereKey($schoolId),
                        );
                });
        });
    }
}
