<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class DispatchAvaliacaoDashboardPendenciasCommand extends Command
{
    protected $signature = 'avaliacoes:dispatch-dashboard-pendencias
        {--limit=200 : Quantidade máxima de pendências a despachar}
        {--force : Ignora o intervalo de recuperação; destinado ao deploy}';

    protected $description = 'Comando legado desativado; não existem mais pendências de fatos do dashboard.';

    public function handle(): int
    {
        $this->info('Processamento de pendências desativado; nenhum job foi despachado.');

        return self::SUCCESS;
    }
}
