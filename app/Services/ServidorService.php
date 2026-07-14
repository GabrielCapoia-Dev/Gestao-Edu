<?php

namespace App\Services;

use App\Models\FuncaoAdministrativa;
use App\Models\Professor;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class ServidorService
{
    public function criarServidorComFuncoes(array $data, array $vinculos = []): Servidor
    {
        if ($this->fluxoEquipeGestora($data, $vinculos)) {
            return app(PessoaEquipeGestoraService::class)->criarPessoaEquipeGestora(
                $data,
                $this->dadosEquipeGestora($data, $vinculos),
            );
        }

        if ($this->fluxoProfessor($data, $vinculos)) {
            return app(PessoaProfessorService::class)->criarPessoaProfessor(
                $data,
                $vinculos['matriculas_professor']
                    ?? $vinculos['registros_professor']
                    ?? $data['matriculas_professor']
                    ?? $data['registros_professor']
                    ?? $vinculos,
                $this->dadosAcesso($data),
            );
        }

        return app(PessoaVinculoService::class)->criarPessoaComVinculos($data, $vinculos);
    }

    public function atualizarServidorComFuncoes(Servidor $servidor, array $data, array $vinculos = []): Servidor
    {
        if ($this->fluxoEquipeGestora($data, $vinculos)) {
            $dadosGestao = $this->dadosEquipeGestora($data, $vinculos);

            if ($servidor->professores()->where('ativo', true)->exists()) {
                return app(PessoaEquipeGestoraService::class)->converterProfessorParaEquipeGestora(
                    $servidor,
                    $dadosGestao,
                    $data,
                );
            }

            return app(PessoaEquipeGestoraService::class)->atualizarPessoaEquipeGestora(
                $servidor,
                $data,
                $dadosGestao,
            );
        }

        if ($this->fluxoProfessor($data, $vinculos)) {
            $registros = $vinculos['matriculas_professor']
                ?? $vinculos['registros_professor']
                ?? $data['matriculas_professor']
                ?? $data['registros_professor']
                ?? $vinculos;

            if ($servidor->vinculosAtivos()
                ->whereHas('funcaoAdministrativa', fn ($funcoes) => $funcoes->equipeGestora())
                ->exists()) {
                return app(PessoaEquipeGestoraService::class)->converterEquipeGestoraParaProfessor(
                    $servidor,
                    $data,
                    $registros,
                    $this->dadosAcesso($data),
                );
            }

            return app(PessoaProfessorService::class)->atualizarPessoaProfessor(
                $servidor,
                $data,
                $registros,
                $this->dadosAcesso($data),
            );
        }

        return app(PessoaVinculoService::class)->atualizarPessoaComVinculos($servidor, $data, $vinculos);
    }

    private function fluxoProfessor(array $data, array $vinculos): bool
    {
        $cargo = $data['cargo'] ?? null;

        if ($cargo === 'professor') {
            return true;
        }

        return array_key_exists('registros_professor', $vinculos)
            || array_key_exists('registros_professor', $data)
            || array_key_exists('matriculas_professor', $vinculos)
            || array_key_exists('matriculas_professor', $data);
    }

    private function fluxoEquipeGestora(array $data, array $vinculos): bool
    {
        return ($data['cargo'] ?? null) === 'equipe_gestora'
            || array_key_exists('equipe_gestora', $data)
            || array_key_exists('equipe_gestora', $vinculos);
    }

    private function dadosEquipeGestora(array $data, array $vinculos): array
    {
        $dados = $vinculos['equipe_gestora']
            ?? $data['equipe_gestora']
            ?? $vinculos;

        return is_array($dados) ? [...$data, ...$dados] : $data;
    }

    private function dadosAcesso(array $data): array
    {
        $permissoesExtras = collect($data)
            ->filter(fn ($_, string $key): bool => str_starts_with($key, 'permissions_'))
            ->flatMap(fn ($permissions) => is_array($permissions) ? $permissions : [$permissions])
            ->filter(fn ($permission): bool => filled($permission))
            ->unique()
            ->values()
            ->all();

        return [
            'roles' => $data['roles_adicionais'] ?? $data['roles'] ?? [],
            'roles_adicionais' => $data['roles_adicionais'] ?? $data['roles'] ?? [],
            'email_approved' => $data['email_approved'] ?? true,
            'usar_permissoes_extras' => $data['usar_permissoes_extras'] ?? false,
            'permissoes_extras' => $permissoesExtras,
        ];
    }

    public function sincronizarProfessor(Professor $professor): ?Servidor
    {
        if (! Schema::hasTable('servidores') || ! Schema::hasColumn('professores', 'servidor_id')) {
            return null;
        }

        return DB::transaction(function () use ($professor): Servidor {
            $servidor = $this->criarOuAtualizarServidorDoProfessor($professor);

            $this->vincularFuncao($servidor, FuncaoAdministrativa::professorPadrao(), [
                'origem' => 'professor',
                'id_escola' => $professor->id_escola,
                'setor_id' => $this->setorIdDoProfessor($professor),
            ]);

            return $servidor->fresh(['funcoesAtivas']);
        });
    }

    public function backfillProfessores(): array
    {
        $resultado = [
            'professores_processados' => 0,
            'servidores_criados_ou_atualizados' => 0,
            'funcoes_vinculadas' => 0,
        ];

        Professor::query()
            ->with(['escola'])
            ->orderBy('id')
            ->chunkById(100, function ($professores) use (&$resultado): void {
                foreach ($professores as $professor) {
                    $antes = $professor->servidor_id;
                    $servidor = $this->sincronizarProfessor($professor);

                    $resultado['professores_processados']++;
                    $resultado['servidores_criados_ou_atualizados'] += $servidor ? 1 : 0;
                    $resultado['funcoes_vinculadas'] += $antes || $servidor ? 1 : 0;
                }
            });

        return $resultado;
    }

    public function vincularFuncao(Servidor $servidor, FuncaoAdministrativa|int $funcao, array $contexto = []): ServidorFuncaoAdministrativa
    {
        $funcao = $funcao instanceof FuncaoAdministrativa
            ? $funcao
            : FuncaoAdministrativa::query()->findOrFail($funcao);

        if ($funcao->ehEquipeGestora()) {
            throw ValidationException::withMessages([
                'equipe_gestora' => 'Funções da Equipe Gestora só podem ser alteradas pelo fluxo próprio, que preserva vigência e principais.',
            ]);
        }

        return DB::transaction(function () use ($servidor, $funcao, $contexto): ServidorFuncaoAdministrativa {
            $servidor = $servidor->fresh(['professores', 'escola']);

            if ($funcao->exige_professor) {
                // Preferir escola/matrícula do contexto do vínculo (podem ainda não estar no agregado da pessoa).
                if (filled($contexto['id_escola'] ?? null) && blank($servidor->id_escola)) {
                    $servidor->id_escola = (int) $contexto['id_escola'];
                }
                if (filled($contexto['matricula'] ?? null) && blank($servidor->matricula)) {
                    $servidor->matricula = (string) $contexto['matricula'];
                }
                if (filled($contexto['setor_id'] ?? null) && blank($servidor->setor_id)) {
                    $servidor->setor_id = (int) $contexto['setor_id'];
                }

                $this->garantirProfessorParaServidor($servidor);
            }

            $origem = (string) ($contexto['origem'] ?? 'manual');
            $query = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcao->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);

            if ($origem !== 'manual') {
                $query->where('origem', $origem);
            }

            $vinculo = $query->first() ?? new ServidorFuncaoAdministrativa([
                'servidor_id' => $servidor->id,
                'funcao_administrativa_id' => $funcao->id,
                'origem' => $origem,
            ]);

            $vinculo->fill([
                'matricula' => $contexto['matricula'] ?? $servidor->matricula,
                'id_escola' => $contexto['id_escola'] ?? $servidor->id_escola,
                'setor_id' => $contexto['setor_id'] ?? $servidor->setor_id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'portaria' => array_key_exists('portaria', $contexto) ? $contexto['portaria'] : $vinculo->portaria,
                'data_inicio' => $contexto['data_inicio'] ?? $vinculo->data_inicio,
                'data_fim' => null,
            ]);
            $vinculo->save();

            if (array_key_exists('turma_ids', $contexto) && Schema::hasTable('servidor_funcao_turma')) {
                $turmaIds = collect($contexto['turma_ids'])
                    ->filter(fn ($id): bool => filled($id))
                    ->map(fn ($id): int => (int) $id)
                    ->unique()
                    ->values()
                    ->all();

                $vinculo->turmas()->sync($turmaIds);
            }

            $vinculo = $vinculo->fresh(['turmas']);

            app(PessoaAcessoService::class)->vincularProfessorAoVinculo($vinculo);

            return $vinculo;
        });
    }

    public function removerFuncao(Servidor $servidor, FuncaoAdministrativa|int $funcao): void
    {
        $funcao = $funcao instanceof FuncaoAdministrativa
            ? $funcao
            : FuncaoAdministrativa::query()->findOrFail($funcao);

        if ($funcao->ehEquipeGestora()) {
            throw ValidationException::withMessages([
                'equipe_gestora' => 'Funções da Equipe Gestora só podem ser encerradas pelo fluxo próprio.',
            ]);
        }

        DB::transaction(function () use ($servidor, $funcao): void {
            $servidor = $servidor->fresh(['professores']);

            if ($funcao->exige_professor) {
                $professor = $servidor->professores()->first();

                if ($professor && $this->professorPossuiVinculosPedagogicos($professor)) {
                    throw ValidationException::withMessages([
                        'vinculos_funcionais' => 'Não é possível remover a função Professor enquanto houver vínculos pedagógicos ativos.',
                    ]);
                }
            }

            $vinculos = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcao->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->get();

            if ($vinculos->isEmpty()) {
                return;
            }

            foreach ($vinculos as $vinculo) {
                if (Schema::hasTable('servidor_funcao_turma')) {
                    if (Schema::hasColumn('servidor_funcao_turma', 'status')) {
                        app(PessoaEquipeGestoraService::class)->encerrarVinculo($vinculo);
                    } else {
                        $vinculo->turmas()->detach();
                    }
                }
            }

            ServidorFuncaoAdministrativa::query()
                ->whereKey($vinculos->pluck('id')->all())
                ->update([
                    'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                    'data_fim' => now()->toDateString(),
                    'updated_at' => now(),
                ]);
        });
    }

    public function sincronizarFuncoesSelecionadas(Servidor $servidor, array $funcaoIds): void
    {
        $this->sincronizarVinculosFuncionais($servidor, $funcaoIds);
    }

    public function sincronizarVinculosFuncionais(Servidor $servidor, array $vinculos): void
    {
        $vinculos = $this->normalizarVinculosFuncionais($vinculos);
        $funcaoIds = $vinculos->pluck('funcao_administrativa_id')->values();
        $ativas = $servidor->funcoesAtivas()
            ->pluck('funcao_administrativa.id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        foreach ($vinculos as $vinculo) {
            $this->vincularFuncao($servidor, (int) $vinculo['funcao_administrativa_id'], [
                'matricula' => $vinculo['matricula'] ?? null,
                'setor_id' => $vinculo['setor_id'] ?? null,
                'id_escola' => $vinculo['id_escola'] ?? null,
                'portaria' => $vinculo['portaria'] ?? null,
                'turma_ids' => $vinculo['turma_ids'] ?? [],
            ]);
        }

        foreach ($ativas->diff($funcaoIds) as $funcaoId) {
            $this->removerFuncao($servidor, $funcaoId);
        }
    }

    public function aplicarEscopoVisibilidade(Builder $query, ?User $user): Builder
    {
        $scope = app(PessoaScopeService::class);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($scope->hasGlobalAccess($user)) {
            return $query;
        }

        $escolaIds = $scope->escolaIdsDosVinculos($user);

        if ($scope->ehEquipeGestora($user)) {
            if ($escolaIds === []) {
                return $query->whereRaw('1 = 0');
            }

            return $query->where(function (Builder $servidores) use ($escolaIds): void {
                $servidores
                    ->whereIn('id_escola', $escolaIds)
                    ->orWhereHas(
                        'vinculosAtivos',
                        fn (Builder $vinculos): Builder => $vinculos->whereIn('id_escola', $escolaIds)
                    );
            });
        }

        $setorIds = $scope->visibleSetorIds($user);

        if ($setorIds === [] && $escolaIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $servidores) use ($setorIds, $escolaIds): void {
            if ($setorIds !== []) {
                $servidores
                    ->whereIn('setor_id', $setorIds)
                    ->orWhereHas('escola', fn (Builder $escola): Builder => $escola->whereIn('setor_id', $setorIds))
                    ->orWhereHas(
                        'vinculosAtivos',
                        fn (Builder $vinculos): Builder => $vinculos->whereIn('setor_id', $setorIds)
                    );
            }

            if ($escolaIds !== []) {
                $servidores
                    ->orWhereIn('id_escola', $escolaIds)
                    ->orWhereHas(
                        'vinculosAtivos',
                        fn (Builder $vinculos): Builder => $vinculos->whereIn('id_escola', $escolaIds)
                    );
            }
        });
    }

    /**
     * Usado só para impedir remoção parcial de lotação ainda com TCP ativo no form.
     * Avaliações NÃO bloqueiam: professor_id é metadado de exportação e pode ser anulado.
     */
    public function professorPossuiVinculosPedagogicos(Professor $professor): bool
    {
        if (
            Schema::hasTable('turma_componente_professor')
            && DB::table('turma_componente_professor')
                ->where('professor_id', $professor->id)
                ->where('tem_professor', true)
                ->exists()
        ) {
            return true;
        }

        return false;
    }

    public function pessoaPodeSerExcluida(Servidor $pessoa): bool
    {
        return ! $pessoa->servidorFuncoes()
            ->whereHas(
                'funcaoAdministrativa',
                fn (Builder $funcoes): Builder => $funcoes->equipeGestora(),
            )
            ->exists();
    }

    public function motivoBloqueioExclusao(Servidor $pessoa): ?string
    {
        if ($this->pessoaPodeSerExcluida($pessoa)) {
            return null;
        }

        return "{$pessoa->nome} não pode ser excluída porque possui histórico na Equipe Gestora. "
            .'Inative a pessoa ou converta o cargo pelo fluxo próprio; os vínculos históricos devem ser preservados.';
    }

    /**
     * Anula referências ao professor em tabelas pedagógicas sem apagar avaliações/alunos.
     *
     * @param  list<int>  $professorIds
     */
    public function desvincularProfessorDePedagogico(array $professorIds): void
    {
        if ($professorIds === []) {
            return;
        }

        if (Schema::hasTable('turma_componente_professor')) {
            DB::table('turma_componente_professor')
                ->whereIn('professor_id', $professorIds)
                ->update([
                    'professor_id' => null,
                    'tem_professor' => false,
                    'updated_at' => now(),
                ]);
        }

        // Avaliação fica no aluno (payload JSON). Professor no documento é só contexto
        // denormalizado em professor_ids; não há tabela linha-por-resposta para limpar.
    }

    public function excluirPessoa(Servidor $pessoa): void
    {
        DB::transaction(function () use ($pessoa): void {
            $pessoa = Servidor::query()
                ->lockForUpdate()
                ->findOrFail($pessoa->getKey());

            if ($motivo = $this->motivoBloqueioExclusao($pessoa)) {
                throw ValidationException::withMessages([
                    'pessoa' => $motivo,
                ]);
            }

            $pessoa->load(['professores', 'professorMatriculas', 'servidorFuncoes']);
            $professorIds = $pessoa->professores->pluck('id')->map(fn ($id): int => (int) $id)->all();

            $this->desvincularProfessorDePedagogico($professorIds);

            if ($professorIds !== []) {
                Professor::query()->whereIn('id', $professorIds)->delete();
            }

            if (Schema::hasTable('professor_matriculas')) {
                DB::table('professor_matriculas')->where('servidor_id', $pessoa->id)->delete();
            }

            if (Schema::hasTable('servidor_funcao_turma')) {
                $vinculoIds = $pessoa->servidorFuncoes->pluck('id')->all();
                if ($vinculoIds !== []) {
                    DB::table('servidor_funcao_turma')
                        ->whereIn('servidor_funcao_administrativa_id', $vinculoIds)
                        ->delete();
                }
            }

            ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $pessoa->id)
                ->delete();

            // Desvincula login sem apagar a conta de usuário.
            if (filled($pessoa->user_id)) {
                $pessoa->update(['user_id' => null]);
            }

            $pessoa->delete();
        });
    }

    /**
     * @param  iterable<int, Servidor>  $pessoas
     * @return array{excluidos: int, bloqueados: list<string>}
     */
    public function excluirPessoasEmMassa(iterable $pessoas): array
    {
        $excluidos = 0;
        $bloqueados = [];

        foreach ($pessoas as $pessoa) {
            if (! $pessoa instanceof Servidor) {
                continue;
            }

            $motivo = $this->motivoBloqueioExclusao($pessoa);
            if ($motivo) {
                $bloqueados[] = $motivo;

                continue;
            }

            $this->excluirPessoa($pessoa);
            $excluidos++;
        }

        return [
            'excluidos' => $excluidos,
            'bloqueados' => $bloqueados,
        ];
    }

    private function normalizarVinculosFuncionais(array $vinculos)
    {
        return collect($vinculos)
            ->map(function ($vinculo): ?array {
                if (is_array($vinculo)) {
                    $funcaoId = $vinculo['funcao_administrativa_id'] ?? $vinculo['id'] ?? null;

                    if (! filled($funcaoId)) {
                        return null;
                    }

                    return [
                        'funcao_administrativa_id' => (int) $funcaoId,
                        'matricula' => $vinculo['matricula'] ?? null,
                        'setor_id' => $vinculo['setor_id'] ?? null,
                        'id_escola' => $vinculo['id_escola'] ?? null,
                        'portaria' => filled($vinculo['portaria'] ?? null) ? (string) $vinculo['portaria'] : null,
                        'turma_ids' => $vinculo['turma_ids'] ?? $vinculo['turmas'] ?? [],
                    ];
                }

                if (! filled($vinculo)) {
                    return null;
                }

                return [
                    'funcao_administrativa_id' => (int) $vinculo,
                    'portaria' => null,
                    'turma_ids' => [],
                ];
            })
            ->filter()
            ->keyBy('funcao_administrativa_id')
            ->values();
    }

    private function dadosServidor(array $data): array
    {
        return [
            'cpf' => $data['cpf'] ?? null,
            'user_id' => $data['user_id'] ?? null,
            'id_escola' => $data['id_escola'] ?? null,
            'setor_id' => $data['setor_id'] ?? null,
            'matricula' => $data['matricula'] ?? null,
            'nome' => $data['nome'] ?? null,
            'email' => $data['email'] ?? null,
            'telefone' => $data['telefone'] ?? null,
            'status' => $data['status'] ?? Servidor::STATUS_ATIVO,
            'observacoes' => $data['observacoes'] ?? null,
        ];
    }

    private function criarOuAtualizarServidorDoProfessor(Professor $professor): Servidor
    {
        $professor->loadMissing('escola', 'servidor');

        $payload = [
            'user_id' => $professor->user_id,
            'id_escola' => $professor->id_escola,
            'setor_id' => $this->setorIdDoProfessor($professor),
            'matricula' => $professor->matricula,
            'nome' => $professor->nome,
            'email' => $professor->email,
            'telefone' => $professor->telefone,
            'status' => Servidor::STATUS_ATIVO,
        ];

        $servidor = $professor->servidor;

        if (! $servidor && filled($professor->id_escola) && filled($professor->matricula)) {
            $servidor = Servidor::query()
                ->where('id_escola', $professor->id_escola)
                ->where('matricula', $professor->matricula)
                ->whereDoesntHave('professores')
                ->first();
        }

        if ($servidor) {
            $servidor->forceFill($payload)->save();
        } else {
            $servidor = Servidor::query()->create($payload);
        }

        if ((int) ($professor->servidor_id ?? 0) !== (int) $servidor->id) {
            $professor->forceFill(['servidor_id' => $servidor->id])->saveQuietly();
        }

        return $servidor;
    }

    private function garantirProfessorParaServidor(Servidor $servidor): Professor
    {
        if (filled($servidor->id_escola) && filled($servidor->matricula)) {
            $correspondente = $servidor->professores()
                ->where('id_escola', $servidor->id_escola)
                ->where('matricula', $servidor->matricula)
                ->first();

            if ($correspondente) {
                $correspondente->forceFill([
                    'nome' => $servidor->nome,
                    'email' => $servidor->email,
                    'telefone' => $servidor->telefone,
                    'user_id' => $servidor->user_id,
                ])->saveQuietly();

                return $correspondente;
            }
        }

        $professor = $servidor->professores()->first();

        if ($professor) {
            $payload = [
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'telefone' => $servidor->telefone,
                'user_id' => $servidor->user_id,
            ];

            if (filled($servidor->id_escola)) {
                $payload['id_escola'] = $servidor->id_escola;
            }

            if (filled($servidor->matricula) && ! $this->professorMatriculaConflita($professor, $servidor)) {
                $payload['matricula'] = $servidor->matricula;
            }

            $professor->forceFill($payload)->saveQuietly();

            return $professor;
        }

        if (blank($servidor->id_escola) || blank($servidor->matricula) || blank($servidor->nome)) {
            throw ValidationException::withMessages([
                'vinculos_funcionais' => 'Para atribuir a função Professor, informe escola, matrícula e nome do servidor.',
            ]);
        }

        $professor = Professor::query()
            ->where('id_escola', $servidor->id_escola)
            ->where('matricula', $servidor->matricula)
            ->first();

        if ($professor) {
            $professor->forceFill([
                'servidor_id' => $servidor->id,
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'telefone' => $servidor->telefone,
                'user_id' => $servidor->user_id,
            ])->saveQuietly();

            return $professor;
        }

        return Professor::query()->create([
            'servidor_id' => $servidor->id,
            'user_id' => $servidor->user_id,
            'id_escola' => $servidor->id_escola,
            'matricula' => $servidor->matricula,
            'nome' => $servidor->nome,
            'email' => $servidor->email,
            'telefone' => $servidor->telefone,
        ]);
    }

    private function professorMatriculaConflita(Professor $professor, Servidor $servidor): bool
    {
        if (blank($servidor->id_escola) || blank($servidor->matricula)) {
            return false;
        }

        return Professor::query()
            ->where('id_escola', $servidor->id_escola)
            ->where('matricula', $servidor->matricula)
            ->where('id', '!=', $professor->id)
            ->exists();
    }

    private function setorIdDoProfessor(Professor $professor): ?int
    {
        return $professor->escola?->setor_id ? (int) $professor->escola->setor_id : null;
    }
}
