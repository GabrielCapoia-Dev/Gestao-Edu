<?php

namespace App\Console\Commands;

use App\Models\Avaliacao;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Console\Command;

class RebuildAvaliacaoDashboardFactsCommand extends Command
{
    protected $signature = 'avaliacoes:rebuild-dashboard-facts {avaliacaoId? : Avaliação específica}';

    protected $description = 'Solicita a consolidação dos fatos do acompanhamento de avaliações.';

    public function handle(AvaliacaoDashboardFactsService $service): int
    {
        $ids = $this->argument('avaliacaoId')
            ? [(int) $this->argument('avaliacaoId')]
            : Avaliacao::query()->pluck('id')->map(fn ($id): int => (int) $id)->all();

        foreach ($ids as $id) {
            $service->requestRebuild($id);
        }

        $this->info(count($ids) . ' avaliação(ões) enviada(s) para consolidação.');

        return self::SUCCESS;
    }
}
