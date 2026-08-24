<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\PessoaExclusaoDefinitiva;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;

class PessoaExclusaoDefinitivaService
{
    /**
     * @return array<string, int>
     */
    public function excluir(Servidor $pessoa, User $operador): array
    {
        Gate::forUser($operador)->authorize('forceDelete', $pessoa);

        return DB::transaction(function () use ($pessoa, $operador): array {
            $pessoa = Servidor::withTrashed()
                ->lockForUpdate()
                ->findOrFail($pessoa->getKey());

            Gate::forUser($operador)->authorize('forceDelete', $pessoa);

            if (! $pessoa->trashed()) {
                throw ValidationException::withMessages([
                    'pessoa' => 'Arquive a pessoa antes de excluí-la definitivamente.',
                ]);
            }

            if (app(ServidorService::class)->possuiEventosFuturosComoMotorista($pessoa)) {
                throw ValidationException::withMessages([
                    'pessoa' => 'A pessoa possui eventos atuais ou futuros como motorista. Substitua o motorista antes da exclusão definitiva.',
                ]);
            }

            $user = filled($pessoa->user_id)
                ? User::withTrashed()->lockForUpdate()->find((int) $pessoa->user_id)
                : null;

            if ($user && Pessoa::withTrashed()
                ->where('user_id', $user->id)
                ->whereKeyNot($pessoa->id)
                ->exists()) {
                throw ValidationException::withMessages([
                    'pessoa' => 'A conta de acesso também está vinculada a outra pessoa. Regularize esse vínculo antes da exclusão definitiva.',
                ]);
            }

            $professores = Professor::query()
                ->where('servidor_id', $pessoa->id)
                ->lockForUpdate()
                ->get();
            $professorIds = $professores->pluck('id')->map(fn ($id): int => (int) $id)->all();
            $vinculos = ServidorFuncaoAdministrativa::query()
                ->where('servidor_id', $pessoa->id)
                ->lockForUpdate()
                ->get();

            $resumo = [
                'professores_excluidos' => $professores->count(),
                'vinculos_funcionais_excluidos' => $vinculos->count(),
                'matriculas_excluidas' => ProfessorMatricula::withTrashed()->where('servidor_id', $pessoa->id)->count(),
                'vinculos_pedagogicos_desocupados' => $this->contarVinculosPedagogicos($professorIds),
                'documentos_avaliativos_com_autoria_preservada' => $this->preservarAutoriaDasAvaliacoes($professores),
                'usuario_excluido' => $user ? 1 : 0,
            ];

            app(ServidorService::class)->desvincularProfessorDePedagogico($professorIds);
            $this->desvincularAlunosDosProfessores($professorIds);

            if ($professorIds !== []) {
                Professor::query()->whereIn('id', $professorIds)->delete();
            }

            ProfessorMatricula::withTrashed()
                ->where('servidor_id', $pessoa->id)
                ->get()
                ->each->forceDelete();

            if ($vinculos->isNotEmpty()) {
                ServidorFuncaoAdministrativa::query()
                    ->whereIn('id', $vinculos->pluck('id')->all())
                    ->delete();
            }

            if ($user) {
                $this->excluirUsuario($user);
            }

            $pessoaId = (int) $pessoa->id;
            $pessoa->forceDelete();

            PessoaExclusaoDefinitiva::query()->create([
                'servidor_id_legado' => $pessoaId,
                'executado_por_user_id' => $operador->id,
                'resumo' => $resumo,
                'ocorrido_em' => now(),
            ]);

            app(PermissionRegistrar::class)->forgetCachedPermissions();

            return $resumo;
        });
    }

    /** @param list<int> $professorIds */
    private function contarVinculosPedagogicos(array $professorIds): int
    {
        if ($professorIds === [] || ! Schema::hasTable('turma_componente_professor')) {
            return 0;
        }

        return DB::table('turma_componente_professor')
            ->whereIn('professor_id', $professorIds)
            ->where('tem_professor', true)
            ->count();
    }

    /** @param list<int> $professorIds */
    private function desvincularAlunosDosProfessores(array $professorIds): void
    {
        if ($professorIds === []
            || ! Schema::hasTable('alunos')
            || ! Schema::hasColumn('alunos', 'id_professor')) {
            return;
        }

        DB::table('alunos')
            ->whereIn('id_professor', $professorIds)
            ->update(['id_professor' => null]);
    }

    /** @param Collection<int, Professor> $professores */
    private function preservarAutoriaDasAvaliacoes(Collection $professores): int
    {
        $nomes = $professores
            ->mapWithKeys(fn (Professor $professor): array => [
                (int) $professor->id => trim($professor->nomeCanonico()),
            ])
            ->filter(fn (string $nome): bool => $nome !== '')
            ->all();

        if ($nomes === []) {
            return 0;
        }

        $alterados = 0;

        foreach (['avaliacao_aluno_documentos', 'avaliacao_aluno_documentos_historico'] as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'payload')) {
                continue;
            }

            DB::table($tabela)
                ->whereNotNull('payload')
                ->orderBy('id')
                ->chunkById(100, function ($documentos) use ($tabela, $nomes, &$alterados): void {
                    foreach ($documentos as $documento) {
                        $payload = json_decode((string) $documento->payload, true);
                        if (! is_array($payload)) {
                            continue;
                        }

                        $mudou = false;
                        foreach (['pautas', 'informacoes_complementares'] as $grupo) {
                            if (! is_array($payload[$grupo] ?? null)) {
                                continue;
                            }

                            foreach ($payload[$grupo] as &$item) {
                                if (! is_array($item)) {
                                    continue;
                                }

                                $professorId = (int) ($item['professor_id'] ?? 0);
                                if (! isset($nomes[$professorId]) || filled($item['professor_nome'] ?? null)) {
                                    continue;
                                }

                                $item['professor_nome'] = $nomes[$professorId];
                                $mudou = true;
                            }
                            unset($item);
                        }

                        if (! $mudou) {
                            continue;
                        }

                        $atualizacao = [
                            'payload' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        ];
                        if (Schema::hasColumn($tabela, 'updated_at')) {
                            $atualizacao['updated_at'] = now();
                        }

                        DB::table($tabela)->where('id', $documento->id)->update($atualizacao);
                        $alterados++;
                    }
                });
        }

        return $alterados;
    }

    private function excluirUsuario(User $user): void
    {
        $email = $user->email;

        $user->syncRoles([]);
        $user->syncPermissions([]);
        $user->escolas()->detach();

        $this->excluirPorUsuario('sessions', 'user_id', $user->id);
        $this->excluirPorUsuario('socialite_users', 'user_id', $user->id);
        $this->excluirPorUsuario('publico_alvo_user', 'user_id', $user->id);
        $this->excluirPorUsuario('aviso_leituras', 'user_id', $user->id);
        $this->excluirPorUsuario('ignored_users', 'user_id', $user->id);
        $this->excluirPorUsuario('ignored_users', 'admin_id', $user->id);

        if (Schema::hasTable('notifications')) {
            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->delete();
        }

        if ($email && Schema::hasTable('password_reset_tokens')) {
            DB::table('password_reset_tokens')->where('email', $email)->delete();
        }

        $user->forceDelete();
    }

    private function excluirPorUsuario(string $tabela, string $coluna, int $userId): void
    {
        if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, $coluna)) {
            DB::table($tabela)->where($coluna, $userId)->delete();
        }
    }
}
