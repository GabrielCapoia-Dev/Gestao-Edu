<?php

namespace App\Services\Avaliacoes;

use App\Models\Aluno;
use App\Models\Alternativa;
use App\Models\Avaliacao;
use App\Models\AvaliacaoTurmaCiclo;
use App\Models\Pauta;
use App\Models\Pessoa;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\Turma;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Spatie\Permission\PermissionRegistrar;

class AvaliacaoAutosaveContextService
{
    /**
     * Resolve, em um unico snapshot SQL, o contexto operacional que ja foi
     * inicializado. A validacao detalhada continua sendo o fallback para
     * dados invalidos e para turmas que ainda dependem da migracao lazy.
     *
     * @param array<string, mixed> $dados
     * @return array{avaliacao:Avaliacao,turma:Turma,ciclo:AvaliacaoTurmaCiclo,aluno:Aluno,pauta:Pauta|null,professor_id:int|null,alternativa:Alternativa|null}|null
     */
    public function resolver(array $dados, User $user): ?array
    {
        if ($user->trashed()) {
            return null;
        }

        $query = DB::table('avaliacoes as avaliacao')
            ->join('avaliacao_turma as vinculo_turma', function ($join): void {
                $join->on('vinculo_turma.avaliacao_id', '=', 'avaliacao.id');
            })
            ->join('turmas as turma', 'turma.id', '=', 'vinculo_turma.turma_id')
            ->join('avaliacao_turma_ciclos as ciclo', function ($join): void {
                $join
                    ->on('ciclo.avaliacao_id', '=', 'avaliacao.id')
                    ->on('ciclo.turma_avaliativa_id', '=', 'turma.id');
            })
            ->join('avaliacao_turma_tokens_escrita as token', 'token.ciclo_id', '=', 'ciclo.id')
            ->join('alunos as aluno', function ($join) use ($dados): void {
                $join
                    ->on('aluno.id_turma', '=', 'ciclo.turma_origem_id')
                    ->where('aluno.id', (int) $dados['aluno_id']);
            })
            ->where('avaliacao.id', (int) $dados['avaliacao_id'])
            ->where('turma.id', (int) $dados['turma_id'])
            ->whereNotNull('ciclo.operacional_inicializado_em')
            ->whereIn('aluno.status', [Aluno::STATUS_MATRICULADO, Aluno::STATUS_PENDENTE])
            ->select([
                'avaliacao.id as avaliacao_id',
                'avaliacao.tipo_avaliacao_id as avaliacao_tipo_id',
                'avaliacao.status as avaliacao_status',
                'avaliacao.data_inicio as avaliacao_data_inicio',
                'avaliacao.data_fim as avaliacao_data_fim',
                'avaliacao.data_inicio_preenchimento as avaliacao_data_inicio_preenchimento',
                'avaliacao.data_fim_preenchimento as avaliacao_data_fim_preenchimento',
                'turma.id as turma_id',
                'turma.id_escola as turma_escola_id',
                'turma.id_serie as turma_serie_id',
                'ciclo.id as ciclo_id',
                'ciclo.avaliacao_id as ciclo_avaliacao_id',
                'ciclo.turma_avaliativa_id as ciclo_turma_avaliativa_id',
                'ciclo.turma_origem_id as ciclo_turma_origem_id',
                'ciclo.status as ciclo_status',
                'ciclo.operacional_inicializado_em as ciclo_operacional_inicializado_em',
                'token.id as token_escrita_bloqueado_id',
                'aluno.id as aluno_id',
                'aluno.id_turma as aluno_turma_id',
                'aluno.status as aluno_status',
                'aluno.pendencia_origem_aluno_id as aluno_pendencia_origem_id',
            ]);

        $this->aplicarAutorizacao($query, $user);

        if ($dados['tipo'] === 'resposta') {
            $query
                ->join('pautas as pauta', function ($join) use ($dados): void {
                    $join
                        ->where('pauta.id', (int) ($dados['pauta_id'] ?? 0))
                        ->where('pauta.status', true);
                })
                ->join('avaliacao_pauta as vinculo_pauta', function ($join): void {
                    $join
                        ->on('vinculo_pauta.avaliacao_id', '=', 'avaliacao.id')
                        ->on('vinculo_pauta.pauta_id', '=', 'pauta.id');
                })
                ->addSelect([
                    'pauta.id as pauta_id',
                    'pauta.tipo_avaliacao_id as pauta_tipo_id',
                    'pauta.serie_id as pauta_serie_id',
                    'pauta.componente_curricular_id as pauta_componente_id',
                    'pauta.status as pauta_status',
                ]);

            $alternativaId = (int) ($dados['alternativa_contexto_id'] ?? 0);
            if ($alternativaId > 0) {
                $query
                    ->leftJoin('alternativas as alternativa', function ($join) use ($alternativaId): void {
                        $join
                            ->where('alternativa.id', $alternativaId)
                            ->where('alternativa.status', true);
                    })
                    ->addSelect([
                        'alternativa.id as alternativa_id',
                        'alternativa.tipo_avaliacao_id as alternativa_tipo_id',
                        'alternativa.tem_observacao as alternativa_tem_observacao',
                    ])
                    ->selectRaw(
                        'EXISTS (SELECT 1 FROM avaliacao_pauta_alternativa apa WHERE apa.avaliacao_id = avaliacao.id AND apa.pauta_id = pauta.id) AS possui_overrides',
                    )
                    ->selectRaw(
                        'EXISTS (SELECT 1 FROM avaliacao_pauta_alternativa apa WHERE apa.avaliacao_id = avaliacao.id AND apa.pauta_id = pauta.id AND apa.alternativa_id = alternativa.id) AS override_permitido',
                    )
                    ->selectRaw(
                        'EXISTS (SELECT 1 FROM alternativa_pauta ap WHERE ap.pauta_id = pauta.id) AS possui_alternativas_pauta',
                    )
                    ->selectRaw(
                        'EXISTS (SELECT 1 FROM alternativa_pauta ap WHERE ap.pauta_id = pauta.id AND ap.alternativa_id = alternativa.id) AS alternativa_pauta_permitida',
                    );
            }
        }

        $componenteId = $dados['tipo'] === 'resposta'
            ? null
            : max(0, (int) ($dados['componente_id'] ?? 0));
        $professor = DB::table('turma_componente_professor as tcp_contexto')
            ->join('professores as prof_contexto', 'prof_contexto.id', '=', 'tcp_contexto.professor_id')
            ->leftJoin('servidores as serv_contexto', 'serv_contexto.id', '=', 'prof_contexto.servidor_id')
            ->select('tcp_contexto.professor_id')
            ->whereColumn('tcp_contexto.turma_id', 'turma.id')
            ->where('tcp_contexto.tem_professor', true)
            ->where('prof_contexto.ativo', true)
            ->whereColumn('prof_contexto.id_escola', 'turma.id_escola')
            ->where(function ($identidade) use ($user): void {
                $identidade
                    ->where('prof_contexto.user_id', (int) $user->getKey())
                    ->orWhere(function ($servidor) use ($user): void {
                        $servidor
                            ->where('serv_contexto.user_id', (int) $user->getKey())
                            ->where('serv_contexto.status', 'ativo')
                            ->whereNull('serv_contexto.deleted_at');
                    });
            });

        if ($dados['tipo'] === 'resposta') {
            $professor->where(function ($componente): void {
                $componente
                    ->whereNull('pauta.componente_curricular_id')
                    ->orWhereColumn('tcp_contexto.componente_curricular_id', 'pauta.componente_curricular_id');
            });
        } elseif ($componenteId > 0) {
            $professor->where('tcp_contexto.componente_curricular_id', $componenteId);
        }

        $query->selectSub($professor->orderBy('tcp_contexto.professor_id')->limit(1), 'professor_vinculado_id');

        $contexto = $query->first();
        if (! $contexto) {
            return null;
        }

        $avaliacao = (new Avaliacao)->newFromBuilder([
            'id' => $contexto->avaliacao_id,
            'tipo_avaliacao_id' => $contexto->avaliacao_tipo_id,
            'status' => $contexto->avaliacao_status,
            'data_inicio' => $contexto->avaliacao_data_inicio,
            'data_fim' => $contexto->avaliacao_data_fim,
            'data_inicio_preenchimento' => $contexto->avaliacao_data_inicio_preenchimento,
            'data_fim_preenchimento' => $contexto->avaliacao_data_fim_preenchimento,
        ]);
        $turma = (new Turma)->newFromBuilder([
            'id' => $contexto->turma_id,
            'id_escola' => $contexto->turma_escola_id,
            'id_serie' => $contexto->turma_serie_id,
        ]);
        $ciclo = (new AvaliacaoTurmaCiclo)->newFromBuilder([
            'id' => $contexto->ciclo_id,
            'avaliacao_id' => $contexto->ciclo_avaliacao_id,
            'turma_avaliativa_id' => $contexto->ciclo_turma_avaliativa_id,
            'turma_origem_id' => $contexto->ciclo_turma_origem_id,
            'status' => $contexto->ciclo_status,
            'operacional_inicializado_em' => $contexto->ciclo_operacional_inicializado_em,
            'token_escrita_bloqueado_id' => $contexto->token_escrita_bloqueado_id,
        ]);
        $aluno = (new Aluno)->newFromBuilder([
            'id' => $contexto->aluno_id,
            'id_turma' => $contexto->aluno_turma_id,
            'status' => $contexto->aluno_status,
            'pendencia_origem_aluno_id' => $contexto->aluno_pendencia_origem_id,
        ]);
        $pauta = $dados['tipo'] === 'resposta'
            ? (new Pauta)->newFromBuilder([
                'id' => $contexto->pauta_id,
                'tipo_avaliacao_id' => $contexto->pauta_tipo_id,
                'serie_id' => $contexto->pauta_serie_id,
                'componente_curricular_id' => $contexto->pauta_componente_id,
                'status' => $contexto->pauta_status,
            ])
            : null;

        $alternativa = null;
        if ($pauta && (int) ($dados['alternativa_contexto_id'] ?? 0) > 0 && $contexto->alternativa_id) {
            $permitida = (bool) $contexto->possui_overrides
                ? (bool) $contexto->override_permitido
                : ((bool) $contexto->possui_alternativas_pauta
                    ? (bool) $contexto->alternativa_pauta_permitida
                    : (
                        (int) $contexto->alternativa_tipo_id === (int) $contexto->pauta_tipo_id
                        || (int) $contexto->alternativa_tipo_id === (int) $contexto->avaliacao_tipo_id
                    ));

            if ($permitida) {
                $alternativa = (new Alternativa)->newFromBuilder([
                    'id' => $contexto->alternativa_id,
                    'tipo_avaliacao_id' => $contexto->alternativa_tipo_id,
                    'tem_observacao' => $contexto->alternativa_tem_observacao,
                    'status' => true,
                ]);
            }
        }

        $professorId = (int) ($contexto->professor_vinculado_id ?? 0) ?: null;

        return [
            'avaliacao' => $avaliacao,
            'turma' => $turma,
            'ciclo' => $ciclo,
            'aluno' => $aluno,
            'pauta' => $pauta,
            'professor_id' => $professorId,
            'alternativa' => $alternativa,
        ];
    }

