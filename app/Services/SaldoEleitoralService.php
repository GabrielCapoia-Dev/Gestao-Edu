<?php

namespace App\Services;

use App\Models\SaldoEleitoral;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaldoEleitoralService
{
    public function saldoAprovado(Servidor|int $servidor): int
    {
        $id = $servidor instanceof Servidor ? $servidor->getKey() : $servidor;

        return (int) SaldoEleitoral::query()
            ->where('servidor_id', $id)
            ->where('status', SaldoEleitoral::STATUS_APROVADO)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo = 'adicao' THEN dias ELSE -dias END), 0) as saldo")
            ->value('saldo');
    }

    public function disponivelParaSolicitacao(Servidor|int $servidor): int
    {
        $id = $servidor instanceof Servidor ? $servidor->getKey() : $servidor;
        $pendente = (int) SaldoEleitoral::query()
            ->where('servidor_id', $id)
            ->where('tipo', SaldoEleitoral::TIPO_USO)
            ->where('status', SaldoEleitoral::STATUS_PENDENTE)
            ->sum('dias');

        return max(0, $this->saldoAprovado($id) - $pendente);
    }

    public function solicitar(Servidor $servidor, User $usuario, string $tipo, int $dias): SaldoEleitoral
    {
        if (! in_array($tipo, [SaldoEleitoral::TIPO_ADICAO, SaldoEleitoral::TIPO_USO], true) || $dias < 1) {
            throw ValidationException::withMessages(['dias' => 'Informe um número de dias maior que zero.']);
        }

        return DB::transaction(function () use ($servidor, $usuario, $tipo, $dias): SaldoEleitoral {
            $servidor = Servidor::query()->lockForUpdate()->findOrFail($servidor->getKey());

            if ($tipo === SaldoEleitoral::TIPO_USO && $dias > $this->disponivelParaSolicitacao($servidor)) {
                throw ValidationException::withMessages(['dias' => 'A quantidade solicitada excede os dias disponíveis para uso.']);
            }

            return SaldoEleitoral::query()->create([
                'servidor_id' => $servidor->getKey(),
                'solicitante_id' => $usuario->getKey(),
                'tipo' => $tipo,
                'dias' => $dias,
                'status' => SaldoEleitoral::STATUS_PENDENTE,
            ]);
        });
    }

    public function decidir(SaldoEleitoral $solicitacao, User $rh, bool $aprovar): SaldoEleitoral
    {
        return DB::transaction(function () use ($solicitacao, $rh, $aprovar): SaldoEleitoral {
            $servidor = Servidor::withTrashed()->lockForUpdate()->findOrFail($solicitacao->servidor_id);
            $registro = SaldoEleitoral::query()->lockForUpdate()->findOrFail($solicitacao->getKey());

            if ($registro->status !== SaldoEleitoral::STATUS_PENDENTE) {
                throw ValidationException::withMessages(['solicitacao' => 'Esta solicitação já foi analisada.']);
            }

            if ($aprovar && $registro->tipo === SaldoEleitoral::TIPO_USO && $registro->dias > $this->saldoAprovado($servidor)) {
                throw ValidationException::withMessages(['solicitacao' => 'O saldo aprovado já não cobre esta solicitação.']);
            }

            $registro->forceFill([
                'status' => $aprovar ? SaldoEleitoral::STATUS_APROVADO : SaldoEleitoral::STATUS_REJEITADO,
                'aprovador_id' => $rh->getKey(),
                'decidido_em' => now(),
            ])->save();

            return $registro->refresh();
        });
    }

    public function descontar(Servidor $servidor, User $rh, int $dias, ?string $observacao = null): SaldoEleitoral
    {
        if ($dias < 1) {
            throw ValidationException::withMessages(['dias' => 'Informe um número de dias maior que zero.']);
        }

        return DB::transaction(function () use ($servidor, $rh, $dias, $observacao): SaldoEleitoral {
            $servidor = Servidor::query()->lockForUpdate()->findOrFail($servidor->getKey());

            if ($dias > $this->disponivelParaSolicitacao($servidor)) {
                throw ValidationException::withMessages(['dias' => 'O desconto não pode exceder os dias disponíveis.']);
            }

            return SaldoEleitoral::query()->create([
                'servidor_id' => $servidor->getKey(),
                'solicitante_id' => $rh->getKey(),
                'aprovador_id' => $rh->getKey(),
                'tipo' => SaldoEleitoral::TIPO_USO,
                'dias' => $dias,
                'status' => SaldoEleitoral::STATUS_APROVADO,
                'lancamento_manual' => true,
                'observacao' => $observacao,
                'decidido_em' => now(),
            ]);
        });
    }
}
