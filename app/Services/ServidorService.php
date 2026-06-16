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
    public function criarServidorComFuncoes(array $data, array $funcaoIds = []): Servidor
    {
        return DB::transaction(function () use ($data, $funcaoIds): Servidor {
            $servidor = Servidor::query()->create($this->dadosServidor($data));
            $this->sincronizarFuncoesSelecionadas($servidor, $funcaoIds);

            return $servidor->fresh(['funcoesAtivas', 'professores']);
        });
    }

    public function atualizarServidorComFuncoes(Servidor $servidor, array $data, array $funcaoIds = []): Servidor
    {
        return DB::transaction(function () use ($servidor, $data, $funcaoIds): Servidor {
            $servidor->update($this->dadosServidor($data));
            $this->sincronizarFuncoesSelecionadas($servidor->fresh(), $funcaoIds);

            return $servidor->fresh(['funcoesAtivas', 'professores']);
        });
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

            $this->sincronizarFuncaoAdministrativaLegada($professor->fresh(), $servidor);

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

        return DB::transaction(function () use ($servidor, $funcao, $contexto): ServidorFuncaoAdministrativa {
            $servidor = $servidor->fresh(['professores', 'escola']);

            if ($funcao->exige_professor) {
                $this->garantirProfessorParaServidor($servidor);
            }

            $origem = (string) ($contexto['origem'] ?? 'manual');

            $vinculo = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcao->id)
                ->where('origem', $origem)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->first() ?? new ServidorFuncaoAdministrativa([
                    'servidor_id' => $servidor->id,
                    'funcao_administrativa_id' => $funcao->id,
                    'origem' => $origem,
                ]);

            $vinculo->fill([
                'id_escola' => $contexto['id_escola'] ?? $servidor->id_escola,
                'setor_id' => $contexto['setor_id'] ?? $servidor->setor_id,
                'status' => ServidorFuncaoAdministrativa::STATUS_ATIVO,
                'portaria' => $contexto['portaria'] ?? $vinculo->portaria,
                'data_inicio' => $contexto['data_inicio'] ?? $vinculo->data_inicio,
                'data_fim' => null,
            ]);
            $vinculo->save();

            if ($funcao->categoria === FuncaoAdministrativa::CATEGORIA_EQUIPE_GESTORA) {
                $this->sincronizarFuncaoLegadaNoProfessor($servidor, $funcao, $vinculo->portaria);
            }

            return $vinculo->fresh();
        });
    }

    public function removerFuncao(Servidor $servidor, FuncaoAdministrativa|int $funcao): void
    {
        $funcao = $funcao instanceof FuncaoAdministrativa
            ? $funcao
            : FuncaoAdministrativa::query()->findOrFail($funcao);

        DB::transaction(function () use ($servidor, $funcao): void {
            $servidor = $servidor->fresh(['professores']);

            if ($funcao->codigo === FuncaoAdministrativa::CODIGO_PROFESSOR) {
                $professor = $servidor->professores()->first();

                if ($professor && $this->professorPossuiVinculosPedagogicos($professor)) {
                    throw ValidationException::withMessages([
                        'funcao_administrativa_ids' => 'Não é possível remover a função Professor enquanto houver vínculos pedagógicos ativos.',
                    ]);
                }
            }

            ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $servidor->id)
                ->where('funcao_administrativa_id', $funcao->id)
                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
                ->update([
                    'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                    'data_fim' => now()->toDateString(),
                    'updated_at' => now(),
                ]);

            if ($funcao->categoria === FuncaoAdministrativa::CATEGORIA_EQUIPE_GESTORA) {
                $this->removerFuncaoLegadaDoProfessor($servidor, $funcao);
            }
        });
    }

    public function sincronizarFuncoesSelecionadas(Servidor $servidor, array $funcaoIds): void
    {
        $funcaoIds = collect($funcaoIds)
            ->filter(fn ($id): bool => filled($id))
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $funcoes = FuncaoAdministrativa::query()
            ->whereIn('id', $funcaoIds)
            ->get();

        $funcoesEquipeGestora = $funcoes
            ->where('categoria', FuncaoAdministrativa::CATEGORIA_EQUIPE_GESTORA);

        if ($servidor->professores()->exists() && $funcoesEquipeGestora->count() > 1) {
            throw ValidationException::withMessages([
                'funcao_administrativa_ids' => 'Selecione apenas uma função de equipe gestora para servidor vinculado a professor.',
            ]);
        }

        $ativas = $servidor->funcoesAtivas()
            ->pluck('funcao_administrativa.id')
            ->map(fn ($id): int => (int) $id)
            ->values();

        foreach ($funcaoIds->diff($ativas) as $funcaoId) {
            $this->vincularFuncao($servidor, $funcaoId);
        }

        foreach ($ativas->diff($funcaoIds) as $funcaoId) {
            $this->removerFuncao($servidor, $funcaoId);
        }
    }

    public function aplicarEscopoVisibilidade(Builder $query, ?User $user): Builder
    {
        $access = app(UserSetorAccessService::class);

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($access->hasGlobalAccess($user)) {
            return $query;
        }

        $setorIds = $access->visibleSetorIds($user);
        $escolaIds = $user->idsEscolasVinculadas();

        if ($setorIds === [] && $escolaIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $servidores) use ($setorIds, $escolaIds): void {
            if ($setorIds !== []) {
                $servidores
                    ->whereIn('setor_id', $setorIds)
                    ->orWhereHas('escola', fn (Builder $escola): Builder => $escola->whereIn('setor_id', $setorIds));
            }

            if ($escolaIds !== []) {
                $servidores->orWhereIn('id_escola', $escolaIds);
            }
        });
    }

    public function professorPossuiVinculosPedagogicos(Professor $professor): bool
    {
        $checks = [
            ['turma_componente_professor', 'professor_id'],
            ['professor_funcao_turma', 'professor_id'],
            ['avaliacao_respostas', 'professor_id'],
            ['avaliacao_informacoes_complementares', 'professor_id'],
        ];

        foreach ($checks as [$table, $column]) {
            if (Schema::hasTable($table) && DB::table($table)->where($column, $professor->id)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function dadosServidor(array $data): array
    {
        return [
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
        $professor = $servidor->professores()->first();

        if ($professor) {
            $professor->forceFill([
                'id_escola' => $servidor->id_escola,
                'matricula' => $servidor->matricula,
                'nome' => $servidor->nome,
                'email' => $servidor->email,
                'telefone' => $servidor->telefone,
                'user_id' => $servidor->user_id,
            ])->saveQuietly();

            return $professor;
        }

        if (blank($servidor->id_escola) || blank($servidor->matricula) || blank($servidor->nome)) {
            throw ValidationException::withMessages([
                'funcao_administrativa_ids' => 'Para atribuir a função Professor, informe escola, matrícula e nome do servidor.',
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

    private function sincronizarFuncaoAdministrativaLegada(Professor $professor, Servidor $servidor): void
    {
        ServidorFuncaoAdministrativa::query()
            ->where('servidor_id', $servidor->id)
            ->where('origem', 'professor_legacy')
            ->when($professor->funcao_administrativa_id, function (Builder $query) use ($professor): Builder {
                return $query->where('funcao_administrativa_id', '!=', $professor->funcao_administrativa_id);
            })
            ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO)
            ->update([
                'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                'data_fim' => now()->toDateString(),
                'updated_at' => now(),
            ]);

        if (! $professor->funcao_administrativa_id) {
            return;
        }

        $this->vincularFuncao($servidor, (int) $professor->funcao_administrativa_id, [
            'origem' => 'professor_legacy',
            'id_escola' => $professor->id_escola,
            'setor_id' => $this->setorIdDoProfessor($professor),
            'portaria' => $professor->portaria,
        ]);
    }

    private function sincronizarFuncaoLegadaNoProfessor(Servidor $servidor, FuncaoAdministrativa $funcao, ?string $portaria): void
    {
        $professor = $servidor->professores()->first();

        if (! $professor) {
            return;
        }

        $professor->forceFill([
            'funcao_administrativa_id' => $funcao->id,
            'portaria' => $portaria,
        ])->saveQuietly();
    }

    private function removerFuncaoLegadaDoProfessor(Servidor $servidor, FuncaoAdministrativa $funcao): void
    {
        $professor = $servidor->professores()->first();

        if (! $professor || (int) $professor->funcao_administrativa_id !== (int) $funcao->id) {
            return;
        }

        $professor->turmasFuncao()->detach();
        $professor->forceFill([
            'funcao_administrativa_id' => null,
            'portaria' => null,
        ])->saveQuietly();
    }

    private function setorIdDoProfessor(Professor $professor): ?int
    {
        return $professor->escola?->setor_id ? (int) $professor->escola->setor_id : null;
    }
}
