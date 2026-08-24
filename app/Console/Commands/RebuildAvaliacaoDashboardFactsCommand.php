<?php

namespace App\Console\Commands;

use App\Models\Avaliacao;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Console\Command;

class RebuildAvaliacaoDashboardFactsCommand extends Command
{
    protected $signature = 'avaliacoes:rebuild-dashboard-facts
        {avaliacaoId? : Avaliação específica}
        {--all : Enfileira explicitamente todas as avaliações}
        {--sync : Executa no processo atual em vez da fila dedicada}
        {--queue : Compatibilidade; o uso da fila já é o padrão}';

    protected $description = 'Calcula os fatos iniciais do acompanhamento de avaliações.';

    protected $aliases = ['avaliacoes:build-dashboard-facts'];

    public function handle(AvaliacaoDashboardFactsService $service): int
    {
        $avaliacaoId = (int) ($this->argument('avaliacaoId') ?? 0);

        if ($avaliacaoId <= 0 && ! $this->option('all')) {
            $this->error('Informe o argumento avaliacaoId ou use a opção --all explicitamente.');

            return self::INVALID;
        }

        $ids = $avaliacaoId > 0
            ? [$avaliacaoId]
            : Avaliacao::query()->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($ids as $id) {
            if ($this->option('sync')) {
                $this->output->write("Consolidando avaliação {$id}... ");
                if ($service->rebuild($id, 'comando_manual_sincrono')) {
                    $this->info('ok');
                } else {
                    $this->warn('já existe um reparo completo em processamento');
                }

                continue;
            }

            if ($service->requestRebuild($id, 'comando_manual')) {
                $this->line("Avaliação {$id} enviada para a fila dashboard.");
            } else {
                $this->warn("Avaliação {$id} já possui reparo completo em processamento.");
            }
        }

        $this->info('Avaliações encaminhadas ou processadas: '.count($ids).'.');

        return self::SUCCESS;
    }
}
