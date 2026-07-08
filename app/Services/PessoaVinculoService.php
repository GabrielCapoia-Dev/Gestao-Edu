<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Setor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PessoaVinculoService
{
    public function __construct(private readonly ServidorService $servidorService) {}

    public function criarPessoaComVinculos(array $dadosPessoa, array $vinculos = []): Servidor
    {
        return DB::transaction(function () use ($dadosPessoa, $vinculos): Servidor {
            $servidor = Servidor::query()->create($this->dadosPessoa($dadosPessoa));
            $this->sincronizarVinculos($servidor, $vinculos);

            return $servidor->fresh(['vinculosAtivas.funcaoAdministrativa', 'vinculosAtivas.escola', 'vinculosAtivas.setor', 'professores']);
        });
    }

    public function atualizarPessoaComVinculos(Servidor $servidor, array $dadosPessoa, array $vinculos = []): Servidor
    {
        return DB::transaction(function () use ($servidor, $dadosPessoa, $vinculos): Servidor {
            $servidor->update($this->dadosPessoa($dadosPessoa));
            $this->sincronizarVinculos($servidor->fresh(), $vinculos);

            return $servidor->fresh(['vinculosAtivas.funcaoAdministrativa', 'vinculosAtivas.escola', 'vinculosAtivas.setor', 'professores']);
        });
    }

    public function sincronizarVinculos(Servidor $servidor, array $vinculos): void
    {
        $normalizados = $this->normalizarVinculos($vinculos);

        foreach ($normalizados as $vinculo) {
            $this->validarVinculo($vinculo);

            $this->servidorService->vincularFuncao($servidor, (int) $vinculo['funcao_administrativa_id'], [
                'matricula' => $vinculo['matricula'] ?? null,
                'setor_id' => $vinculo['setor_id'] ?? null,
                'id_escola' => $vinculo['id_escola'] ?? null,
                'portaria' => $vinculo['portaria'] ?? null,
                'turma_ids' => $vinculo['turma_ids'] ?? [],
                'origem' => $vinculo['origem'] ?? 'manual',
            ]);
        }

        $funcaoIds = $normalizados->pluck('funcao_administrativa_id')->map(fn ($id): int => (int) $id);
        $ativas = $servidor->funcoesAtivas()
            ->pluck('funcao_administrativa.id')
            ->map(fn ($id): int => (int) $id);

        foreach ($ativas->diff($funcaoIds) as $funcaoId) {
            $this->servidorService->removerFuncao($servidor, $funcaoId);
        }
    }

    public function validarVinculo(array $vinculo): void
    {
        $setorId = $vinculo['setor_id'] ?? null;

        if (blank($setorId)) {
            throw ValidationException::withMessages([
                'vinculos_funcionais' => 'Cada vínculo precisa informar o setor.',
            ]);
        }

        $setor = Setor::query()->find($setorId);

        if (! $setor) {
            throw ValidationException::withMessages([
                'vinculos_funcionais' => 'Setor informado no vínculo é inválido.',
            ]);
        }

        if ($setor->exigeVinculoEscola() && blank($vinculo['id_escola'] ?? null)) {
            throw ValidationException::withMessages([
                'vinculos_funcionais' => "O setor {$setor->nome} exige vínculo com escola ou CMEI.",
            ]);
        }

        if (blank($vinculo['matricula'] ?? null)) {
            throw ValidationException::withMessages([
                'vinculos_funcionais' => 'Cada vínculo precisa informar a matrícula.',
            ]);
        }
    }

    /** @return Collection<int, array<string, mixed>> */
    public function normalizarVinculos(array $vinculos): Collection
    {
        return collect($vinculos)
            ->map(function ($vinculo): ?array {
                if (! is_array($vinculo)) {
                    if (! filled($vinculo)) {
                        return null;
                    }

                    return [
                        'funcao_administrativa_id' => (int) $vinculo,
                        'matricula' => null,
                        'setor_id' => null,
                        'id_escola' => null,
                        'portaria' => null,
                        'turma_ids' => [],
                        'origem' => 'manual',
                    ];
                }

                $funcaoId = $vinculo['funcao_administrativa_id'] ?? $vinculo['id'] ?? null;

                if (! filled($funcaoId)) {
                    return null;
                }

                return [
                    'funcao_administrativa_id' => (int) $funcaoId,
                    'matricula' => filled($vinculo['matricula'] ?? null) ? (string) $vinculo['matricula'] : null,
                    'setor_id' => filled($vinculo['setor_id'] ?? null) ? (int) $vinculo['setor_id'] : null,
                    'id_escola' => filled($vinculo['id_escola'] ?? null) ? (int) $vinculo['id_escola'] : null,
                    'portaria' => filled($vinculo['portaria'] ?? null) ? (string) $vinculo['portaria'] : null,
                    'turma_ids' => $vinculo['turma_ids'] ?? $vinculo['turmas'] ?? [],
                    'origem' => $vinculo['origem'] ?? 'manual',
                ];
            })
            ->filter()
            ->values();
    }

    private function dadosPessoa(array $data): array
    {
        return [
            'cpf' => $data['cpf'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'nome' => $data['nome'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'status' => $data['status'] ?? Servidor::STATUS_ATIVO,
            'observacoes' => $data['observacoes'] ?? null,
            'id_escola' => $data['id_escola'] ?? null,
            'setor_id' => $data['setor_id'] ?? null,
            'matricula' => $data['matricula'] ?? null,
        ];
    }
}