<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\Servidor;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Auditoria legada de possíveis duplicidades.
 *
 * A consolidação automática foi removida: nenhuma Pessoa ou vínculo é alterado
 * por este serviço, inclusive quando dryRun=false.
 */
class PessoaConsolidacaoService
{
    /**
     * @return array{grupos_processados:int,pessoas_removidas:int,vinculos_realocados:int,professores_mesclados:int}
     */
    public function consolidarDuplicatas(bool $dryRun = false): array
    {
        return [
            'grupos_processados' => $this->gruposDuplicados()->count(),
            'pessoas_removidas' => 0,
            'vinculos_realocados' => 0,
            'professores_mesclados' => 0,
        ];
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function gruposDuplicados(): Collection
    {
        $pessoas = Servidor::withTrashed()->orderBy('id')->get();

        return $this->agruparPorCpf($pessoas)
            ->concat($this->agruparPorEmail($pessoas))
            ->concat($this->agruparPorNomeMatricula($pessoas))
            ->filter(fn (Collection $grupo): bool => $grupo->count() > 1)
            ->values();
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function agruparPorCpf(Collection $pessoas): Collection
    {
        return $pessoas
            ->filter(fn (Servidor $pessoa): bool => filled($this->normalizarCpf($pessoa->cpf)))
            ->groupBy(fn (Servidor $pessoa): string => (string) $this->normalizarCpf($pessoa->cpf))
            ->values();
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function agruparPorEmail(Collection $pessoas): Collection
    {
        return $pessoas
            ->filter(fn (Servidor $pessoa): bool => filled(Pessoa::normalizarEmail($pessoa->email)))
            ->groupBy(fn (Servidor $pessoa): string => (string) Pessoa::normalizarEmail($pessoa->email))
            ->values();
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function agruparPorNomeMatricula(Collection $pessoas): Collection
    {
        return $pessoas
            ->filter(fn (Servidor $pessoa): bool => filled($pessoa->nome) && filled($pessoa->matricula))
            ->groupBy(fn (Servidor $pessoa): string => $this->normalizarNome((string) $pessoa->nome).'|'.Str::lower(trim((string) $pessoa->matricula)))
            ->values();
    }

    private function normalizarCpf(?string $cpf): ?string
    {
        if (blank($cpf)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cpf);

        return filled($digits) ? $digits : null;
    }

    private function normalizarNome(string $nome): string
    {
        return Str::of($nome)->squish()->lower()->toString();
    }
}
