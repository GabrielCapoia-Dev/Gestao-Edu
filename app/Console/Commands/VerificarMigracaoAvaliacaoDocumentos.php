<?php

namespace App\Console\Commands;

use App\Support\Migrations\AvaliacaoDocumentosMigrator;
use Illuminate\Console\Command;
use Throwable;

class VerificarMigracaoAvaliacaoDocumentos extends Command
{
    protected $signature = 'avaliacoes:verificar-migracao-documentos {--repair : Reprocessa legado residual e recalcula fatos}';

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
            ['Fatos', $stats['fatos']],
            ['Historicos', $stats['historicos']],
            ['Pares legado (avaliacao x aluno)', $stats['legado_pares']],
            ['Respostas legado com alternativa', $stats['legado_respostas']],
            ['Tabelas legadas presentes', $migrator->hasLegacyTables() ? 'sim' : 'nao'],
        ]);

        try {
            $migrator->assertMigracaoConsistente();
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->info('Verificacao concluida: estrutura consistente.');

        return self::SUCCESS;
    }
}
