<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Remove estruturas legadas sem consumidores no runtime atual.
     *
     * A migration e registrada pelo Laravel e executada uma unica vez.
     * dropIfExists tambem permite aplicar a limpeza em bancos onde parte das
     * tabelas ja tenha sido removida manualmente.
     */
    public function up(): void
    {
        $tables = [
            // Dependentes devem ser removidas antes das tabelas referenciadas.
            'aluno_laudo',
            'aluno_retencoes',
            'laudos',

            // Pipeline materializado de avaliacoes desativado em 25/08/2026.
            'avaliacao_dashboard_pendencias',
            'avaliacao_dashboard_escopo_pendencias',
            'avaliacao_dashboard_fatos',
            'avaliacao_dashboard_consolidacoes',

            // Estruturas substituidas, diagnosticas ou de pacotes removidos.
            'pedido_merenda_items',
            'activity_log',
            'perf_digest_snapshots',

            // Backups operacionais temporarios de 01/09/2026.
            'backup_avaliacao_documentos_pautas_invalidas_20260901',
            'backup_avaliacao_documentos_pauta_415_20260901',
            'bkp_av_resp_operacionais_inv_20260901',
        ];

        foreach ($tables as $table) {
            Schema::dropIfExists($table);
        }
    }

    public function down(): void
    {
        // Irreversivel: recriar tabelas vazias nao restauraria os dados removidos.
    }
};
