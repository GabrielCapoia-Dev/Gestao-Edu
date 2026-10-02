<?php

namespace App\Services;

use App\Models\SaldoEleitoral;
use App\Models\Servidor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class SaldoEleitoralService
{
    public function saldoAprovado(Servidor|int $servidor): int
    {
        $id = $servidor instanceof Servidor ? $servidor->getKey() : $servidor;

        return (int) SaldoEleitoral::query()
            ->where('servidor_id', $id)
            ->where('status', SaldoEleitoral::STATUS_APROVADO)
            ->selectRaw("COALESCE(SUM(CASE WHEN tipo IN ('adicao', 'estorno') THEN dias ELSE -dias END), 0) as saldo")
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

    /** @return Collection<int, SaldoEleitoral> */
    public function usosElegiveisParaEstorno(Servidor|int $servidor): Collection
    {
        $id = $servidor instanceof Servidor ? $servidor->getKey() : $servidor;

        return SaldoEleitoral::query()
            ->where('servidor_id', $id)
            ->where('tipo', SaldoEleitoral::TIPO_USO)
            ->where('status', SaldoEleitoral::STATUS_APROVADO)
            ->with('estornos:id,movimento_origem_id,dias,status,datas')
            ->latest('decidido_em')
            ->get()
            ->filter(fn (SaldoEleitoral $uso): bool => $this->diasRestantesParaEstorno($uso) > 0)
            ->values();
    }

    public function diasRestantesParaEstorno(SaldoEleitoral $uso): int
    {
        $estornos = $uso->relationLoaded('estornos') ? $uso->estornos : $uso->estornos()->get();
        $reservados = $estornos
            ->whereIn('status', [SaldoEleitoral::STATUS_PENDENTE, SaldoEleitoral::STATUS_APROVADO])
            ->sum('dias');

        return max(0, (int) $uso->dias - (int) $reservados);
    }

    /** @return list<string>|null Null means the original use predates date tracking. */
    public function datasDisponiveisParaEstorno(SaldoEleitoral $uso): ?array
    {
        if (blank($uso->datas)) {
            return null;
        }

        $estornos = $uso->relationLoaded('estornos') ? $uso->estornos : $uso->estornos()->get();
        $reservadas = $estornos
            ->whereIn('status', [SaldoEleitoral::STATUS_PENDENTE, SaldoEleitoral::STATUS_APROVADO])
            ->flatMap(fn (SaldoEleitoral $estorno): array => $estorno->datas ?? [])
            ->all();

        return array_values(array_diff($uso->datas, $reservadas));
    }

    public function solicitar(Servidor $servidor, User $usuario, string $tipo, int $dias, ?array $datas = null): SaldoEleitoral
    {
        if (! in_array($tipo, [SaldoEleitoral::TIPO_ADICAO, SaldoEleitoral::TIPO_USO], true) || $dias < 1) {
            throw ValidationException::withMessages(['dias' => 'Informe um número de dias maior que zero.']);
        }

        $datas = $tipo === SaldoEleitoral::TIPO_USO
            ? app(SaldoEleitoralCalendarService::class)->validateUsageDates($datas ?? [], $dias)
            : null;

        return DB::transaction(function () use ($servidor, $usuario, $tipo, $dias, $datas): SaldoEleitoral {
            $servidor = Servidor::query()->lockForUpdate()->findOrFail($servidor->getKey());

            if ($tipo === SaldoEleitoral::TIPO_USO && $dias > $this->disponivelParaSolicitacao($servidor)) {
                throw ValidationException::withMessages(['dias' => 'A quantidade solicitada excede os dias disponíveis para uso.']);
            }

            if ($tipo === SaldoEleitoral::TIPO_USO) {
                $datasEmUso = SaldoEleitoral::query()
                    ->where('servidor_id', $servidor->getKey())
                    ->where('tipo', SaldoEleitoral::TIPO_USO)
                    ->whereIn('status', [SaldoEleitoral::STATUS_PENDENTE, SaldoEleitoral::STATUS_APROVADO])
                    ->get(['datas'])
                    ->flatMap(fn (SaldoEleitoral $movimento): array => $movimento->datas ?? [])
                    ->all();

                if (array_intersect($datas, $datasEmUso)) {
                    throw ValidationException::withMessages(['datas' => 'Uma ou mais datas já estão reservadas ou aprovadas para uso do saldo.']);
                }
            }

            return SaldoEleitoral::query()->create([
                'servidor_id' => $servidor->getKey(),
                'solicitante_id' => $usuario->getKey(),
                'tipo' => $tipo,
                'dias' => $dias,
                'datas' => $datas,
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

            if ($aprovar && $registro->tipo === SaldoEleitoral::TIPO_USO) {
                if ($registro->dias > $this->saldoAprovado($servidor)) {
                    throw ValidationException::withMessages(['solicitacao' => 'O saldo aprovado já não cobre esta solicitação.']);
                }

                $datasEmUso = SaldoEleitoral::query()
                    ->where('servidor_id', $servidor->getKey())
                    ->where('tipo', SaldoEleitoral::TIPO_USO)
                    ->whereIn('status', [SaldoEleitoral::STATUS_PENDENTE, SaldoEleitoral::STATUS_APROVADO])
                    ->where('id', '<>', $registro->getKey())
                    ->get(['datas'])
                    ->flatMap(fn (SaldoEleitoral $movimento): array => $movimento->datas ?? [])
                    ->all();

                if (array_intersect($registro->datas ?? [], $datasEmUso)) {
                    throw ValidationException::withMessages(['solicitacao' => 'Uma ou mais datas desta solicitação já foram reservadas ou aprovadas em outro pedido.']);
                }
            }

            if ($aprovar && $registro->tipo === SaldoEleitoral::TIPO_ESTORNO) {
                $uso = SaldoEleitoral::query()->lockForUpdate()->findOrFail($registro->movimento_origem_id);
                $this->validarLimiteEstorno($registro, $uso);
            }

            $registro->forceFill([
                'status' => $aprovar ? SaldoEleitoral::STATUS_APROVADO : SaldoEleitoral::STATUS_REJEITADO,
                'aprovador_id' => $rh->getKey(),
                'decidido_em' => now(),
            ])->save();

            return $registro->refresh();
        });
    }

    public function solicitarEstorno(
        Servidor $servidor,
        User $usuario,
        int $usoId,
        int $dias,
        string $justificativa,
        ?array $datas = null,
    ): SaldoEleitoral {
        $justificativa = trim($justificativa);
        if ($dias < 1 || blank($justificativa) || mb_strlen($justificativa) > 1000) {
            throw ValidationException::withMessages([
                'justificativa' => 'Informe a quantidade de dias e uma justificativa de até 1.000 caracteres.',
            ]);
        }

        return DB::transaction(function () use ($servidor, $usuario, $usoId, $dias, $justificativa, $datas): SaldoEleitoral {
            $servidor = Servidor::query()->lockForUpdate()->findOrFail($servidor->getKey());
            $uso = SaldoEleitoral::query()
                ->where('servidor_id', $servidor->getKey())
                ->where('tipo', SaldoEleitoral::TIPO_USO)
                ->where('status', SaldoEleitoral::STATUS_APROVADO)
                ->lockForUpdate()
                ->findOrFail($usoId);

            if ($dias > $this->diasRestantesParaEstorno($uso)) {
                throw ValidationException::withMessages(['dias' => 'A quantidade excede os dias ainda estornáveis deste uso.']);
            }

            if (filled($uso->datas)) {
                $datas = app(SaldoEleitoralCalendarService::class)->validateUsageDates($datas ?? [], $dias);
                $datasDisponiveis = $this->datasDisponiveisParaEstorno($uso) ?? [];

                if (array_diff($datas, $datasDisponiveis) !== []) {
                    throw ValidationException::withMessages(['datas' => 'Selecione apenas datas do uso aprovado que ainda não foram estornadas.']);
                }
            } else {
                if (filled($datas)) {
                    throw ValidationException::withMessages(['datas' => 'Este uso antigo não possui datas individualizadas.']);
                }
                $datas = null;
            }

            return SaldoEleitoral::query()->create([
                'servidor_id' => $servidor->getKey(),
                'movimento_origem_id' => $uso->getKey(),
                'solicitante_id' => $usuario->getKey(),
                'tipo' => SaldoEleitoral::TIPO_ESTORNO,
                'dias' => $dias,
                'datas' => $datas,
                'status' => SaldoEleitoral::STATUS_PENDENTE,
                'observacao' => $justificativa,
            ]);
        });
    }

    public function descontar(Servidor $servidor, User $rh, int $dias, ?string $observacao = null, ?array $datas = null): SaldoEleitoral
    {
        if ($dias < 1) {
            throw ValidationException::withMessages(['dias' => 'Informe um número de dias maior que zero.']);
        }

        $datas = app(SaldoEleitoralCalendarService::class)->validateUsageDates($datas ?? [], $dias);

        return DB::transaction(function () use ($servidor, $rh, $dias, $observacao, $datas): SaldoEleitoral {
            $servidor = Servidor::query()->lockForUpdate()->findOrFail($servidor->getKey());

            if ($dias > $this->disponivelParaSolicitacao($servidor)) {
                throw ValidationException::withMessages(['dias' => 'O desconto não pode exceder os dias disponíveis.']);
            }

            $datasEmUso = SaldoEleitoral::query()
                ->where('servidor_id', $servidor->getKey())
                ->where('tipo', SaldoEleitoral::TIPO_USO)
                ->whereIn('status', [SaldoEleitoral::STATUS_PENDENTE, SaldoEleitoral::STATUS_APROVADO])
                ->get(['datas'])
                ->flatMap(fn (SaldoEleitoral $movimento): array => $movimento->datas ?? [])
                ->all();

            if (array_intersect($datas, $datasEmUso)) {
                throw ValidationException::withMessages(['datas' => 'Uma ou mais datas já estão reservadas ou aprovadas para uso do saldo.']);
            }

            return SaldoEleitoral::query()->create([
                'servidor_id' => $servidor->getKey(),
                'solicitante_id' => $rh->getKey(),
                'aprovador_id' => $rh->getKey(),
                'tipo' => SaldoEleitoral::TIPO_USO,
                'dias' => $dias,
                'datas' => $datas,
                'status' => SaldoEleitoral::STATUS_APROVADO,
                'lancamento_manual' => true,
                'observacao' => $observacao,
                'decidido_em' => now(),
            ]);
        });
    }

    private function validarLimiteEstorno(SaldoEleitoral $estorno, SaldoEleitoral $uso): void
    {
        if ($uso->tipo !== SaldoEleitoral::TIPO_USO
            || $uso->status !== SaldoEleitoral::STATUS_APROVADO
            || (int) $uso->servidor_id !== (int) $estorno->servidor_id) {
            throw ValidationException::withMessages(['solicitacao' => 'O uso original não está disponível para estorno.']);
        }

        $outrosEstornos = SaldoEleitoral::query()
            ->where('movimento_origem_id', $uso->getKey())
            ->where('tipo', SaldoEleitoral::TIPO_ESTORNO)
            ->whereIn('status', [SaldoEleitoral::STATUS_PENDENTE, SaldoEleitoral::STATUS_APROVADO]);

        if ($outrosEstornos->sum('dias') > (int) $uso->dias) {
            throw ValidationException::withMessages(['solicitacao' => 'Os estornos pendentes e aprovados excedem o uso original.']);
        }

        if (filled($uso->datas)) {
            if (! filled($estorno->datas) || count($estorno->datas) !== (int) $estorno->dias) {
                throw ValidationException::withMessages(['solicitacao' => 'As datas do estorno não correspondem à quantidade solicitada.']);
            }

            $datasOriginais = $uso->datas ?? [];
            $estornos = (clone $outrosEstornos)->where('id', '<>', $estorno->getKey())->get(['datas']);
            $datasJaReservadas = $estornos->flatMap(fn (SaldoEleitoral $item): array => $item->datas ?? [])->all();

            if (array_diff($estorno->datas, $datasOriginais) !== []
                || array_intersect($estorno->datas, $datasJaReservadas) !== []) {
                throw ValidationException::withMessages(['solicitacao' => 'As datas do estorno não correspondem a dias disponíveis do uso original.']);
            }
        } elseif (filled($estorno->datas)) {
            throw ValidationException::withMessages(['solicitacao' => 'O uso original não possui datas individualizadas.']);
        }
    }
}
