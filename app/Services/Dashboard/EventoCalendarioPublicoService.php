<?php

namespace App\Services\Dashboard;

use App\Models\EventoCalendario;
use App\Models\FuncaoAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final class EventoCalendarioPublicoService
{
    public function aplicarEscopo(Builder $query, User $usuario): Builder
    {
        $escolas = $usuario->idsEscolasVinculadas();
        $funcoes = $usuario->servidores()->with('funcoesAtivas:id')->get()
            ->flatMap->funcoesAtivas
            ->pluck('id')
            ->unique()
            ->values()
            ->all();
        $professores = $usuario->professores()->where('ativo', true)->get();
        $turnos = $professores->pluck('turno')->filter()->unique()->values()->all();
        $series = $professores->flatMap(fn ($professor) => $professor->turmas()->pluck('id_serie'))->filter()->unique()->values()->all();
        $componentes = $professores->flatMap(fn ($professor) => $professor->componentesPorTurma()->pluck('componente_curricular.id'))->filter()->unique()->values()->all();

        return $query->where(function (Builder $eventos) use ($escolas, $funcoes, $turnos, $series, $componentes): void {
            $eventos->where('eventos_calendario.publico_tipo', '!=', 'segmentado')
                ->orWhereHas('publicoRegras', function (Builder $regras) use ($escolas, $funcoes, $turnos, $series, $componentes): void {
                    $this->aplicarFiltroJson($regras, 'escola_ids', $escolas);
                    $this->aplicarFiltroJson($regras, 'funcao_ids', $funcoes);
                    $this->aplicarFiltroJson($regras, 'turnos', $turnos);
                    $this->aplicarFiltroJson($regras, 'serie_ids', $series);
                    $this->aplicarFiltroJson($regras, 'componente_ids', $componentes);
                });
        });
    }

    /** @param array<int, mixed> $regras @param array<int, mixed> $excecoes */
    public function sincronizar(EventoCalendario $evento, array $regras, array $excecoes, User $ator): void
    {
        $regras = $this->normalizarRegras($regras, $ator);
        $excecoes = collect($excecoes)
            ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $evento->publicoRegras()->delete();
        $evento->publicoExcecoes()->delete();

        foreach ($regras as $filtros) {
            $evento->publicoRegras()->create(['filtros' => $filtros]);
        }

        foreach ($excecoes as $userId) {
            $evento->publicoExcecoes()->create(['user_id' => $userId]);
        }
    }

    /** @return Collection<int, User> */
    public function destinatarios(EventoCalendario $evento): Collection
    {
        $evento->loadMissing(['publicoRegras', 'publicoExcecoes']);

        $usuarios = collect();

        foreach ($evento->publicoRegras as $regra) {
            $usuarios = $usuarios->merge($this->queryRegra($regra->filtros)
                ->with(['escola:id,nome', 'servidores.funcoesAtivas'])
                ->get());
        }

        $excluidos = $evento->publicoExcecoes->modelKeys();

        return $usuarios
            ->unique('id')
            ->reject(fn (User $usuario): bool => in_array((int) $usuario->id, $excluidos, true))
            ->sortBy('name')
            ->values();
    }

    /** @param array<int, mixed> $regras @param array<int, mixed> $excecoes @return Collection<int, User> */
    public function preview(User $ator, array $regras, array $excecoes = []): Collection
    {
        $usuarios = collect();

        foreach ($this->normalizarRegras($regras, $ator) as $filtros) {
            $usuarios = $usuarios->merge($this->queryRegra($filtros)
                ->with(['escola:id,nome', 'servidores.funcoesAtivas'])
                ->get());
        }

        $excluidos = collect($excecoes)->map(fn ($id): int => (int) $id)->all();

        return $usuarios->unique('id')
            ->reject(fn (User $usuario): bool => in_array((int) $usuario->id, $excluidos, true))
            ->sortBy('name')->values();
    }

    /** @return array<int, array{escola: string, cargos: array<int, array{cargo: string, usuarios: array<int, string>}>}> */
    public function resumo(EventoCalendario $evento): array
    {
        return $this->destinatarios($evento)
            ->groupBy(fn (User $usuario): string => (string) ($usuario->escola?->nome ?? 'Sem escola'))
            ->map(fn (Collection $usuarios): array => [
                'escola' => (string) ($usuarios->first()?->escola?->nome ?? 'Sem escola'),
                'cargos' => $usuarios->groupBy(fn (User $usuario): string => $this->cargo($usuario))
                    ->map(fn (Collection $grupo, string $cargo): array => [
                        'cargo' => $cargo,
                        'usuarios' => $grupo->pluck('name')->values()->all(),
                    ])->values()->all(),
            ])->values()->all();
    }

    /** @param array<int, mixed> $regras @return array<int, array<string, array<int, int|string>>> */
    private function normalizarRegras(array $regras, User $ator): array
    {
        $contexto = app(DashboardUserContextFactory::class)->make($ator);

        return collect($regras)->map(function ($regra) use ($contexto): array {
            $regra = is_array($regra) ? $regra : [];
            $filtros = [];

            foreach (['escola_ids', 'funcao_ids', 'serie_ids', 'componente_ids'] as $campo) {
                $filtros[$campo] = collect($regra[$campo] ?? [])
                    ->filter(fn ($id): bool => is_numeric($id) && (int) $id > 0)
                    ->map(fn ($id): int => (int) $id)
                    ->unique()->values()->all();
            }

            $filtros['turnos'] = collect($regra['turnos'] ?? [])
                ->map(fn ($turno): string => mb_strtolower(trim((string) $turno)))
                ->filter()->unique()->values()->all();

            if ($filtros['escola_ids'] !== [] && ! $contexto->escopoGlobal
                && array_diff($filtros['escola_ids'], $contexto->escolaIds) !== []) {
                throw ValidationException::withMessages([
                    'publico_regras' => 'Uma escola selecionada está fora do seu escopo de acesso.',
                ]);
            }

            if ($filtros['funcao_ids'] !== []) {
                $validas = FuncaoAdministrativa::query()->where('ativo', true)->whereKey($filtros['funcao_ids'])->count();
                if ($validas !== count($filtros['funcao_ids'])) {
                    throw ValidationException::withMessages([
                        'publico_regras' => 'Uma função administrativa selecionada está inválida ou inativa.',
                    ]);
                }
            }

            if ($filtros['escola_ids'] === [] && $filtros['funcao_ids'] === [] && $filtros['serie_ids'] === []
                && $filtros['componente_ids'] === [] && $filtros['turnos'] === []) {
                throw ValidationException::withMessages([
                    'publico_regras' => 'Cada grupo precisa ter ao menos um filtro.',
                ]);
            }

            return $filtros;
        })->values()->all();
    }

    /** @param array<string, mixed> $filtros */
    private function queryRegra(array $filtros): Builder
    {
        return User::query()
            ->whereNull('users.deleted_at')
            ->where(function (Builder $pessoas): void {
                $pessoas->whereHas('servidores', fn (Builder $servidores): Builder => $servidores->where('status', 'ativo'))
                    ->orWhereHas('servidores.professores', fn (Builder $professores): Builder => $professores->where('ativo', true));
            })
            ->when($filtros['escola_ids'] ?? [], function (Builder $query, array $ids): Builder {
                return $query->where(function (Builder $filtro) use ($ids): void {
                    $filtro->whereHas('escolas', fn (Builder $escolas): Builder => $escolas->whereKey($ids))
                        ->orWhereIn('users.id_escola', $ids)
                        ->orWhereHas('servidores.vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos->whereIn('id_escola', $ids))
                        ->orWhereHas('servidores.professores', fn (Builder $professores): Builder => $professores->where('ativo', true)->whereIn('id_escola', $ids));
                });
            })
            ->when($filtros['funcao_ids'] ?? [], function (Builder $query, array $ids): Builder {
                return $query->where(function (Builder $filtro) use ($ids): void {
                    $filtro->whereHas('servidores.vinculosAtivos', fn (Builder $vinculos): Builder => $vinculos->whereIn('funcao_administrativa_id', $ids))
                        ->orWhereHas('servidores.professores', fn (Builder $professores): Builder => $professores->where('ativo', true)->whereHas('vinculoFuncional', fn (Builder $vinculo): Builder => $vinculo->whereIn('funcao_administrativa_id', $ids)->where('status', 'ativo')));
                });
            })
            ->when($filtros['turnos'] ?? [], fn (Builder $query, array $turnos): Builder => $query->whereHas('servidores.professores', fn (Builder $professores): Builder => $professores->where('ativo', true)->whereIn('turno', $turnos)))
            ->when($filtros['serie_ids'] ?? [], fn (Builder $query, array $ids): Builder => $query->whereHas('servidores.professores', fn (Builder $professores): Builder => $professores->where('ativo', true)->whereHas('turmas', fn (Builder $turmas): Builder => $turmas->whereIn('id_serie', $ids))))
            ->when($filtros['componente_ids'] ?? [], fn (Builder $query, array $ids): Builder => $query->whereHas('servidores.professores', fn (Builder $professores): Builder => $professores->where('ativo', true)->whereHas('componentesPorTurma', fn (Builder $componentes): Builder => $componentes->whereIn('componente_curricular.id', $ids))));
    }

    private function cargo(User $usuario): string
    {
        $usuario->loadMissing('servidores.funcoesAtivas');

        return (string) ($usuario->servidores->flatMap->funcoesAtivas->pluck('nome')->first() ?? 'Sem cargo');
    }

    /** @param array<int, int|string> $ids */
    private function aplicarFiltroJson(Builder $query, string $campo, array $ids): void
    {
        $query->where(function (Builder $filtro) use ($campo, $ids): void {
            $filtro->whereJsonLength("filtros->{$campo}", 0);
            foreach ($ids as $id) {
                $filtro->orWhereJsonContains("filtros->{$campo}", $id);
            }
        });
    }
}
