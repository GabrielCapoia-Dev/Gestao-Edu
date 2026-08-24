<?php

namespace App\Services;

use App\Models\Pessoa;
use App\Models\PessoaExclusaoDefinitiva;
use App\Models\Professor;
use App\Models\ProfessorMatricula;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
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
                'professores_anonimizados' => $professores->count(),
                'vinculos_funcionais_preservados' => $vinculos->count(),
                'matriculas_excluidas' => ProfessorMatricula::query()->where('servidor_id', $pessoa->id)->count(),
                'vinculos_pedagogicos_desocupados' => $this->contarVinculosPedagogicos($professorIds),
                'snapshots_anonimizados' => 0,
                'usuario_anonimizado' => $user ? 1 : 0,
            ];

            app(ServidorService::class)->desvincularProfessorDePedagogico($professorIds);

            foreach ($professores as $professor) {
                $payload = [
                    'user_id' => null,
                    'servidor_id' => null,
                    'professor_matricula_id' => null,
                    'matricula' => "EXCLUIDO-PROF-{$professor->id}",
                    'nome' => "Professor excluído #{$professor->id}",
                    'email' => null,
                    'telefone' => null,
                    'ativo' => false,
                    'desativado_em' => now(),
                    'desativado_por_id' => $operador->id,
                    'motivo_desativacao' => 'Pessoa excluída definitivamente; histórico anonimizado.',
                    'updated_at' => now(),
                ];

                if (Schema::hasColumn('professores', 'portaria')) {
                    $payload['portaria'] = null;
                }

                DB::table('professores')->where('id', $professor->id)->update($payload);
            }

            ProfessorMatricula::query()->where('servidor_id', $pessoa->id)->delete();

            DB::table('servidor_funcao_administrativa')
                ->where('servidor_id', $pessoa->id)
                ->whereNull('data_fim')
                ->update(['data_fim' => now()->toDateString(), 'updated_at' => now()]);
            DB::table('servidor_funcao_administrativa')
                ->where('servidor_id', $pessoa->id)
                ->update([
                    'servidor_id' => null,
                    'matricula' => null,
                    'portaria' => null,
                    'status' => ServidorFuncaoAdministrativa::STATUS_INATIVO,
                    'updated_at' => now(),
                ]);

            $resumo['snapshots_anonimizados'] += $this->anonimizarSnapshotsDaPessoa($pessoa->id);
            if ($user) {
                $resumo['snapshots_anonimizados'] += $this->anonimizarSnapshotsDoUsuario($user->id);
                $this->anonimizarUsuario($user);
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

    private function anonimizarSnapshotsDaPessoa(int $pessoaId): int
    {
        $alterados = 0;

        if (Schema::hasTable('evento_calendario_transporte_alocacoes')) {
            $alterados += DB::table('evento_calendario_transporte_alocacoes')
                ->where('motorista_id', $pessoaId)
                ->update([
                    'motorista_nome' => 'Pessoa excluída',
                    'motorista_cpf' => null,
                    'motorista_matricula' => null,
                    'updated_at' => now(),
                ]);
        }

        foreach (['avaliacao_aluno_documentos', 'avaliacao_aluno_documentos_historico'] as $tabela) {
            if (! Schema::hasTable($tabela) || ! Schema::hasColumn($tabela, 'responsaveis_snapshot')) {
                continue;
            }

            DB::table($tabela)
                ->whereNotNull('responsaveis_snapshot')
                ->orderBy('id')
                ->chunkById(100, function ($documentos) use ($tabela, $pessoaId, &$alterados): void {
                    foreach ($documentos as $documento) {
                        $snapshot = json_decode((string) $documento->responsaveis_snapshot, true);
                        if (! is_array($snapshot)) {
                            continue;
                        }

                        $mudou = false;
                        foreach (['diretor', 'coordenador'] as $papel) {
                            if ((int) ($snapshot[$papel]['pessoa_id'] ?? 0) !== $pessoaId) {
                                continue;
                            }

                            $snapshot[$papel]['nome'] = 'Pessoa excluída';
                            $snapshot[$papel]['portaria'] = '';
                            $mudou = true;
                        }

                        if (! $mudou) {
                            continue;
                        }

                        DB::table($tabela)->where('id', $documento->id)->update([
                            'responsaveis_snapshot' => json_encode($snapshot, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                        $alterados++;
                    }
                });
        }

        return $alterados;
    }

    private function anonimizarSnapshotsDoUsuario(int $userId): int
    {
        $alterados = 0;
        $atores = [
            ['pedidos', 'solicitante_id', 'solicitante_id_legado', 'solicitante_nome_snapshot', 'solicitante_email_snapshot'],
            ['pedidos', 'responsavel_id', 'responsavel_id_legado', 'responsavel_nome_snapshot', 'responsavel_email_snapshot'],
            ['pedidos', 'comentario_gestor_user_id', 'comentario_gestor_user_id_legado', 'comentario_gestor_user_nome_snapshot', 'comentario_gestor_user_email_snapshot'],
            ['pedido_historicos', 'usuario_id', 'usuario_id_legado', 'usuario_nome_snapshot', 'usuario_email_snapshot'],
            ['pedido_arquivos', 'usuario_id', 'usuario_id_legado', 'usuario_nome_snapshot', 'usuario_email_snapshot'],
            ['export_requests', 'user_id', 'user_id_legado', 'user_nome_snapshot', 'user_email_snapshot'],
        ];

        foreach ($atores as [$tabela, $atual, $legado, $nome, $email]) {
            if (! Schema::hasTable($tabela)
                || ! Schema::hasColumn($tabela, $atual)
                || ! Schema::hasColumn($tabela, $legado)
                || ! Schema::hasColumn($tabela, $nome)
                || ! Schema::hasColumn($tabela, $email)) {
                continue;
            }

            $alterados += DB::table($tabela)
                ->where(function ($query) use ($atual, $legado, $userId): void {
                    $query->where($atual, $userId)->orWhere($legado, $userId);
                })
                ->update([
                    $nome => 'Usuário excluído',
                    $email => null,
                    'updated_at' => now(),
                ]);
        }

        return $alterados;
    }

    private function anonimizarUsuario(User $user): void
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

        $user->forceFill([
            'id_escola' => null,
            'setor_id' => null,
            'name' => "Usuário excluído #{$user->id}",
            'email' => null,
            'email_approved' => false,
            'ativo' => false,
            'email_verified_at' => null,
            'last_login_at' => null,
            'last_seen_at' => null,
            'password' => Hash::make(Str::random(64)),
            'must_change_password' => true,
            'google_id' => null,
            'google_email' => null,
            'google_token' => null,
            'google_refresh_token' => null,
            'google_token_expires_in' => null,
            'avatar_url' => null,
            'codigo' => null,
            'remember_token' => Str::random(60),
            'auth_version' => ((int) $user->auth_version) + 1,
        ])->saveQuietly();

        if (! $user->trashed()) {
            $user->delete();
        }
    }

    private function excluirPorUsuario(string $tabela, string $coluna, int $userId): void
    {
        if (Schema::hasTable($tabela) && Schema::hasColumn($tabela, $coluna)) {
            DB::table($tabela)->where($coluna, $userId)->delete();
        }
    }
}
