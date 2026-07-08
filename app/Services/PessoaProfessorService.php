<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PessoaProfessorService
{
    public function __construct(
        private readonly ServidorService $servidorService,
        private readonly PessoaAcessoService $pessoaAcessoService,
    ) {}

    public function criarPessoaProfessor(array $dadosPessoa, array $registros, array $acesso = []): Servidor
    {
        return DB::transaction(function () use ($dadosPessoa, $registros, $acesso): Servidor {
            $servidor = Servidor::query()->create($this->dadosPessoa($dadosPessoa));

            return $this->finalizarProfessor($servidor, $registros, $acesso);
        });
    }

    public function atualizarPessoaProfessor(Servidor $servidor, array $dadosPessoa, array $registros, array $acesso = []): Servidor
    {
        return DB::transaction(function () use ($servidor, $dadosPessoa, $registros, $acesso): Servidor {
            $servidor->update($this->dadosPessoa($dadosPessoa));

            return $this->finalizarProfessor($servidor->fresh(), $registros, $acesso);
        });
    }

    public function sincronizarRegistros(Servidor $servidor, array $registros): void
    {
        $normalizados = $this->normalizarRegistros($registros);
        $idsMantidos = collect();

        foreach ($normalizados as $registro) {
            $this->validarRegistro($registro);

            $professor = $this->upsertProfessor($servidor, $registro);
            $idsMantidos->push($professor->id);

            $this->sincronizarTurmasComponentes(
                $professor,
                $registro['vinculos_turma_componente'] ?? [],
            );
        }

        $this->removerRegistrosAusentes($servidor, $idsMantidos);
    }

    public function sincronizarTurmasComponentes(Professor $professor, array $vinculos): void
    {
        $professor->loadMissing('escola');

        $normalizados = collect($vinculos)
            ->map(function ($vinculo): ?array {
                if (! is_array($vinculo)) {
                    return null;
                }

                $turmaId = $vinculo['turma_id'] ?? null;
                $componenteId = $vinculo['componente_curricular_id'] ?? null;

                if (! filled($turmaId) || ! filled($componenteId)) {
                    return null;
                }

                return [
                    'turma_id' => (int) $turmaId,
                    'componente_curricular_id' => (int) $componenteId,
                ];
            })
            ->filter()
            ->unique(fn (array $item): string => "{$item['turma_id']}-{$item['componente_curricular_id']}")
            ->values();

        foreach ($normalizados as $vinculo) {
            $turma = Turma::query()
                ->with('serie.componentesCurriculares')
                ->find($vinculo['turma_id']);

            if (! $turma) {
                throw ValidationException::withMessages([
                    'registros_professor' => 'Turma informada é inválida.',
                ]);
            }

            if ((int) $turma->id_escola !== (int) $professor->id_escola) {
                throw ValidationException::withMessages([
                    'registros_professor' => 'A turma selecionada não pertence à escola do registro.',
                ]);
            }

            $componentesDaSerie = $turma->serie?->componentesCurriculares?->pluck('id')->map(fn ($id): int => (int) $id) ?? collect();

            if (! $componentesDaSerie->contains($vinculo['componente_curricular_id'])) {
                throw ValidationException::withMessages([
                    'registros_professor' => 'O componente selecionado não pertence à série da turma.',
                ]);
            }

            TurmaComponenteProfessor::query()->updateOrCreate(
                [
                    'turma_id' => $vinculo['turma_id'],
                    'componente_curricular_id' => $vinculo['componente_curricular_id'],
                ],
                [
                    'professor_id' => $professor->id,
                    'tem_professor' => true,
                ],
            );
        }

        if ($normalizados->isNotEmpty()) {
            app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores([$professor->id]);
        }
    }

    public function sincronizarVinculosFuncionaisSilenciosos(Servidor $servidor): void
    {
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();
        $servidor->loadMissing(['professores.escola']);

        $vinculosMantidos = collect();

        foreach ($servidor->professores->where('ativo', true) as $professor) {
            $setorId = $professor->escola?->setor_id ? (int) $professor->escola->setor_id : null;

            $vinculo = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcaoProfessor->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->where('id_escola', $professor->id_escola)
                ->where('matricula', $professor->matricula)
                ->first();

            if (! $vinculo) {
                $vinculo = new ServidorFuncaoAdministrativa([
                    'servidor_id' => $servidor->id,
                    'funcao_administrativa_id' => $funcaoProfessor->id,
                    'origem' => 'professor',
                ]);
            }

            $vinculo->fill([
                'matricula' => $professor->matricula,
                'id_escola' => $professor->id_escola,
                'setor_id' => $setorId,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'data_fim' => null,
            ]);
            $vinculo->save();

            if ((int) ($professor->servidor_funcao_administrativa_id ?? 0) !== (int) $vinculo->id) {
                $professor->update(['servidor_funcao_administrativa_id' => $vinculo->id]);
            }

            $vinculosMantidos->push($vinculo->id);
        }

        ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $servidor->id)
            ->where('funcao_administrativa_id', $funcaoProfessor->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotIn('id', $vinculosMantidos->all())
            ->update([
                'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                'data_fim' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        $this->atualizarEscopoAgregadoServidor($servidor->fresh(['professores.escola']));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function normalizarRegistros(array $registros): Collection
    {
        return collect($registros)
            ->map(function ($registro): ?array {
                if (! is_array($registro)) {
                    return null;
                }

                if (blank($registro['matricula'] ?? null) || blank($registro['turno'] ?? null) || blank($registro['id_escola'] ?? null)) {
                    return null;
                }

                return [
                    'id' => filled($registro['id'] ?? null) ? (int) $registro['id'] : null,
                    'matricula' => (string) $registro['matricula'],
                    'turno' => (string) $registro['turno'],
                    'id_escola' => (int) $registro['id_escola'],
                    'vinculos_turma_componente' => $registro['vinculos_turma_componente'] ?? [],
                ];
            })
            ->filter()
            ->values();
    }

    private function finalizarProfessor(Servidor $servidor, array $registros, array $acesso): Servidor
    {
        $this->sincronizarRegistros($servidor, $registros);
        $this->pessoaAcessoService->provisionarUsuarioProfessor($servidor->fresh(['professores']), $acesso);
        $this->sincronizarVinculosFuncionaisSilenciosos($servidor->fresh(['professores']));

        return $servidor->fresh(['professores.escola', 'user', 'vinculosAtivos']);
    }

    private function upsertProfessor(Servidor $servidor, array $registro): Professor
    {
        $query = Professor::query()->where('servidor_id', $servidor->id);

        if (filled($registro['id'] ?? null)) {
            $professor = $query->whereKey($registro['id'])->first();
        } else {
            $professor = null;
        }

        if (! $professor) {
            $professor = Professor::query()
                ->where('id_escola', $registro['id_escola'])
                ->where('matricula', $registro['matricula'])
                ->first();
        }

        $payload = [
            'servidor_id' => $servidor->id,
            'id_escola' => $registro['id_escola'],
            'matricula' => $registro['matricula'],
            'turno' => $registro['turno'],
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'telefone' => $servidor->telefone,
            'user_id' => $servidor->user_id,
            'ativo' => true,
        ];

        if ($professor) {
            if ((int) ($professor->servidor_id ?? 0) !== 0 && (int) $professor->servidor_id !== (int) $servidor->id) {
                throw ValidationException::withMessages([
                    'registros_professor' => "A matrícula {$registro['matricula']} já está vinculada a outra pessoa nesta escola.",
                ]);
            }

            $professor->update($payload);

            return $professor->fresh();
        }

        return Professor::query()->create($payload);
    }

    private function removerRegistrosAusentes(Servidor $servidor, Collection $idsMantidos): void
    {
        $paraRemover = $servidor->professores()
            ->when($idsMantidos->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $idsMantidos->all()))
            ->when($idsMantidos->isEmpty(), fn ($query) => $query)
            ->get();

        foreach ($paraRemover as $professor) {
            if ($this->servidorService->professorPossuiVinculosPedagogicos($professor)) {
                throw ValidationException::withMessages([
                    'registros_professor' => 'Não é possível remover um registro com vínculos pedagógicos ativos.',
                ]);
            }

            $professor->delete();
        }
    }

    private function validarRegistro(array $registro): void
    {
        $escola = Escola::query()->find($registro['id_escola'] ?? null);

        if (! $escola) {
            throw ValidationException::withMessages([
                'registros_professor' => 'Escola informada no registro é inválida.',
            ]);
        }

        if (blank($escola->setor_id)) {
            throw ValidationException::withMessages([
                'registros_professor' => "A escola {$escola->nome} não possui setor vinculado.",
            ]);
        }

        if (! array_key_exists($registro['turno'], Professor::turnosOptions())) {
            throw ValidationException::withMessages([
                'registros_professor' => 'Turno informado é inválido.',
            ]);
        }
    }

    private function atualizarEscopoAgregadoServidor(Servidor $servidor): void
    {
        $escolaIds = $servidor->professores
            ->where('ativo', true)
            ->pluck('id_escola')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $setorIds = $servidor->professores
            ->where('ativo', true)
            ->loadMissing('escola')
            ->pluck('escola.setor_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $servidor->update([
            'id_escola' => $escolaIds->first(),
            'setor_id' => $setorIds->first(),
        ]);

        if ($servidor->user) {
            $servidor->user->update([
                'id_escola' => $escolaIds->first(),
                'setor_id' => $setorIds->first(),
            ]);

            app(ProfessorEscolaVinculoService::class)->sincronizarPorUsuario($servidor->user);
        }
    }

    private function dadosPessoa(array $data): array
    {
        return [
            'cpf' => $data['cpf'] ?? null,
            'nome' => $data['nome'] ?? null,
            'email' => filled($data['email'] ?? null) ? Professor::normalizarEmail((string) $data['email']) : null,
            'telefone' => $data['telefone'] ?? null,
            'status' => $data['status'] ?? Servidor::STATUS_ATIVO,
            'observacoes' => $data['observacoes'] ?? null,
        ];
    }
}