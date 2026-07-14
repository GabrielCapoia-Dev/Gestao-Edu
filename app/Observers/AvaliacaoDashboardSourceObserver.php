<?php

namespace App\Observers;

use App\Models\Avaliacao;
use App\Models\Pauta;
use App\Models\Turma;
use App\Services\Avaliacoes\AvaliacaoDashboardFactsService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class AvaliacaoDashboardSourceObserver
{
    public function saved(Model $model): void
    {
        $this->requestFor($model);
    }

    public function deleted(Model $model): void
    {
        $this->requestFor($model);
    }

    private function requestFor(Model $model): void
    {
        $ids = match (true) {
            $model instanceof Avaliacao => [(int) $model->getKey()],
            $model instanceof Pauta => DB::table('avaliacao_pauta')
                ->where('pauta_id', (int) $model->getKey())
                ->pluck('avaliacao_id')->all(),
            $model instanceof Turma => DB::table('avaliacao_turma')
                ->where('turma_id', (int) $model->getKey())
                ->pluck('avaliacao_id')->all(),
            default => [],
        };

        foreach ($ids as $id) {
            app(AvaliacaoDashboardFactsService::class)->requestRebuild((int) $id);
        }
    }
}
