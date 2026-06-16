<?php

namespace App\Console\Commands;

use App\Models\Professor;
use App\Services\ServidorService;
use Illuminate\Console\Command;

class BackfillServidoresFromProfessores extends Command
{
    protected $signature = 'servidores:backfill-professores {--dry-run : Mostra a quantidade de professores que seriam processados sem alterar dados}';

    protected $description = 'Cria ou sincroniza servidores e funções a partir dos professores existentes';

    public function handle(ServidorService $service): int
    {
        if ($this->option('dry-run')) {
            $total = Professor::query()->count();
            $semServidor = Professor::query()->whereNull('servidor_id')->count();

            $this->info("Professores encontrados: {$total}");
            $this->info("Professores sem servidor vinculado: {$semServidor}");

            return Command::SUCCESS;
        }

        $resultado = $service->backfillProfessores();

        $this->info('Backfill de servidores concluído.');
        $this->line("Professores processados: {$resultado['professores_processados']}");
        $this->line("Servidores criados ou atualizados: {$resultado['servidores_criados_ou_atualizados']}");
        $this->line("Funções vinculadas: {$resultado['funcoes_vinculadas']}");

        return Command::SUCCESS;
    }
}
