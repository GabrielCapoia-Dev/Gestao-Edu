<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array<string, list<string>>> */
    private const INDEXES = [
        'eventos_calendario' => [
            'idx_evento_cal_status_periodo' => ['ativo', 'status', 'data_inicio', 'data_fim'],
            'idx_evento_cal_categoria_ativo_inicio' => ['categoria', 'ativo', 'data_inicio'],
            'idx_evento_cal_escola_inicio' => ['escola_id', 'data_inicio'],
            'idx_evento_cal_setor_inicio' => ['setor_id', 'data_inicio'],
            'idx_evt_cal_inicio_id' => ['data_inicio', 'id'],
            'idx_evt_cal_criado_id' => ['created_at', 'id'],
        ],
        'reservas_veiculos' => [
            'idx_res_veic_disponibilidade' => ['veiculo_transporte_id', 'status', 'data_inicio', 'data_fim'],
            'idx_res_veic_escola' => ['escola_id', 'status', 'data_inicio'],
            'idx_res_veic_usuario' => ['usuario_id', 'status', 'data_inicio'],
            'idx_res_veic_grupo' => ['grupo_recorrencia'],
            'idx_res_veic_status_periodo' => ['status', 'data_inicio', 'data_fim'],
        ],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $tableName => $indexes) {
            if (! Schema::hasTable($tableName)) {
                continue;
            }

            $missing = array_filter(
                $indexes,
                fn (array $columns, string $name): bool => ! Schema::hasIndex($tableName, $name),
                ARRAY_FILTER_USE_BOTH,
            );

            if ($missing === []) {
                continue;
            }

            Schema::table($tableName, function (Blueprint $table) use ($missing): void {
                foreach ($missing as $name => $columns) {
                    $table->index($columns, $name);
                }
            });
        }
    }

    public function down(): void
    {
        // Indexes repair the imported Hub schema and should not be removed on rollback.
    }
};
