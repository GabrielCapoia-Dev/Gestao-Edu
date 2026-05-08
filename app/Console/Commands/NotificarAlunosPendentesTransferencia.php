<?php

namespace App\Console\Commands;

use App\Models\Aluno;
use App\Services\AlunoTransferenciaPendenteService;
use Illuminate\Console\Command;

class NotificarAlunosPendentesTransferencia extends Command
{
    protected $signature = 'app:notificar-alunos-pendentes-transferencia';

    protected $description = 'Notifica diariamente as escolas de origem sobre alunos com transferencia pendente';

    public function handle(AlunoTransferenciaPendenteService $service): int
    {
        $enviadas = 0;

        Aluno::query()
            ->with(['turma.escola', 'pendenciaOrigem.turma.escola'])
            ->where('status', Aluno::STATUS_PENDENTE)
            ->whereNotNull('pendencia_origem_aluno_id')
            ->orderBy('id')
            ->chunkById(100, function ($pendentes) use ($service, &$enviadas): void {
                foreach ($pendentes as $pendente) {
                    $enviadas += $service->notificarPendencia($pendente, null, true);
                }
            });

        $this->info("Notificacoes de transferencia pendente enviadas: {$enviadas}");

        return self::SUCCESS;
    }
}
