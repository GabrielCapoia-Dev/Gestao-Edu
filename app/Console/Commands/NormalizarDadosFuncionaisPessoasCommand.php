<?php

namespace App\Console\Commands;

use App\Services\PessoaDadosFuncionaisLegadoService;
use Illuminate\Console\Command;

class NormalizarDadosFuncionaisPessoasCommand extends Command
{
    protected $signature = 'pessoas:normalizar-dados-funcionais
                            {--dry-run : Apenas informa o que seria alterado}
                            {--apply : Aplica apenas inferências inequívocas}
                            {--email= : Restringe a normalização a um e-mail}';

    protected $description = 'Preenche carga horária e jornada legadas a partir das matrículas, sem sobrescrever dados informados';

    public function handle(PessoaDadosFuncionaisLegadoService $service): int
    {
        if ($this->option('dry-run') && $this->option('apply')) {
            $this->error('Use somente uma opção: --dry-run ou --apply.');

            return self::INVALID;
        }

        $aplicar = (bool) $this->option('apply');
        if (! $aplicar) {
            $this->warn('Modo de simulação: nenhuma gravação será feita. Use --apply para confirmar.');
        }

        $stats = $service->executar(
            aplicar: $aplicar,
            email: filled($this->option('email')) ? (string) $this->option('email') : null,
        );

        $this->info($aplicar ? 'Normalização funcional concluída.' : 'Simulação da normalização funcional concluída.');
        foreach ($stats as $chave => $valor) {
            $this->line("{$chave}: {$valor}");
        }

        return self::SUCCESS;
    }
}
