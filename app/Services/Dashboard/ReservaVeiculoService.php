<?php

namespace App\Services\Dashboard;

use App\Models\Escola;
use App\Models\ReservaVeiculo;
use App\Models\Enums\ReservaVeiculoStatus;
use App\Models\User;
use App\Models\VeiculoTransporte;
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
    public function query(User $ator): Builder
    {
        Gate::forUser($ator)->authorize('viewAny', ReservaVeiculo::class);

        return ReservaVeiculo::query()
            ->with([
                'veiculo:id,placa,identificacao,capacidade_passageiros,ativo',
                'usuario:id,name,email',
                'escola:id,nome',
                'canceladoPor:id,name',
            ])
            ->orderByDesc('data_inicio');
    }

    /** @return Collection<int, ReservaVeiculo> */
    public function criarEmLote(User $ator, array $dados): Collection
    {
        Gate::forUser($ator)->authorize('create', ReservaVeiculo::class);
        $dados = $this->validarCriacao($dados);
        $periodos = $this->periodos($dados['datas'], $dados['hora_inicio'], $dados['hora_fim']);

        return DB::transaction(function () use ($ator, $dados, $periodos): Collection {
            $veiculo = VeiculoTransporte::query()
                ->ativos()
                ->lockForUpdate()
                ->findOrFail($dados['veiculo_transporte_id']);

            $this->validarDisponibilidade($veiculo->id, $periodos);
            [$escolaId, $localNome] = $this->resolverLocal($dados);
            $grupo = count($periodos) > 1 ? (string) Str::uuid() : null;

            return collect($periodos)->map(fn (array $periodo): ReservaVeiculo => ReservaVeiculo::query()->create([
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
            ]));
        });
    }

    public function atualizar(User $ator, ReservaVeiculo $reserva, array $dados): ReservaVeiculo
    {
        $dados = $this->validarEdicao($dados);
        $periodo = $this->periodos([$dados['data']], $dados['hora_inicio'], $dados['hora_fim']);

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
            [$escolaId, $localNome] = $this->resolverLocal($dados);

            $reserva->fill([
                'veiculo_transporte_id' => $veiculo->id,
                'escola_id' => $escolaId,
                'local_nome' => $localNome,
                'atividade' => $dados['atividade'],
                'data_inicio' => $periodo[0]['inicio'],
                'data_fim' => $periodo[0]['fim'],
                'atualizado_por_id' => $ator->id,
            ])->save();

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
        array $datas,
        ?string $horaInicio,
        ?string $horaFim,
        ?int $ignorarReservaId = null,
    ): array {
        Gate::forUser($ator)->authorize('viewAny', ReservaVeiculo::class);

        if ($datas === [] || blank($horaInicio) || blank($horaFim)) {
            return [];
        }

        try {
            $periodos = $this->periodos($datas, $horaInicio, $horaFim);
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

        return ReservaVeiculo::query()
            ->ativas()
            ->where('data_fim', '>=', now())
            ->with(['veiculo:id,placa,identificacao', 'usuario:id,name', 'escola:id,nome'])
            ->orderBy('data_inicio')
            ->limit(max(1, min($limite, 12)))
            ->get();
    }

    /** @return array<string, mixed> */
    private function validarCriacao(array $dados): array
    {
        return validator($dados, [
            'datas' => ['required', 'array', 'min:1', 'max:31'],
            'datas.*' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'distinct'],
            ...$this->regrasComuns(),
        ], $this->mensagens())->validate();
    }

    /** @return array<string, mixed> */
    private function validarEdicao(array $dados): array
    {
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
            'escola_id' => ['nullable', 'required_if:tipo_local,escola', 'integer', 'exists:escolas,id'],
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
            'datas.required' => 'Informe ao menos uma data para a reserva.',
            'datas.*.after_or_equal' => 'As reservas devem ser feitas para hoje ou uma data futura.',
            'datas.*.distinct' => 'A mesma data foi informada mais de uma vez.',
            'hora_fim.after' => 'O horário final deve ser posterior ao horário inicial.',
            'escola_id.required_if' => 'Selecione a escola ou o CMEI de destino.',
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

    /** @return array{0: int|null, 1: string} */
    private function resolverLocal(array $dados): array
    {
        if ($dados['tipo_local'] === 'escola') {
            $escola = Escola::query()->ativas()->find($dados['escola_id']);

            if (! $escola) {
                throw ValidationException::withMessages([
                    'escola_id' => 'A escola ou o CMEI selecionado não está ativo.',
                ]);
            }

            return [$escola->id, $escola->nome];
        }

        return [
            null,
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
}
