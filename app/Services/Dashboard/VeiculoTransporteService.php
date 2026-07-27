<?php

namespace App\Services\Dashboard;

use App\Models\User;
use App\Models\VeiculoTransporte;
use DomainException;
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

        $query = VeiculoTransporte::query()
            ->withCount([
                'reservasAtivas as reservas_ativas_count' => fn (Builder $reservas): Builder => $reservas
                    ->where('data_fim', '>=', now()),
            ]);
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

        return DB::transaction(function () use ($dados): VeiculoTransporte {
            $veiculo = VeiculoTransporte::query()->create([
                ...$dados,
                'capacidade_passageiros' => 1,
                'ativo' => true,
            ]);

            return $veiculo->fresh();
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

    /** @return array{placa: string, identificacao: ?string} */
    private function validarDados(array $dados, ?int $ignorarVeiculoId = null): array
    {
        $normalizados = [
            'placa' => VeiculoTransporte::normalizarPlaca($dados['placa'] ?? null),
            'identificacao' => filled($dados['identificacao'] ?? null)
                ? Str::of((string) $dados['identificacao'])->squish()->toString()
                : null,
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
        ], [
            'placa.regex' => 'Informe uma placa válida no padrão antigo ou Mercosul.',
            'placa.unique' => 'Já existe um veículo com esta placa.',
        ]);

        if ($validator->fails()) {
            throw new ValidationException($validator);
        }

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

            if (! $ativo && $veiculo->reservasAtivas()->where('data_fim', '>=', now())->exists()) {
                throw new DomainException('O veículo possui reservas futuras e não pode ser desativado.');
            }

            if ((bool) $veiculo->ativo !== $ativo) {
                $veiculo->forceFill(['ativo' => $ativo])->save();
            }

            return $veiculo->fresh();
        });
    }
}
