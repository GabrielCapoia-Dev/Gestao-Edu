<?php

namespace App\Console\Commands;

use App\Models\Avaliacao;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Console\Command;

class RebuildAvaliacaoDashboardFactsCommand extends Command
{
    protected $signature = 'avaliacoes:rebuild-dashboard-facts {avaliacaoId? : Avaliação específica} {--queue : Apenas enfileira o processamento}';

    protected $description = 'Calcula os fatos iniciais do acompanhamento de avaliações.';

    protected $aliases = ['avaliacoes:build-dashboard-facts'];

    public function handle(AvaliacaoDashboardFactsService $service): int
    {
        $ids = $this->argument('avaliacaoId')
            ? [(int) $this->argument('avaliacaoId')]
            : Avaliacao::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($ids as $id) {
            if ($this->option('queue')) {
                $service->requestRebuild($id);
            } else {
                $this->output->write("Consolidando avaliação {$id}... ");
                $service->rebuild($id);
                $this->info('ok');
            }
        }

        $this->info(count($ids) . ' avaliação(ões) consolidada(s).');

        return self::SUCCESS;
    }
}
