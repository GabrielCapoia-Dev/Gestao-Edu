<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\VeiculoTransporte;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class VeiculoTransporteService
{
    public function query(User $ator, ?string $search = null): Builder
    {
        Gate::forUser($ator)->authorize('viewAny', VeiculoTransporte::class);

        $query = VeiculoTransporte::query();
        $search = trim((string) $search);

        if ($search !== '') {
            $placa = VeiculoTransporte::normalizarPlaca($search);
            $query->where(function (Builder $filtro) use ($search, $placa): void {
                $filtro->where('identificacao', 'like', "%{$search}%");

                if ($placa !== '') {
                    $filtro->orWhere('placa', 'like', "%{$placa}%");
                }
            });
        }

        return $query->orderByDesc('ativo')->orderBy('identificacao')->orderBy('placa');
    }

    public function criar(User $ator, array $dados): VeiculoTransporte
    {
        Gate::forUser($ator)->authorize('create', VeiculoTransporte::class);
        $dados = $this->validarDados($dados);

        return DB::transaction(function () use ($ator, $dados): VeiculoTransporte {
            $existente = VeiculoTransporte::query()
                ->where('placa', $dados['placa'])
                ->lockForUpdate()
                ->exists();

            if ($existente) {
                throw ValidationException::withMessages(['placa' => 'Já existe um veículo com esta placa.']);
            }

            $veiculo = VeiculoTransporte::query()->create([...$dados, 'ativo' => true]);
            $veiculo = VeiculoTransporte::query()->lockForUpdate()->findOrFail($veiculo->getKey());
            Gate::forUser($ator)->authorize('view', $veiculo);

            return $veiculo;
        });
    }

    public function atualizar(User $ator, VeiculoTransporte $veiculo, array $dados): VeiculoTransporte
    {
        $dados = $this->validarDados($dados, $veiculo->getKey());

        return DB::transaction(function () use ($ator, $veiculo, $dados): VeiculoTransporte {
            $veiculo = VeiculoTransporte::query()->lockForUpdate()->findOrFail($veiculo->getKey());
            Gate::forUser($ator)->authorize('update', $veiculo);

            $veiculo->fill($dados)->save();

            return $veiculo->fresh();
        });
    }

    public function ativar(User $ator, VeiculoTransporte $veiculo): VeiculoTransporte
    {
        return $this->alterarStatus($ator, $veiculo, true);
    }

    public function desativar(User $ator, VeiculoTransporte $veiculo): VeiculoTransporte
    {
        return $this->alterarStatus($ator, $veiculo, false);
    }

    /** @return array{placa: string, identificacao: ?string, capacidade_passageiros: int} */
    private function validarDados(array $dados, ?int $ignorarVeiculoId = null): array
    {
        $normalizados = [
            'placa' => VeiculoTransporte::normalizarPlaca($dados['placa'] ?? null),
            'identificacao' => filled($dados['identificacao'] ?? null)
                ? Str::of((string) $dados['identificacao'])->squish()->toString()
                : null,
            'capacidade_passageiros' => $dados['capacidade_passageiros'] ?? null,
        ];

        $placaUnica = Rule::unique('veiculos_transporte', 'placa');
        if ($ignorarVeiculoId !== null) {
            $placaUnica->ignore($ignorarVeiculoId);
        }

        $validator = validator($normalizados, [
            'placa' => [
                'required',
                'string',
                'regex:/^(?:[A-Z]{3}[0-9]{4}|[A-Z]{3}[0-9][A-Z][0-9]{2})$/',
                $placaUnica,
            ],
            'identificacao' => ['nullable', 'string', 'max:120'],
            'capacidade_passageiros' => ['required', 'integer', 'min:1', 'max:500'],
        ], [
            'placa.regex' => 'Informe uma placa válida no padrão antigo ou Mercosul.',
            'placa.unique' => 'Já existe um veículo com esta placa.',
            'capacidade_passageiros.min' => 'A capacidade deve ser de pelo menos 1 passageiro.',
            'capacidade_passageiros.max' => 'A capacidade não pode ultrapassar 500 passageiros.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

        $normalizados['capacidade_passageiros'] = (int) $normalizados['capacidade_passageiros'];

        return $normalizados;
    }

    private function alterarStatus(
        User $ator,
        VeiculoTransporte $veiculo,
        bool $ativo,
    ): VeiculoTransporte {
        return DB::transaction(function () use ($ator, $veiculo, $ativo): VeiculoTransporte {
            $veiculo = VeiculoTransporte::query()->lockForUpdate()->findOrFail($veiculo->getKey());
            Gate::forUser($ator)->authorize($ativo ? 'activate' : 'deactivate', $veiculo);

            if ((bool) $veiculo->ativo !== $ativo) {
                $veiculo->forceFill(['ativo' => $ativo])->save();
            }

            return $veiculo->fresh();
        });
    }
}
