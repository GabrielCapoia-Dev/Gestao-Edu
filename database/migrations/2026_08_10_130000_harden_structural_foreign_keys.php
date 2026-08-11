<?php

use App\Services\PedidoEstruturalBackfillService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<int, array{table:string,column:string,foreign:string,nullable:?bool,on_delete:string,name:string}>
     */
    private array $constraints = [
        ['table' => 'pedidos', 'column' => 'escola_id', 'foreign' => 'escolas', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedidos_escola_integridade'],
        ['table' => 'pedidos', 'column' => 'solicitante_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_pedidos_solicitante_integridade'],
        ['table' => 'pedidos', 'column' => 'responsavel_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_pedidos_responsavel_integridade'],
        ['table' => 'pedidos', 'column' => 'comentario_gestor_user_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_pedidos_comentario_user_integridade'],
        ['table' => 'pedido_historicos', 'column' => 'usuario_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_pedido_historicos_usuario_integridade'],
        ['table' => 'pedido_arquivos', 'column' => 'usuario_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_pedido_arquivos_usuario_integridade'],
        ['table' => 'export_requests', 'column' => 'user_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_export_requests_user_integridade'],
        ['table' => 'users', 'column' => 'id_escola', 'foreign' => 'escolas', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_users_escola_integridade'],
        ['table' => 'servidores', 'column' => 'user_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_servidores_user_integridade'],
        ['table' => 'professores', 'column' => 'user_id', 'foreign' => 'users', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_professores_user_integridade'],
        ['table' => 'professores', 'column' => 'servidor_id', 'foreign' => 'servidores', 'nullable' => true, 'on_delete' => 'restrict', 'name' => 'fk_professores_servidor_integridade'],
        ['table' => 'servidor_funcao_administrativa', 'column' => 'servidor_id', 'foreign' => 'servidores', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_servidor_funcao_pessoa_integridade'],
        ['table' => 'professor_matriculas', 'column' => 'servidor_id', 'foreign' => 'servidores', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_professor_matriculas_pessoa_integridade'],
        ['table' => 'evento_calendario_transporte_alocacoes', 'column' => 'motorista_id', 'foreign' => 'servidores', 'nullable' => true, 'on_delete' => 'set null', 'name' => 'fk_evento_alocacoes_motorista_integridade'],
        ['table' => 'pedidos', 'column' => 'tipo_manutencao_id', 'foreign' => 'tipo_manutencao', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedidos_tipo_manutencao_integridade'],
        ['table' => 'pedidos', 'column' => 'tipo_status_id', 'foreign' => 'tipo_status', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedidos_tipo_status_integridade'],
        ['table' => 'pedidos', 'column' => 'pedido_principal_id', 'foreign' => 'pedidos', 'nullable' => true, 'on_delete' => 'restrict', 'name' => 'fk_pedidos_principal_integridade'],
        ['table' => 'pedido_historicos', 'column' => 'pedido_id', 'foreign' => 'pedidos', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedido_historicos_pedido_integridade'],
        ['table' => 'pedido_historicos', 'column' => 'status_anterior_id', 'foreign' => 'tipo_status', 'nullable' => true, 'on_delete' => 'restrict', 'name' => 'fk_pedido_historicos_status_anterior'],
        ['table' => 'pedido_historicos', 'column' => 'status_novo_id', 'foreign' => 'tipo_status', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedido_historicos_status_novo'],
        ['table' => 'pedido_arquivos', 'column' => 'pedido_id', 'foreign' => 'pedidos', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedido_arquivos_pedido_integridade'],
        ['table' => 'pedido_problemas', 'column' => 'pedido_id', 'foreign' => 'pedidos', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_pedido_problemas_pedido_integridade'],
        ['table' => 'feedback_pedidos', 'column' => 'pedido_id', 'foreign' => 'pedidos', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_feedback_pedidos_pedido_integridade'],
        ['table' => 'feedback_pedido_itens', 'column' => 'pedido_id', 'foreign' => 'pedidos', 'nullable' => false, 'on_delete' => 'restrict', 'name' => 'fk_feedback_itens_pedido_integridade'],
    ];

    public function up(): void
    {
        $this->restoreSqliteNormalizedEmailColumn();
        $this->assertStructuralSchemaReady();
        app(PedidoEstruturalBackfillService::class)->run(apply: true);
        $this->assertHistoricalPreservation();
        $this->assertPedidoSchoolIntegrity();

        foreach ($this->constraints as $constraint) {
            $this->assertReferenceIntegrity(
                $constraint['table'],
                $constraint['column'],
                $constraint['foreign'],
            );
        }

        foreach (collect($this->constraints)->groupBy('table') as $tableName => $constraints) {
            $this->replaceTableForeignKeys((string) $tableName, $constraints->all());
        }

        $this->restoreSqliteNormalizedEmailColumn();
    }

    public function down(): void
    {
        throw new RuntimeException(
            'Migration estrutural forward-only. Para rollback, restaure o dump e o pacote de código do rollout.',
        );
    }

    private function assertStructuralSchemaReady(): void
    {
        foreach ($this->constraints as $constraint) {
            if (
                ! Schema::hasTable($constraint['table'])
                || ! Schema::hasColumn($constraint['table'], $constraint['column'])
                || ! Schema::hasTable($constraint['foreign'])
            ) {
                throw new RuntimeException(
                    "Schema estrutural incompleto para {$constraint['table']}.{$constraint['column']} -> {$constraint['foreign']}.",
                );
            }
        }

        $requiredHistoryColumns = [
            'pedidos' => [
                'escola_id_legado',
                'escola_nome_snapshot',
                'solicitante_id_legado',
                'solicitante_nome_snapshot',
                'solicitante_email_snapshot',
                'responsavel_id_legado',
                'responsavel_nome_snapshot',
                'responsavel_email_snapshot',
                'comentario_gestor_user_id_legado',
                'comentario_gestor_user_nome_snapshot',
                'comentario_gestor_user_email_snapshot',
            ],
            'pedido_historicos' => ['usuario_id_legado', 'usuario_nome_snapshot', 'usuario_email_snapshot'],
            'pedido_arquivos' => ['usuario_id_legado', 'usuario_nome_snapshot', 'usuario_email_snapshot'],
            'export_requests' => ['user_id_legado', 'user_nome_snapshot', 'user_email_snapshot'],
        ];

        foreach ($requiredHistoryColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Schema histórico incompleto: {$table}.{$column} ausente.");
                }
            }
        }
    }

    private function assertHistoricalPreservation(): void
    {
        $actors = [
            ['pedidos', 'solicitante_id', 'solicitante_id_legado', 'solicitante_nome_snapshot', 'solicitante_email_snapshot'],
            ['pedidos', 'responsavel_id', 'responsavel_id_legado', 'responsavel_nome_snapshot', 'responsavel_email_snapshot'],
            ['pedidos', 'comentario_gestor_user_id', 'comentario_gestor_user_id_legado', 'comentario_gestor_user_nome_snapshot', 'comentario_gestor_user_email_snapshot'],
            ['pedido_historicos', 'usuario_id', 'usuario_id_legado', 'usuario_nome_snapshot', 'usuario_email_snapshot'],
            ['pedido_arquivos', 'usuario_id', 'usuario_id_legado', 'usuario_nome_snapshot', 'usuario_email_snapshot'],
            ['export_requests', 'user_id', 'user_id_legado', 'user_nome_snapshot', 'user_email_snapshot'],
        ];

        foreach ($actors as [$table, $current, $legacy, $name, $email]) {
            $withoutLegacy = DB::table($table)
                ->whereNotNull($current)
                ->whereNull($legacy)
                ->count();

            if ($withoutLegacy > 0) {
                throw new RuntimeException("Backfill pendente: {$table}.{$legacy} está vazio em {$withoutLegacy} registro(s) com ator atual.");
            }

            $withoutSnapshot = DB::table("{$table} as origem")
                ->join('users as ator_atual', 'ator_atual.id', '=', "origem.{$current}")
                ->where(function ($query) use ($name, $email): void {
                    $query->whereNull("origem.{$name}")
                        ->orWhere("origem.{$name}", '')
                        ->orWhereNull("origem.{$email}")
                        ->orWhere("origem.{$email}", '');
                })
                ->count();

            if ($withoutSnapshot > 0) {
                throw new RuntimeException("Backfill pendente: {$table} possui {$withoutSnapshot} ator(es) atuais sem snapshot completo.");
            }
        }

        $schoolsWithoutHistory = DB::table('pedidos')
            ->whereNotNull('escola_id')
            ->where(function ($query): void {
                $query->whereNull('escola_id_legado')
                    ->orWhereNull('escola_nome_snapshot')
                    ->orWhere('escola_nome_snapshot', '');
            })
            ->count();

        if ($schoolsWithoutHistory > 0) {
            throw new RuntimeException("Backfill pendente: {$schoolsWithoutHistory} pedido(s) não preservaram a Escola histórica.");
        }
    }

    private function assertPedidoSchoolIntegrity(): void
    {
        if (! Schema::hasTable('pedidos') || ! Schema::hasColumn('pedidos', 'escola_id')) {
            return;
        }

        $withoutSchool = DB::table('pedidos')->whereNull('escola_id')->count();

        if ($withoutSchool > 0) {
            throw new RuntimeException("Existem {$withoutSchool} pedidos sem escola; execute dados:backfill-estrutural antes da migration.");
        }

        if (! Schema::hasColumn('pedidos', 'pedido_principal_id')) {
            return;
        }

        $differentSchool = DB::table('pedidos as adicional')
            ->join('pedidos as principal', 'principal.id', '=', 'adicional.pedido_principal_id')
            ->whereColumn('adicional.escola_id', '<>', 'principal.escola_id')
            ->count();

        if ($differentSchool > 0) {
            throw new RuntimeException("Existem {$differentSchool} pedidos adicionais com escola divergente do principal.");
        }
    }

    private function assertReferenceIntegrity(string $table, string $column, string $foreignTable): void
    {
        if (
            ! Schema::hasTable($table)
            || ! Schema::hasColumn($table, $column)
            || ! Schema::hasTable($foreignTable)
        ) {
            return;
        }

        $orphans = DB::table("{$table} as origem")
            ->leftJoin("{$foreignTable} as destino", "destino.id", '=', "origem.{$column}")
            ->whereNotNull("origem.{$column}")
            ->whereNull('destino.id')
            ->count();

        if ($orphans > 0) {
            throw new RuntimeException("Integridade pendente: {$table}.{$column} possui {$orphans} referências órfãs.");
        }
    }

    /**
     * @param array<int, array{table:string,column:string,foreign:string,nullable:?bool,on_delete:string,name:string}> $constraints
     */
    private function replaceTableForeignKeys(string $tableName, array $constraints): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $available = collect($constraints)
            ->filter(fn (array $constraint): bool => Schema::hasColumn($tableName, $constraint['column'])
                && Schema::hasTable($constraint['foreign']))
            ->values();

        if ($available->isEmpty()) {
            return;
        }

        $this->dropForeignKeysForColumns($tableName, $available->pluck('column')->all());

        Schema::table($tableName, function (Blueprint $table) use ($available): void {
            foreach ($available as $constraint) {
                if ($constraint['nullable'] !== null) {
                    $table->unsignedBigInteger($constraint['column'])
                        ->nullable($constraint['nullable'])
                        ->change();
                }
            }

            foreach ($available as $constraint) {
                $foreign = $table->foreign($constraint['column'], $constraint['name'])
                    ->references('id')
                    ->on($constraint['foreign']);

                match ($constraint['on_delete']) {
                    'set null' => $foreign->nullOnDelete(),
                    default => $foreign->restrictOnDelete(),
                };
            }
        });
    }

    /** @param array<int, string> $columns */
    private function dropForeignKeysForColumns(string $tableName, array $columns): void
    {
        if (! Schema::hasTable($tableName)) {
            return;
        }

        $foreignKeys = collect(Schema::getForeignKeys($tableName))
            ->filter(fn (array $foreign): bool => collect($foreign['columns'] ?? [])->intersect($columns)->isNotEmpty())
            ->values();

        if ($foreignKeys->isEmpty()) {
            return;
        }

        Schema::table($tableName, function (Blueprint $table) use ($foreignKeys): void {
            foreach ($foreignKeys as $foreign) {
                $column = (string) collect($foreign['columns'] ?? [])->first();

                $table->dropForeign(
                    filled($foreign['name'] ?? null)
                        ? $foreign['name']
                        : [$column],
                );
            }
        });
    }

    /**
     * O SQLite reconstrói a tabela ao alterar FKs e perde a expressão de colunas
     * geradas. Recria somente a coluna técnica afetada após esse rebuild.
     */
    private function restoreSqliteNormalizedEmailColumn(): void
    {
        if (
            DB::getDriverName() !== 'sqlite'
            || ! Schema::hasTable('servidores')
            || ! Schema::hasColumn('servidores', 'email')
        ) {
            return;
        }

        if (
            Schema::hasColumn('servidores', 'email_normalizado')
            && Schema::hasIndex('servidores', 'idx_servidores_email_normalizado')
        ) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropIndex('idx_servidores_email_normalizado');
            });
        }

        if (Schema::hasColumn('servidores', 'email_normalizado')) {
            Schema::table('servidores', function (Blueprint $table): void {
                $table->dropColumn('email_normalizado');
            });
        }

        Schema::table('servidores', function (Blueprint $table): void {
            $table->string('email_normalizado')
                ->virtualAs("NULLIF(LOWER(TRIM(email)), '')");
        });

        Schema::table('servidores', function (Blueprint $table): void {
            $table->index('email_normalizado', 'idx_servidores_email_normalizado');
        });
    }
};
