<?php

namespace App\Console\Commands;

use App\Services\PessoaLegadoNormalizacaoService;
use Illuminate\Console\Command;

class NormalizarPessoasLegadoCommand extends Command
{
    protected $signature = 'pessoas:normalizar-legado
                            {--dry-run : Apenas simula alterações}
                            {--email= : Normaliza apenas um e-mail}';

    protected $description = 'Normaliza professores legados para Pessoa + professor_matriculas (idempotente)';

    public function handle(PessoaLegadoNormalizacaoService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $email = $this->option('email') ? (string) $this->option('email') : null;

        if ($dryRun) {
            $this->warn('Modo dry-run: nenhuma gravação será feita.');
        }

        $stats = $service->normalizar($dryRun, $email);

        $this->info('Normalização de pessoas legadas concluída.');
        foreach ($stats as $chave => $valor) {
            if ($chave === 'anomalias') {
                continue;
            }
            $this->line(sprintf('%s: %s', $chave, is_scalar($valor) ? $valor : json_encode($valor)));
        }

        $anomalias = $stats['anomalias'] ?? [];
        if (is_array($anomalias) && $anomalias !== []) {
            $this->warn('Anomalias ('.count($anomalias).'):');
            foreach (array_slice($anomalias, 0, 30) as $linha) {
                $this->line(' - '.$linha);
            }
            if (count($anomalias) > 30) {
                $this->line(' - ... +'.(count($anomalias) - 30).' mais');
            }
        }

        return Command::SUCCESS;
    }
}
