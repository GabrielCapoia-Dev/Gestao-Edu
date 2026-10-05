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
            $this->datasDaRepeticao($dados),
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
        array $regraRepeticao = [],
    ): array {
        Gate::forUser($ator)->authorize('viewAny', ReservaVeiculo::class);
        ReservaVeiculo::concluirExpiradas();

        if (blank($dataInicial) || blank($dataFinal) || blank($horaInicio) || blank($horaFim)) {
            return [];
        }

        try {
            $periodos = $this->periodos(
                $this->datasDaRepeticao([
                    'data_inicial' => $dataInicial,
                    'data_final' => $dataFinal,
                    ...$regraRepeticao,
                ]),
                $horaInicio,
                $horaFim,
            );
        } catch (\Throwable) {
            return [];
        }

        $conflitos = ReservaVeiculo::query()
            ->ativas()
            ->when($ignorarReservaId, fn (Builder $reservas): Builder => $reservas->whereKeyNot($ignorarReservaId))
            ->where('data_inicio', '<', collect($periodos)->max('fim'))
            ->where('data_fim', '>', collect($periodos)->min('inicio'))
            ->get(['veiculo_transporte_id', 'data_inicio', 'data_fim']);

        $veiculosIndisponiveis = $conflitos
            ->filter(fn (ReservaVeiculo $reserva): bool => collect($periodos)->contains(
                fn (array $periodo): bool => $reserva->data_inicio->lt($periodo['fim'])
                    && $reserva->data_fim->gt($periodo['inicio']),
            ))
            ->pluck('veiculo_transporte_id')
            ->unique()
            ->all();

        return VeiculoTransporte::query()
            ->ativos()
            ->whereNotIn('id', $veiculosIndisponiveis)
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
        $dados['repeticao'] ??= ! empty($dados['reservar_varios_dias']) ? 'diaria' : 'nenhuma';
        // Mantém compatibilidade com clientes antigos, mas toda série termina pela data final.
        $dados['fim_repeticao'] = 'data';

        if (! array_key_exists('escola_ids', $dados) && array_key_exists('escola_id', $dados)) {
            $dados['escola_ids'] = $dados['escola_id'] ? [$dados['escola_id']] : null;
        }

        $validados = validator($dados, [
            'data_inicial' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'repeticao' => ['required', Rule::in(['nenhuma', 'diaria', 'semanal', 'mensal', 'personalizada'])],
            'dias_semana' => ['nullable', 'array'],
            'dias_semana.*' => ['integer', 'between:1,7'],
            'data_final' => [
                Rule::excludeIf(fn (): bool => ($dados['repeticao'] ?? 'nenhuma') === 'nenhuma'),
                'nullable',
                Rule::requiredIf(fn (): bool => ($dados['repeticao'] ?? 'nenhuma') !== 'nenhuma'),
                'date_format:Y-m-d',
                'after_or_equal:data_inicial',
            ],
            ...$this->regrasComuns(),
        ], $this->mensagens())->validate();

        $validados['data_final'] = $validados['repeticao'] === 'nenhuma'
            ? $validados['data_inicial']
            : $validados['data_final'];
        $validados['fim_repeticao'] = 'data';

        $this->datasDaRepeticao($validados);

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
    private function datasDaRepeticao(array $dados): array
    {
        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
        $inicio = CarbonImmutable::createFromFormat('!Y-m-d', $dados['data_inicial'] ?? '', $timezone);
        if (! $inicio) {
            throw ValidationException::withMessages([
                'data_inicial' => 'Informe uma data inicial válida.',
            ]);
        }
        $repeticao = $dados['repeticao'] ?? 'nenhuma';
        $fim = $repeticao === 'nenhuma'
            ? $inicio
            : CarbonImmutable::createFromFormat('!Y-m-d', $dados['data_final'] ?? '', $timezone);

        if (! $inicio || ! $fim || $fim->lt($inicio) || $fim->gt($inicio->addYears(10))) {
            throw ValidationException::withMessages([
                'data_final' => 'A repetição deve terminar entre a data inicial e, no máximo, 10 anos depois.',
            ]);
        }

        if ($repeticao === 'nenhuma') {
            return [$inicio->toDateString()];
        }

        $datas = [];
        $dias = array_values(array_unique(array_map('intval', $dados['dias_semana'] ?? [])));
        if ($repeticao === 'semanal' && $dias === []) {
            $dias = [$inicio->dayOfWeekIso];
        }
        if ($repeticao === 'personalizada' && $dias === []) {
            throw ValidationException::withMessages([
                'dias_semana' => 'Selecione ao menos um dia da semana para a repetição personalizada.',
            ]);
        }

        $limite = $inicio->addYears(10)->min($fim);
        for ($data = $inicio; $data->lte($limite); $data = $data->addDay()) {
            if ($this->ocorreNaData($data, $inicio, $repeticao, $dias)) {
                $datas[] = $data->toDateString();
                if (count($datas) > 366) {
                    throw ValidationException::withMessages([
                        'data_final' => 'A série pode conter no máximo 366 ocorrências. Reduza o intervalo ou o período.',
                    ]);
                }
            }
        }

        if ($datas === []) {
            throw ValidationException::withMessages([
                'data_final' => 'Não há ocorrências para os dias selecionados dentro do intervalo informado.',
            ]);
        }

        return $datas;
    }

    /** @param list<int> $dias */
    private function ocorreNaData(CarbonImmutable $data, CarbonImmutable $inicio, string $repeticao, array $dias): bool
    {
        return match ($repeticao) {
            'diaria' => true,
            'semanal', 'personalizada' => in_array($data->dayOfWeekIso, $dias ?: [$inicio->dayOfWeekIso], true),
            'mensal' => $data->dayOfWeekIso === $inicio->dayOfWeekIso
                && (int) ceil($data->day / 7) === (int) ceil($inicio->day / 7),
            default => false,
        };
    }

    /**
     * @param  list<array{inicio: CarbonImmutable, fim: CarbonImmutable}>  $periodos
     */
    private function validarDisponibilidade(
        int $veiculoId,
        array $periodos,
        ?int $ignorarReservaId = null,
    ): void {
        $conflitos = ReservaVeiculo::query()
            ->ativas()
            ->where('veiculo_transporte_id', $veiculoId)
            ->when($ignorarReservaId, fn (Builder $reservas): Builder => $reservas->whereKeyNot($ignorarReservaId))
            ->where('data_inicio', '<', collect($periodos)->max('fim'))
            ->where('data_fim', '>', collect($periodos)->min('inicio'))
            ->get(['data_inicio', 'data_fim']);

        foreach ($periodos as $periodo) {
            if ($conflitos->contains(fn (ReservaVeiculo $reserva): bool => $reserva->data_inicio->lt($periodo['fim'])
                && $reserva->data_fim->gt($periodo['inicio']))) {
                throw ValidationException::withMessages([
                    'veiculo_transporte_id' => 'O veículo já possui uma reserva neste dia e horário.',
                ]);
            }
        }
    }

    /**
     * @param  list<array{inicio: CarbonImmutable, fim: CarbonImmutable}>  $periodos
     */
    private function validarPeriodosFuturos(array $periodos): void
    {
        $timezone = (string) config('dashboard.calendar.timezone', config('app.timezone'));
        $inicioMinimo = CarbonImmutable::now($timezone)->addMinutes(20);

        if (collect($periodos)->contains(
            fn (array $periodo): bool => $periodo['inicio']->lt($inicioMinimo),
        )) {
            throw ValidationException::withMessages([
                'hora_inicio' => 'A reserva deve ser solicitada com pelo menos 20 minutos de antecedência.',
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
            if (! $this->scope->hasGlobalAccess($ator) && ! $this->scope->ehRh($ator) && array_diff($escolaIds, $permitidas) !== []) {
                throw ValidationException::withMessages([
                    'escola_ids' => 'Selecione apenas escolas pertencentes ao seu contexto de acesso.',
                ]);
            }

            return [$escolas->first()->id, $escolas->pluck('nome')->implode(', '), $escolaIds];
        }

        return [
            null,
            Str::of((string) $dados['local_outro'])->squish()->limit(255)->toString(),
            [],
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

        if ($this->scope->ehRh($ator)) {
            return $query->where('usuario_id', $ator->getKey());
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
