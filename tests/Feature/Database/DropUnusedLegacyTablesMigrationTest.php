<?php

namespace Tests\Feature\Database;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DropUnusedLegacyTablesMigrationTest extends TestCase
{
    private const TABLES = [
        'aluno_laudo',
        'aluno_retencoes',
        'laudos',
        'avaliacao_dashboard_pendencias',
        'avaliacao_dashboard_escopo_pendencias',
        'avaliacao_dashboard_fatos',
        'avaliacao_dashboard_consolidacoes',
        'pedido_merenda_items',
        'activity_log',
        'perf_digest_snapshots',
        'backup_avaliacao_documentos_pautas_invalidas_20260901',
        'backup_avaliacao_documentos_pauta_415_20260901',
        'bkp_av_resp_operacionais_inv_20260901',
    ];

    public function test_remove_tabelas_legadas_e_pode_ser_executada_novamente(): void
    {
        foreach (self::TABLES as $table) {
            Schema::create($table, function (Blueprint $blueprint): void {
                $blueprint->id();
            });
        }

        $migration = require database_path(
            'migrations/2026_09_02_090000_drop_unused_legacy_tables.php'
        );

        $migration->up();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }

        // dropIfExists deve tornar segura uma eventual execucao manual repetida.
        $migration->up();

        foreach (self::TABLES as $table) {
            $this->assertFalse(Schema::hasTable($table), $table);
        }
    }
}
