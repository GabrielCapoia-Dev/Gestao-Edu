<?php

namespace App\Services\Dashboard;

use App\Models\Enums\ReservaVeiculoStatus;
use App\Models\Escola;
use App\Models\ReservaVeiculo;
use App\Models\User;
use App\Models\VeiculoTransporte;
use App\Services\PessoaScopeService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ReservaVeiculoService
{
    public function __construct(private readonly PessoaScopeService $scope) {}

    public function query(User $ator): Builder
    {
        Gate::forUser($ator)->authorize('viewAny', ReservaVeiculo::class);
        ReservaVeiculo::concluirExpiradas();

        $query = ReservaVeiculo::query()
            ->with([
                'veiculo:id,placa,identificacao,ativo',
                'usuario:id,name,email',
                'escola:id,nome',
                'escolas:id,nome',
                'canceladoPor:id,name',
            ])
            ->orderByDesc('data_inicio');

        return $this->aplicarEscopoEscolas($query, $ator);
    }

    /** @return Collection<int, ReservaVeiculo> */
    public function criarEmLote(User $ator, array $dados): Collection
    {
        Gate::forUser($ator)->authorize('create', ReservaVeiculo::class);
        $dados = $this->validarCriacao($dados);
        $periodos = $this->periodos(
            $this->datasDoIntervalo($dados['data_inicial'], $dados['data_final']),
            $dados['hora_inicio'],
            $dados['hora_fim'],
        );
        $this->validarPeriodosFuturos($periodos);

        return DB::transaction(function () use ($ator, $dados, $periodos): Collection {
            $veiculo = VeiculoTransporte::query()
                ->ativos()
                ->lockForUpdate()
                ->findOrFail($dados['veiculo_transporte_id']);

            $this->validarDisponibilidade($veiculo->id, $periodos);
            [$escolaId, $localNome, $escolaIds] = $this->resolverLocal($dados, $ator);
            $grupo = count($periodos) > 1 ? (string) Str::uuid() : null;

            return collect($periodos)->map(function (array $periodo) use ($escolaId, $localNome, $escolaIds, $ator, $dados, $veiculo, $grupo): ReservaVeiculo {
                $reserva = ReservaVeiculo::query()->create([
                'veiculo_transporte_id' => $veiculo->id,
                'usuario_id' => $ator->id,
                'escola_id' => $escolaId,
                'local_nome' => $localNome,
                'atividade' => $dados['atividade'],
                'data_inicio' => $periodo['inicio'],
                'data_fim' => $periodo['fim'],
                'status' => ReservaVeiculoStatus::ATIVA,
                'grupo_recorrencia' => $grupo,
                'criado_por_id' => $ator->id,
                'atualizado_por_id' => $ator->id,
                ]);
                if ($escolaIds !== []) {
                    $reserva->escolas()->sync($escolaIds);
                }
                return $reserva;
            });
        });
    }

    public function atualizar(User $ator, ReservaVeiculo $reserva, array $dados): ReservaVeiculo
    {
        $dados = $this->validarEdicao($dados);
        $periodo = $this->periodos([$dados['data']], $dados['hora_inicio'], $dados['hora_fim']);
        $this->validarPeriodosFuturos($periodo);

        return DB::transaction(function () use ($ator, $reserva, $dados, $periodo): ReservaVeiculo {
            $reserva = ReservaVeiculo::query()->lockForUpdate()->findOrFail($reserva->id);
            Gate::forUser($ator)->authorize('update', $reserva);

            VeiculoTransporte::query()
                ->whereIn('id', collect([
                    $reserva->veiculo_transporte_id,
                    $dados['veiculo_transporte_id'],
                ])->unique()->sort()->values())
                ->lockForUpdate()
                ->get();

            $veiculo = VeiculoTransporte::query()
                ->ativos()
                ->find($dados['veiculo_transporte_id']);

            if (! $veiculo) {
                throw ValidationException::withMessages([
                    'veiculo_transporte_id' => 'O veículo selecionado não está ativo.',
                ]);
            }

            $this->validarDisponibilidade($veiculo->id, $periodo, $reserva->id);
            [$escolaId, $localNome, $escolaIds] = $this->resolverLocal($dados, $ator);

            $reserva->fill([
                'veiculo_transporte_id' => $veiculo->id,
                'escola_id' => $escolaId,
                'local_nome' => $localNome,
                'atividade' => $dados['atividade'],
                'data_inicio' => $periodo[0]['inicio'],
                'data_fim' => $periodo[0]['fim'],
                'atualizado_por_id' => $ator->id,
            ])->save();
            $reserva->escolas()->sync($escolaIds);

            return $reserva->fresh(['veiculo', 'usuario', 'escola']);
        });
    }

    public function cancelar(
        User $ator,
        ReservaVeiculo $reserva,
        ?string $motivo = null,
    ): ReservaVeiculo {
        return DB::transaction(function () use ($ator, $reserva, $motivo): ReservaVeiculo {
            $reserva = ReservaVeiculo::query()->lockForUpdate()->findOrFail($reserva->id);
            Gate::forUser($ator)->authorize('cancel', $reserva);

            $reserva->forceFill([
                'status' => ReservaVeiculoStatus::CANCELADA,
                'cancelado_por_id' => $ator->id,
                'cancelado_em' => now(),
                'motivo_cancelamento' => filled($motivo)
                    ? Str::of((string) $motivo)->squish()->limit(500)->toString()
                    : null,
                'atualizado_por_id' => $ator->id,
            ])->save();

            return $reserva->fresh();
        });
    }

    /** @return array<int, string> */
    public function veiculosDisponiveis(
        User $ator,
        ?string $dataInicial,
        ?string $dataFinal,
        ?string $horaInicio,
        ?string $horaFim,
        ?int $ignorarReservaId = null,
    ): array {
        Gate::forUser($ator)->authorize('viewAny', ReservaVeiculo::class);
        ReservaVeiculo::concluirExpiradas();

        if (blank($dataInicial) || blank($dataFinal) || blank($horaInicio) || blank($horaFim)) {
            return [];
        }

        try {
            $periodos = $this->periodos(
                $this->datasDoIntervalo($dataInicial, $dataFinal),
                $horaInicio,
                $horaFim,
            );
        } catch (\Throwable) {
            return [];
        }

        return VeiculoTransporte::query()
            ->ativos()
            ->where(function (Builder $veiculos) use ($periodos, $ignorarReservaId): void {
                foreach ($periodos as $periodo) {
                    $veiculos->whereDoesntHave(
                        'reservasAtivas',
                        fn (Builder $reservas): Builder => $this->aplicarConflito(
                            $reservas,
                            $periodo['inicio'],
                            $periodo['fim'],
                            $ignorarReservaId,
                        ),
                    );
                }
            })
            ->orderBy('identificacao')
            ->orderBy('placa')
            ->get()
            ->mapWithKeys(fn (VeiculoTransporte $veiculo): array => [
                $veiculo->id => $this->rotuloVeiculo($veiculo),
            ])
            ->all();
    }

    /** @return Collection<int, ReservaVeiculo> */
    public function proximas(User $ator, int $limite = 4): Collection
    {
        Gate::forUser($ator)->authorize('viewAny', ReservaVeiculo::class);
        ReservaVeiculo::concluirExpiradas();

        $query = ReservaVeiculo::query()
            ->ativas()
            ->where('data_fim', '>=', now())
            ->with(['veiculo:id,placa,identificacao', 'usuario:id,name', 'escola:id,nome'])
            ->orderBy('data_inicio')
            ->limit(max(1, min($limite, 12)));

        return $this->aplicarEscopoEscolas($query, $ator)->get();
    }

    /** @return array<string, mixed> */
    private function validarCriacao(array $dados): array
    {
        if (! array_key_exists('escola_ids', $dados) && array_key_exists('escola_id', $dados)) {
            $dados['escola_ids'] = $dados['escola_id'] ? [$dados['escola_id']] : null;
        }

        $validados = validator($dados, [
            'data_inicial' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'reservar_varios_dias' => ['required', 'boolean'],
            'data_final' => [
                'nullable',
                Rule::requiredIf(fn (): bool => (bool) ($dados['reservar_varios_dias'] ?? false)),
                'date_format:Y-m-d',
                'after_or_equal:data_inicial',
            ],
            ...$this->regrasComuns(),
        ], $this->mensagens())->validate();

        $validados['data_final'] = $validados['reservar_varios_dias']
            ? $validados['data_final']
            : $validados['data_inicial'];

        $this->datasDoIntervalo($validados['data_inicial'], $validados['data_final']);

        return $validados;
    }

    /** @return array<string, mixed> */
    private function validarEdicao(array $dados): array
    {
        if (! array_key_exists('escola_ids', $dados) && array_key_exists('escola_id', $dados)) {
            $dados['escola_ids'] = $dados['escola_id'] ? [$dados['escola_id']] : null;
        }

        return validator($dados, [
            'data' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            ...$this->regrasComuns(),
        ], $this->mensagens())->validate();
    }

    /** @return array<string, array<int, mixed>> */
    private function regrasComuns(): array
    {
        return [
            'hora_inicio' => ['required', 'date_format:H:i'],
            'hora_fim' => ['required', 'date_format:H:i', 'after:hora_inicio'],
            'atividade' => ['required', 'string', 'max:500'],
            'tipo_local' => ['required', Rule::in(['escola', 'outros'])],
            'escola_ids' => ['nullable', 'required_if:tipo_local,escola', 'array', 'min:1'],
            'escola_ids.*' => ['integer', 'exists:escolas,id'],
            'escola_id' => ['nullable', 'integer', 'exists:escolas,id'],
            'local_outro' => ['nullable', 'required_if:tipo_local,outros', 'string', 'max:255'],
            'veiculo_transporte_id' => [
                'required',
                'integer',
                Rule::exists('veiculos_transporte', 'id')->where('ativo', true),
            ],
        ];
    }

    /** @return array<string, string> */
    private function mensagens(): array
    {
        return [
            'data_inicial.after_or_equal' => 'A reserva deve começar hoje ou em uma data futura.',
            'data_final.required' => 'Informe a data final do intervalo.',
            'data_final.after_or_equal' => 'A data final deve ser igual ou posterior à data inicial.',
            'hora_fim.after' => 'O horário final deve ser posterior ao horário inicial.',
            'escola_ids.required_if' => 'Selecione ao menos uma escola ou CMEI de destino.',
            'local_outro.required_if' => 'Informe o local da atividade.',
            'veiculo_transporte_id.exists' => 'O veículo selecionado não está disponível para reserva.',
        ];
    }

    /**
     * @param  list<string>  $datas
     * @return list<array{inicio: CarbonImmutable, fim: CarbonImmutable}>
     */
    private function periodos(array $datas, string $horaInicio, string $horaFim): array
    {
        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));

        return collect($datas)
            ->map(function (string $data) use ($horaInicio, $horaFim, $timezone): array {
                $inicio = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data} {$horaInicio}", $timezone);
                $fim = CarbonImmutable::createFromFormat('Y-m-d H:i', "{$data} {$horaFim}", $timezone);

                if (! $inicio || ! $fim || $fim->lte($inicio)) {
                    throw ValidationException::withMessages([
                        'hora_fim' => 'O horário final deve ser posterior ao horário inicial.',
                    ]);
                }

                return compact('inicio', 'fim');
            })
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function datasDoIntervalo(string $dataInicial, string $dataFinal): array
    {
        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d', $dataInicial, $timezone);
        $fim = CarbonImmutable::createFromFormat('!Y-m-d', $dataFinal, $timezone);

        if (! $inicio || ! $fim || $fim->lt($inicio)) {
            throw ValidationException::withMessages([
                'data_final' => 'A data final deve ser igual ou posterior à data inicial.',
            ]);
        }

        if ($inicio->diffInDays($fim) > 30) {
            throw ValidationException::withMessages([
                'data_final' => 'O intervalo pode ter no máximo 31 dias.',
            ]);
        }

        $datas = [];

        for ($data = $inicio; $data->lte($fim); $data = $data->addDay()) {
            $datas[] = $data->toDateString();
        }

        return $datas;
    }

    /**
     * @param  list<array{inicio: CarbonImmutable, fim: CarbonImmutable}>  $periodos
     */
    private function validarDisponibilidade(
        int $veiculoId,
        array $periodos,
        ?int $ignorarReservaId = null,
    ): void {
        foreach ($periodos as $periodo) {
            $conflito = ReservaVeiculo::query()
                ->ativas()
                ->where('veiculo_transporte_id', $veiculoId)
                ->when(
                    $ignorarReservaId,
                    fn (Builder $reservas): Builder => $reservas->whereKeyNot($ignorarReservaId),
                )
                ->where('data_inicio', '<', $periodo['fim'])
                ->where('data_fim', '>', $periodo['inicio'])
                ->exists();

            if ($conflito) {
                throw ValidationException::withMessages([
                    'veiculo_transporte_id' => 'O veículo já possui uma reserva neste dia e horário.',
                ]);
            }
        }
    }

    private function aplicarConflito(
        Builder $query,
        CarbonImmutable $inicio,
        CarbonImmutable $fim,
        ?int $ignorarReservaId,
    ): Builder {
        return $query
            ->when(
                $ignorarReservaId,
                fn (Builder $reservas): Builder => $reservas->whereKeyNot($ignorarReservaId),
            )
            ->where('data_inicio', '<', $fim)
            ->where('data_fim', '>', $inicio);
    }

    /**
     * @param  list<array{inicio: CarbonImmutable, fim: CarbonImmutable}>  $periodos
     */
    private function validarPeriodosFuturos(array $periodos): void
    {
        if (collect($periodos)->contains(
            fn (array $periodo): bool => $periodo['inicio']->lte(now()),
        )) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'O horário inicial da reserva deve ser posterior ao horário atual.',
            ]);
        }
    }

    /** @return array{0: int|null, 1: string, 2: list<int>} */
    private function resolverLocal(array $dados, User $ator): array
    {
        if ($dados['tipo_local'] === 'escola') {
            $escolaIds = array_values(array_unique(array_map('intval', $dados['escola_ids'] ?? (($dados['escola_id'] ?? null) ? [$dados['escola_id']] : []))));
            $escolas = Escola::query()->ativas()->whereIn('id', $escolaIds)->orderBy('nome')->get();

            if ($escolas->count() !== count($escolaIds)) {
                throw ValidationException::withMessages([
                    'escola_id' => 'A escola ou o CMEI selecionado não está ativo.',
                ]);
            }

            $permitidas = $this->scope->escolaIdsDosVinculos($ator);
            if (! $this->scope->hasGlobalAccess($ator) && array_diff($escolaIds, $permitidas) !== []) {
                throw ValidationException::withMessages([
                    'escola_ids' => 'Selecione apenas escolas pertencentes ao seu contexto de acesso.',
                ]);
            }

            return [$escolas->first()->id, $escolas->pluck('nome')->implode(', '), $escolaIds];
        }

        return [
            null, [],
            Str::of((string) $dados['local_outro'])->squish()->limit(255)->toString(),
        ];
    }

    private function rotuloVeiculo(VeiculoTransporte $veiculo): string
    {
        return collect([
            $veiculo->identificacao,
            $veiculo->placa,
        ])->filter()->implode(' — ');
    }

    private function aplicarEscopoEscolas(Builder $query, User $ator): Builder
    {
        if ($this->scope->hasGlobalAccess($ator)) {
            return $query;
        }

        $ids = $this->scope->escolaIdsDosVinculos($ator);
        if ($ids === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $reservas) use ($ids): void {
            $reservas->whereIn('escola_id', $ids)
                ->orWhereHas('escolas', fn (Builder $escolas): Builder => $escolas->whereKey($ids));
        });
    }
}
