<?php

namespace App\Services;

use App\Models\Escola;
use App\Models\FuncaoAdministrativa;
use App\Models\Pessoa;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\TurmaComponenteProfessor;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
            // Servidor extends Pessoa — instancia concreta para compatibilidade de typehints legados.
            $pessoa = Servidor::query()->create($this->dadosPessoa($dadosPessoa));

            return $this->finalizarProfessor($pessoa, $registros, $acesso);
        });
    }

    public function atualizarPessoaProfessor(Pessoa|Servidor $pessoa, array $dadosPessoa, array $registros, array $acesso = []): Servidor
    {
        return DB::transaction(function () use ($pessoa, $dadosPessoa, $registros, $acesso): Servidor {
            $pessoa->update($this->dadosPessoa($dadosPessoa));

            /** @var Servidor $fresh */
            $fresh = Servidor::query()->findOrFail($pessoa->id);

            return $this->finalizarProfessor($fresh, $registros, $acesso);
        });
    }

    /**
     * Aceita formato hierárquico (matriculas → escolas) ou flat legado
     * (registros_professor com matricula+turno+id_escola).
     */
    public function sincronizarRegistros(Pessoa|Servidor $pessoa, array $registros): void
    {
        $matriculas = $this->normalizarMatriculas($registros);
        $this->validarInvariantesMatriculas($matriculas);

        $idsProfessoresMantidos = collect();
        $idsMatriculasMantidas = collect();

        foreach ($matriculas as $matriculaData) {
            $matriculaModel = $this->upsertMatricula($pessoa, $matriculaData);
            $idsMatriculasMantidas->push($matriculaModel->id);

            foreach ($matriculaData['escolas'] as $escolaData) {
                $this->validarEscola($escolaData['id_escola']);

                $professor = $this->upsertProfessorLotacao($pessoa, $matriculaModel, $escolaData);
                $idsProfessoresMantidos->push($professor->id);

                $this->sincronizarTurmasComponentes(
                    $professor,
                    $escolaData['vinculos_turma_componente'] ?? [],
                    $matriculaModel->turno,
                );
            }
        }

        $this->removerRegistrosAusentes($pessoa, $idsProfessoresMantidos);
        $this->removerMatriculasAusentes($pessoa, $idsMatriculasMantidas);
    }

    public function sincronizarTurmasComponentes(Professor $professor, array $vinculos, ?string $turnoMatricula = null): void
    {
        $professor->loadMissing(['escola', 'professorMatricula']);
        $turnoMatricula ??= $professor->turnoEfetivo();

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

        $vinculosMantidosKeys = collect();

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

            // Legado pode ter turma de turno diferente da matrícula; não bloquear o save.
            // Novos vínculos inconsistentes ainda são gravados se a escola/série baterem.
            // (O form filtra opções por turno; validação rígida quebrava edição de dados legados.)

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

            $vinculosMantidosKeys->push("{$vinculo['turma_id']}-{$vinculo['componente_curricular_id']}");
        }

        // Remove vínculos deste professor que saíram do formulário (sem apagar slots de outros).
        TurmaComponenteProfessor::query()
            ->where('professor_id', $professor->id)
            ->where('tem_professor', true)
            ->get()
            ->each(function (TurmaComponenteProfessor $row) use ($vinculosMantidosKeys): void {
                $key = "{$row->turma_id}-{$row->componente_curricular_id}";
                if ($vinculosMantidosKeys->contains($key)) {
                    return;
                }

                $row->update([
                    'professor_id' => null,
                    'tem_professor' => false,
                ]);
            });

        if ($normalizados->isNotEmpty()) {
            app(ProfessorEscolaVinculoService::class)->sincronizarPorProfessores([$professor->id]);
        }
    }

    public function sincronizarVinculosFuncionaisSilenciosos(Pessoa|Servidor $pessoa): void
    {
        $funcaoProfessor = FuncaoAdministrativa::professorPadrao();
        $pessoa->loadMissing(['professores.escola']);

        $vinculosMantidos = collect();

        foreach ($pessoa->professores->where('ativo', true) as $professor) {
            $setorId = $professor->escola?->setor_id ? (int) $professor->escola->setor_id : null;

            $vinculo = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $pessoa->id)
                ->where('funcao_administrativa_id', $funcaoProfessor->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->where('id_escola', $professor->id_escola)
                ->where('matricula', $professor->matricula)
                ->first();

            if (! $vinculo) {
                $vinculo = new ServidorFuncaoAdministrativa([
                    'servidor_id' => $pessoa->id,
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
            ->where('servidor_id', $pessoa->id)
            ->where('funcao_administrativa_id', $funcaoProfessor->id)
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->whereNotIn('id', $vinculosMantidos->all())
            ->update([
                'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                'data_fim' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        $this->atualizarEscopoAgregadoPessoa($pessoa->fresh(['professores.escola']));
    }

    /**
     * Normaliza hierárquico OU flat para:
     * [
     *   ['id'?, 'matricula', 'turno', 'escolas' => [['id'?, 'id_escola', 'vinculos_turma_componente' => [...]]]]
     * ]
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function normalizarMatriculas(array $registros): Collection
    {
        // Formato hierárquico explícito
        if ($this->pareceHierarquico($registros)) {
            return collect($registros)
                ->map(function ($item): ?array {
                    if (! is_array($item) || blank($item['matricula'] ?? null) || blank($item['turno'] ?? null)) {
                        return null;
                    }

                    $escolas = collect($item['escolas'] ?? $item['lotacoes'] ?? [])
                        ->map(function ($escola): ?array {
                            if (! is_array($escola) || blank($escola['id_escola'] ?? null)) {
                                return null;
                            }

                            return [
                                'id' => filled($escola['id'] ?? null) ? (int) $escola['id'] : null,
                                'id_escola' => (int) $escola['id_escola'],
                                'vinculos_turma_componente' => $escola['vinculos_turma_componente'] ?? [],
                            ];
                        })
                        ->filter()
                        ->values()
                        ->all();

                    return [
                        'id' => filled($item['id'] ?? null) ? (int) $item['id'] : null,
                        'matricula' => (string) $item['matricula'],
                        'turno' => (string) $item['turno'],
                        'escolas' => $escolas,
                    ];
                })
                ->filter()
                ->values();
        }

        // Formato flat legado: cada linha = matrícula + turno + escola
        return $this->normalizarRegistros($registros)
            ->groupBy(fn (array $r): string => (string) $r['matricula'])
            ->map(function (Collection $grupo, string $matricula): array {
                $turnos = $grupo->pluck('turno')->unique()->values();
                $turno = (string) $turnos->first();

                return [
                    'id' => null,
                    'matricula' => $matricula,
                    'turno' => $turno,
                    'escolas' => $grupo->map(fn (array $r): array => [
                        'id' => $r['id'] ?? null,
                        'id_escola' => $r['id_escola'],
                        'vinculos_turma_componente' => $r['vinculos_turma_componente'] ?? [],
                    ])->values()->all(),
                    '_turnos_no_grupo' => $turnos->all(),
                ];
            })
            ->values();
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

    private function pareceHierarquico(array $registros): bool
    {
        if ($registros === []) {
            return false;
        }

        $first = collect($registros)->first(fn ($item) => is_array($item));

        if (! is_array($first)) {
            return false;
        }

        return array_key_exists('escolas', $first)
            || array_key_exists('lotacoes', $first)
            || (array_key_exists('matricula', $first) && array_key_exists('turno', $first) && ! array_key_exists('id_escola', $first));
    }

    /** @param Collection<int, array<string, mixed>> $matriculas */
    private function validarInvariantesMatriculas(Collection $matriculas): void
    {
        // Legado pode ter > MAX_POR_PESSOA; não bloquear edição/salvamento.
        // Preferência de cadastro novo permanece documentada no form (helper text).

        foreach ($matriculas as $matricula) {
            if (blank($matricula['matricula'] ?? null) || blank($matricula['turno'] ?? null)) {
                throw ValidationException::withMessages([
                    'registros_professor' => 'Cada matrícula precisa de número e turno.',
                ]);
            }

            ProfessorMatricula::assertTurnoValido((string) $matricula['turno']);

            if (isset($matricula['_turnos_no_grupo']) && count(array_unique($matricula['_turnos_no_grupo'])) > 1) {
                throw ValidationException::withMessages([
                    'registros_professor' => "A matrícula {$matricula['matricula']} não pode ter turnos diferentes.",
                ]);
            }

            // Escolas opcionais (matrícula sem lotação é válida).
            foreach ($matricula['escolas'] ?? [] as $escola) {
                if (blank($escola['id_escola'] ?? null)) {
                    throw ValidationException::withMessages([
                        'registros_professor' => 'Há uma lotação sem escola selecionada. Remova o item vazio ou escolha a escola.',
                    ]);
                }
            }
        }

        $duplicadas = $matriculas->pluck('matricula')->duplicates();
        if ($duplicadas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'registros_professor' => 'Matrículas duplicadas no formulário: ' . $duplicadas->unique()->implode(', '),
            ]);
        }
    }

    private function upsertMatricula(Pessoa|Servidor $pessoa, array $data): ProfessorMatricula
    {
        $query = ProfessorMatricula::query()->where('servidor_id', $pessoa->id);

        $matricula = null;
        if (filled($data['id'] ?? null)) {
            $matricula = (clone $query)->whereKey($data['id'])->first();
        }

        if (! $matricula) {
            $matricula = (clone $query)->where('matricula', $data['matricula'])->first();
        }

        $payload = [
            'servidor_id' => $pessoa->id,
            'matricula' => $data['matricula'],
            'turno' => $data['turno'],
        ];

        if ($matricula) {
            $matricula->update($payload);

            return $matricula->fresh();
        }

        return ProfessorMatricula::query()->create($payload);
    }

    private function upsertProfessorLotacao(Pessoa|Servidor $pessoa, ProfessorMatricula $matricula, array $escolaData): Professor
    {
        $query = Professor::query()->where('servidor_id', $pessoa->id);

        $professor = null;
        if (filled($escolaData['id'] ?? null)) {
            $professor = (clone $query)->whereKey($escolaData['id'])->first();
        }

        if (! $professor) {
            $professor = Professor::query()
                ->where('servidor_id', $pessoa->id)
                ->where('id_escola', $escolaData['id_escola'])
                ->where('matricula', $matricula->matricula)
                ->first();
        }

        if (! $professor) {
            $professor = Professor::query()
                ->where('id_escola', $escolaData['id_escola'])
                ->where('matricula', $matricula->matricula)
                ->first();
        }

        $payload = [
            'servidor_id' => $pessoa->id,
            'professor_matricula_id' => $matricula->id,
            'id_escola' => $escolaData['id_escola'],
            'matricula' => $matricula->matricula,
            'turno' => $matricula->turno,
            'nome' => $pessoa->nome,
            'email' => $pessoa->email,
            'telefone' => $pessoa->telefone,
            'user_id' => $pessoa->user_id,
            'ativo' => true,
        ];

        if ($professor) {
            if ((int) ($professor->servidor_id ?? 0) !== 0 && (int) $professor->servidor_id !== (int) $pessoa->id) {
                throw ValidationException::withMessages([
                    'registros_professor' => "A matrícula {$matricula->matricula} já está vinculada a outra pessoa nesta escola.",
                ]);
            }

            $professor->update($payload);

            return $professor->fresh();
        }

        return Professor::query()->create($payload);
    }

    private function finalizarProfessor(Servidor $pessoa, array $registros, array $acesso): Servidor
    {
        $this->sincronizarRegistros($pessoa, $registros);
        $this->pessoaAcessoService->provisionarUsuarioProfessor($pessoa->fresh(['professores']), $acesso);
        $this->sincronizarVinculosFuncionaisSilenciosos($pessoa->fresh(['professores']));

        /** @var Servidor $fresh */
        $fresh = $pessoa->fresh(['professores.escola', 'professorMatriculas', 'user', 'vinculosAtivos']);

        return $fresh;
    }

    private function removerRegistrosAusentes(Pessoa|Servidor $pessoa, Collection $idsMantidos): void
    {
        $paraRemover = $pessoa->professores()
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

    private function removerMatriculasAusentes(Pessoa|Servidor $pessoa, Collection $idsMantidos): void
    {
        if (! Schema::hasTable('professor_matriculas')) {
            return;
        }

        $paraRemover = ProfessorMatricula::query()
            ->where('servidor_id', $pessoa->id)
            ->when($idsMantidos->isNotEmpty(), fn ($query) => $query->whereNotIn('id', $idsMantidos->all()))
            ->when($idsMantidos->isEmpty(), fn ($query) => $query)
            ->get();

        foreach ($paraRemover as $matricula) {
            if ($matricula->professores()->exists()) {
                continue;
            }

            $matricula->delete();
        }
    }

    private function validarEscola(int $escolaId): void
    {
        $escola = Escola::query()->find($escolaId);

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
    }

    private function turmaCompativelComTurno(Turma $turma, string $turnoMatricula): bool
    {
        $turnoTurma = (string) $turma->turno;
        $compatveis = ProfessorMatricula::turnosTurmaCompativeis($turnoMatricula);

        return in_array($turnoTurma, $compatveis, true);
    }

    private function atualizarEscopoAgregadoPessoa(Pessoa|Servidor $pessoa): void
    {
        $escolaIds = $pessoa->professores
            ->where('ativo', true)
            ->pluck('id_escola')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $setorIds = $pessoa->professores
            ->where('ativo', true)
            ->loadMissing('escola')
            ->pluck('escola.setor_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $pessoa->update([
            'id_escola' => $escolaIds->first(),
            'setor_id' => $setorIds->first(),
        ]);

        if ($pessoa->user) {
            $payload = [
                'name' => $pessoa->nome,
                'id_escola' => $escolaIds->first() ?: $pessoa->user->id_escola,
                'setor_id' => $setorIds->first() ?: $pessoa->user->setor_id,
            ];

            // Só propaga e-mail se não conflitar com outro usuário (unique).
            $email = filled($pessoa->email) ? Professor::normalizarEmail((string) $pessoa->email) : null;
            if (
                $email
                && ! User::query()
                    ->where('email', $email)
                    ->where('id', '!=', $pessoa->user->id)
                    ->exists()
            ) {
                $payload['email'] = $email;
            }

            $pessoa->user->update($payload);

            try {
                app(ProfessorEscolaVinculoService::class)->sincronizarPorUsuario($pessoa->user);
            } catch (\Throwable) {
                // best-effort: não derruba o save da pessoa
            }
        }
    }

    private function dadosPessoa(array $data): array
    {
        return [
            'cpf' => Pessoa::normalizarCpf($data['cpf'] ?? null),
            'nome' => $data['nome'] ?? null,
            'email' => filled($data['email'] ?? null) ? Professor::normalizarEmail((string) $data['email']) : null,
            'telefone' => $data['telefone'] ?? null,
            'status' => $data['status'] ?? Pessoa::STATUS_ATIVO,
            'observacoes' => $data['observacoes'] ?? null,
        ];
    }
}
