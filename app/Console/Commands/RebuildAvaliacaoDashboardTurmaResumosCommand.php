<?php

namespace App\Console\Commands;

use App\Jobs\AtualizarAvaliacaoDashboardTurmaResumoJob;
use App\Models\Avaliacao;
use App\Models\AvaliacaoTurmaCiclo;
use App\Services\Avaliacoes\AvaliacaoDashboardTurmaResumoService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RebuildAvaliacaoDashboardTurmaResumosCommand extends Command
{
    protected $signature = 'avaliacoes:rebuild-dashboard-turma-resumos
        {avaliacaoId? : Avaliação específica}
        {--sync : Executa o recálculo no processo atual}';

    protected $description = 'Reconstrói os resumos operacionais do dashboard de avaliações por turma.';

    public function handle(AvaliacaoDashboardTurmaResumoService $resumos): int
    {
        if (! $resumos->disponivel()) {
            $this->error('A tabela de resumos ainda não foi criada. Execute as migrations.');

            return self::FAILURE;
        }

        $avaliacoes = Avaliacao::query()
            ->when($this->argument('avaliacaoId'), fn ($query, $id) => $query->whereKey((int) $id))
            ->where('status', Avaliacao::STATUS_ATIVA)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id);
        $total = 0;

        foreach ($avaliacoes as $avaliacaoId) {
            $turmaIds = DB::table('avaliacao_turma_ciclos')
                ->where('avaliacao_id', $avaliacaoId)
                ->whereIn('status', [AvaliacaoTurmaCiclo::STATUS_ABERTA, AvaliacaoTurmaCiclo::STATUS_REABERTA])
                ->pluck('turma_avaliativa_id')
                ->map(fn ($id): int => (int) $id)
                ->unique();

            foreach ($turmaIds as $turmaId) {
                if ($this->option('sync')) {
                    $resumos->recalcular($avaliacaoId, $turmaId);
                } else {
                    AtualizarAvaliacaoDashboardTurmaResumoJob::dispatch($avaliacaoId, $turmaId);
                }
                $total++;
            }
        }

        $this->info(($this->option('sync') ? 'Recalculadas' : 'Enfileiradas').": {$total} turma(s).");

        return self::SUCCESS;
    }
}
