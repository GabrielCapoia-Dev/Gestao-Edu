<?php

namespace App\Services;

use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class PessoaConsolidacaoService
{
    /** @var array<int, true> */
    private array $servidoresMesclados = [];

    public function consolidarDuplicatas(bool $dryRun = false): array
    {
        $stats = [
            'grupos_processados' => 0,
            'pessoas_removidas' => 0,
            'vinculos_realocados' => 0,
            'professores_mesclados' => 0,
        ];

        $this->servidoresMesclados = [];

        foreach ($this->gruposDuplicados() as $grupo) {
            if ($grupo->count() < 2) {
                continue;
            }

            $stats['grupos_processados']++;

            $canonica = $grupo->sortBy('id')->first();
            $duplicatas = $grupo->reject(fn (Servidor $servidor): bool => (int) $servidor->id === (int) $canonica->id);

            foreach ($duplicatas as $duplicata) {
                if (isset($this->servidoresMesclados[(int) $duplicata->id])) {
                    continue;
                }

                $resultado = $this->mesclarServidores($canonica, $duplicata, $dryRun);
                $stats['pessoas_removidas'] += $resultado['pessoas_removidas'];
                $stats['vinculos_realocados'] += $resultado['vinculos_realocados'];
                $stats['professores_mesclados'] += $resultado['professores_mesclados'];
            }
        }

        $stats['professores_mesclados'] += $this->consolidarProfessoresPorEscola($dryRun);

        return $stats;
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function gruposDuplicados(): Collection
    {
        $servidores = Servidor::query()
            ->orderBy('id')
            ->get();

        $grupos = collect();

        foreach ($this->agruparPorCpf($servidores) as $grupo) {
            $grupos->push($grupo);
        }

        $restantes = $servidores->reject(fn (Servidor $servidor): bool => isset($this->servidoresMesclados[(int) $servidor->id]));

        foreach ($this->agruparPorEmail($restantes) as $grupo) {
            $grupos->push($grupo);
        }

        $restantes = $servidores->reject(fn (Servidor $servidor): bool => isset($this->servidoresMesclados[(int) $servidor->id]));

        foreach ($this->agruparPorNomeMatricula($restantes) as $grupo) {
            $grupos->push($grupo);
        }

        return $grupos;
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function agruparPorCpf(Collection $servidores): Collection
    {
        return $servidores
            ->filter(fn (Servidor $servidor): bool => filled($this->normalizarCpf($servidor->cpf)))
            ->groupBy(fn (Servidor $servidor): string => (string) $this->normalizarCpf($servidor->cpf))
            ->values();
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function agruparPorEmail(Collection $servidores): Collection
    {
        return $servidores
            ->filter(fn (Servidor $servidor): bool => filled($this->normalizarEmail($servidor->email)))
            ->groupBy(fn (Servidor $servidor): string => (string) $this->normalizarEmail($servidor->email))
            ->values();
    }

    /** @return Collection<int, Collection<int, Servidor>> */
    private function agruparPorNomeMatricula(Collection $servidores): Collection
    {
        return $servidores
            ->filter(fn (Servidor $servidor): bool => filled($servidor->nome) && filled($servidor->matricula))
            ->groupBy(fn (Servidor $servidor): string => $this->normalizarNome((string) $servidor->nome).'|'.Str::lower(trim((string) $servidor->matricula)))
            ->values();
    }

    /** @return array{pessoas_removidas: int, vinculos_realocados: int, professores_mesclados: int} */
    private function mesclarServidores(Servidor $canonica, Servidor $duplicata, bool $dryRun): array
    {
        $stats = [
            'pessoas_removidas' => 0,
            'vinculos_realocados' => 0,
            'professores_mesclados' => 0,
        ];

        if ((int) $canonica->id === (int) $duplicata->id) {
            return $stats;
        }

        if ($dryRun) {
            $stats['pessoas_removidas'] = 1;
            $stats['vinculos_realocados'] = $duplicata->servidorFuncoes()->count();
            $stats['professores_mesclados'] = $duplicata->professores()->count();

            return $stats;
        }

        DB::transaction(function () use ($canonica, $duplicata, &$stats): void {
            $canonica = $canonica->fresh();
            $duplicata = $duplicata->fresh();

            $this->preencherCamposVazios($canonica, $duplicata);

            foreach ($duplicata->servidorFuncoes as $vinculo) {
                if ($this->vinculoConflitaComCanonica($canonica, $vinculo)) {
                    $vinculo->update([
                        'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                        'data_fim' => now()->toDateString(),
                    ]);

                    continue;
                }

                $vinculo->update(['servidor_id' => $canonica->id]);
                $stats['vinculos_realocados']++;
            }

            foreach ($duplicata->professores as $professor) {
                $existente = $this->professorEquivalenteNaCanonica($canonica, $professor);

                if ($existente) {
                    $this->realocarReferenciasProfessor($professor, $existente);
                    $professor->delete();
                    $stats['professores_mesclados']++;
                } else {
                    $professor->update(['servidor_id' => $canonica->id]);
                }
            }

            $stats['professores_mesclados'] += $this->consolidarProfessoresDaPessoa($canonica);

            $duplicata->delete();
            $stats['pessoas_removidas'] = 1;
            $this->servidoresMesclados[(int) $duplicata->id] = true;
        });

        return $stats;
    }

    private function preencherCamposVazios(Servidor $canonica, Servidor $duplicata): void
    {
        $campos = ['cpf', 'email', 'telefone', 'user_id', 'matricula', 'id_escola', 'setor_id'];

        $updates = [];

        foreach ($campos as $campo) {
            if (blank($canonica->{$campo}) && filled($duplicata->{$campo})) {
                $updates[$campo] = $duplicata->{$campo};
            }
        }

        if ($updates !== []) {
            $canonica->update($updates);
        }
    }

    private function vinculoConflitaComCanonica(Servidor $canonica, ServidorFuncaoAdministrativa $vinculo): bool
    {
        return $canonica->servidorFuncoesAtivas()
            ->where('funcao_administrativa_id', $vinculo->funcao_administrativa_id)
            ->when(
                filled($vinculo->id_escola),
                fn ($query) => $query->where('id_escola', $vinculo->id_escola)
            )
            ->when(
                filled($vinculo->setor_id),
                fn ($query) => $query->where('setor_id', $vinculo->setor_id)
            )
            ->when(
                filled($vinculo->matricula),
                fn ($query) => $query->where('matricula', $vinculo->matricula)
            )
            ->exists();
    }

    private function consolidarProfessoresPorEscola(bool $dryRun): int
    {
        $mesclados = 0;

        Servidor::query()
            ->whereHas('professores')
            ->orderBy('id')
            ->chunkById(100, function ($servidores) use ($dryRun, &$mesclados): void {
                foreach ($servidores as $servidor) {
                    if ($dryRun) {
                        $mesclados += max(0, $servidor->professores()->count() - $servidor->professores()->distinct('id_escola')->count('id_escola'));

                        continue;
                    }

                    $mesclados += $this->consolidarProfessoresDaPessoa($servidor);
                }
            });

        return $mesclados;
    }

    private function professorEquivalenteNaCanonica(Servidor $canonica, Professor $professor): ?Professor
    {
        $query = $canonica->professores();

        if (filled($professor->id_escola)) {
            $query->where('id_escola', $professor->id_escola);
        }

        if (filled($professor->matricula)) {
            $query->where('matricula', $professor->matricula);
        }

        return $query->first();
    }

    private function consolidarProfessoresDaPessoa(Servidor $servidor): int
    {
        $mesclados = 0;

        $grupos = $servidor->professores()
            ->orderBy('id')
            ->get()
            ->groupBy(fn (Professor $professor): string => (string) ($professor->id_escola ?? 'sem-escola'));

        foreach ($grupos as $professores) {
            if ($professores->count() < 2) {
                continue;
            }

            $canonica = $professores->sortBy('id')->first();

            foreach ($professores->reject(fn (Professor $professor): bool => (int) $professor->id === (int) $canonica->id) as $duplicata) {
                $this->realocarReferenciasProfessor($duplicata, $canonica);
                $duplicata->delete();
                $mesclados++;
            }
        }

        return $mesclados;
    }

    private function realocarReferenciasProfessor(Professor $duplicata, Professor $canonica): void
    {
        if (
            Schema::hasColumn('professores', 'servidor_funcao_administrativa_id')
            && blank($canonica->servidor_funcao_administrativa_id)
            && filled($duplicata->servidor_funcao_administrativa_id)
        ) {
            $canonica->update([
                'servidor_funcao_administrativa_id' => $duplicata->servidor_funcao_administrativa_id,
            ]);
        }

        $tabelas = [
            ['turma_componente_professor', 'professor_id'],

        ];

        foreach ($tabelas as [$tabela, $coluna]) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, $coluna)) {
                continue;
            }

            DB::table($tabela)
                ->where($coluna, $duplicata->id)
                ->update([$coluna => $canonica->id]);
        }
    }

    private function normalizarCpf(?string $cpf): ?string
    {
        if (blank($cpf)) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $cpf);

        return filled($digits) ? $digits : null;
    }

    private function normalizarEmail(?string $email): ?string
    {
        if (blank($email)) {
            return null;
        }

        return Str::lower(trim($email));
    }

    private function normalizarNome(string $nome): string
    {
        return Str::of($nome)->squish()->lower()->toString();
    }
}