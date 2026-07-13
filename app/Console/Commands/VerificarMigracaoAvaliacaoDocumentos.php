<?php

namespace App\Console\Commands;

use App\Support\Migrations\AvaliacaoDocumentosMigrator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Throwable;

class VerificarMigracaoAvaliacaoDocumentos extends Command
{
    protected $signature = 'avaliacoes:verificar-migracao-documentos {--repair : Reprocessa legado residual e recalcula metricas do documento}';

    protected $description = 'Verifica contagens da migracao de respostas para documentos avaliativos';

    public function handle(): int
    {
        $migrator = new AvaliacaoDocumentosMigrator();

        if ($this->option('repair')) {
            $this->info('Reprocessando migracao residual...');
            $statsRepair = $migrator->migrateFromLegacy();
            $this->table(['Metrica', 'Valor'], collect($statsRepair)->map(fn ($v, $k) => [$k, $v])->values()->all());
        }

        $stats = $migrator->stats();

        $this->table(['Metrica', 'Valor'], [
            ['Documentos', $stats['documentos']],
            ['Respostas no payload (soma totais)', $stats['respondidas_payload']],
            ['Historicos', $stats['historicos']],
            ['Pares legado (avaliacao x aluno)', $stats['legado_pares']],
            ['Respostas legado com alternativa', $stats['legado_respostas']],
            ['Tabelas legadas presentes', $migrator->hasLegacyTables() ? 'sim' : 'nao'],
            ['Tabela fatos presente', Schema::hasTable('avaliacao_resposta_fatos') ? 'sim (deve ser removida)' : 'nao'],
        ]);

        try {
            $migrator->assertMigracaoConsistente();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        if (Schema::hasTable('avaliacao_resposta_fatos')) {
            $this->warn('Tabela avaliacao_resposta_fatos ainda existe. Rode as migrations pendentes.');
        }

        $this->info('Verificacao concluida: estrutura consistente (documento = fonte unica).');

        return self::SUCCESS;
    }
}