    private function aplicarAutorizacao($query, User $user): void
    {
        $query->whereRaw(
            '(SELECT COUNT(*) FROM servidores servidor_acesso'
            .' WHERE servidor_acesso.user_id = ?'
            .' AND servidor_acesso.deleted_at IS NULL'
            .' AND servidor_acesso.status = ?'
            .' AND ('
            .'EXISTS (SELECT 1 FROM professores professor_acesso'
            .' WHERE professor_acesso.servidor_id = servidor_acesso.id'
            .' AND professor_acesso.ativo = 1)'
            .' OR EXISTS (SELECT 1 FROM servidor_funcao_administrativa vinculo_acesso'
            .' INNER JOIN funcao_administrativa funcao_acesso'
            .' ON funcao_acesso.id = vinculo_acesso.funcao_administrativa_id'
            .' WHERE vinculo_acesso.servidor_id = servidor_acesso.id'
            .' AND vinculo_acesso.status = ?'
            .' AND funcao_acesso.codigo <> ?))) = 1',
            [
                (int) $user->getKey(),
                Pessoa::STATUS_ATIVO,
                ServidorFuncaoAdministrativa::STATUS_ATIVO,
                Pessoa::CARGO_PENDENTE_CODIGO,
            ],
        );

        $needle = Str::lower(Str::ascii('responder avaliacoes'));
        $permissionIds = app(PermissionRegistrar::class)
            ->getPermissions()
            ->filter(fn ($permission): bool => str_contains(
                Str::lower(Str::ascii((string) $permission->name)),
                $needle,
            ))
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($permissionIds === []) {
            $query->whereRaw('1 = 0');

            return;
        }

        $tables = config('permission.table_names');
        $columns = config('permission.column_names');
        $permissionPivotKey = $columns['permission_pivot_key'] ?? 'permission_id';
        $rolePivotKey = $columns['role_pivot_key'] ?? 'role_id';
        $modelKey = $columns['model_morph_key'] ?? 'model_id';
        $modelType = $user->getMorphClass();
        $modelId = $user->getKey();

        $query->where(function ($permissao) use (
            $tables,
            $permissionIds,
            $permissionPivotKey,
            $rolePivotKey,
            $modelKey,
            $modelType,
            $modelId,
        ): void {
            $permissao
                ->whereExists(function ($direta) use (
                    $tables,
                    $permissionIds,
                    $permissionPivotKey,
                    $modelKey,
                    $modelType,
                    $modelId,
                ): void {
                    $direta
                        ->selectRaw('1')
                        ->from($tables['model_has_permissions'].' as autosave_user_permission')
                        ->whereIn('autosave_user_permission.'.$permissionPivotKey, $permissionIds)
                        ->where('autosave_user_permission.model_type', $modelType)
                        ->where('autosave_user_permission.'.$modelKey, $modelId);
                })
                ->orWhereExists(function ($viaRole) use (
                    $tables,
                    $permissionIds,
                    $permissionPivotKey,
                    $rolePivotKey,
                    $modelKey,
                    $modelType,
                    $modelId,
                ): void {
                    $viaRole
                        ->selectRaw('1')
                        ->from($tables['role_has_permissions'].' as autosave_role_permission')
                        ->join(
                            $tables['model_has_roles'].' as autosave_user_role',
                            'autosave_user_role.'.$rolePivotKey,
                            '=',
                            'autosave_role_permission.'.$rolePivotKey,
                        )
                        ->whereIn('autosave_role_permission.'.$permissionPivotKey, $permissionIds)
                        ->where('autosave_user_role.model_type', $modelType)
                        ->where('autosave_user_role.'.$modelKey, $modelId);
                });
        });
    }
}
