<?php

namespace App\Console\Commands;

use App\Models\Professor;
use App\Services\PessoaProfessorService;
use Illuminate\Console\Command;

class SyncProfessorVinculos extends Command
{
    protected $signature = 'pessoas:sync-professor-vinculos {--dry-run : Apenas exibe o que seria alterado}';

    protected $description = 'Sincroniza vínculos funcionais shadow a partir dos registros de professor';

    public function handle(PessoaProfessorService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $processados = 0;

        Professor::query()
            ->whereNotNull('servidor_id')
            ->where('ativo', true)
            ->with('servidor')
            ->orderBy('id')
            ->chunkById(100, function ($professores) use ($dryRun, $service, &$processados): void {
                $servidorIds = $professores->pluck('servidor_id')->unique()->filter();

                foreach ($servidorIds as $servidorId) {
                    $servidor = $professores->firstWhere('servidor_id', $servidorId)?->servidor;

                    if (! $servidor) {
                        continue;
                    }

                    if ($dryRun) {
                        $this->line("Dry-run: sincronizaria vínculos do servidor #{$servidor->id}");
                    } else {
                        $service->sincronizarVinculosFuncionaisSilenciosos($servidor);
                    }

                    $processados++;
                }
            });

        $this->info('Sync de vínculos professor concluído.');
        $this->line("Servidores processados: {$processados}");

        return Command::SUCCESS;
    }
}