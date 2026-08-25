<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class RebuildAvaliacaoDashboardFactsCommand extends Command
{
    protected $signature = 'avaliacoes:rebuild-dashboard-facts
        {avaliacaoId? : Avaliação específica}
        {--all : Enfileira explicitamente todas as avaliações}
        {--sync : Executa no processo atual em vez da fila dedicada}
        {--queue : Compatibilidade; o uso da fila já é o padrão}';

    protected $description = 'Comando legado desativado; o acompanhamento agora é calculado sob demanda.';

    protected $aliases = ['avaliacoes:build-dashboard-facts'];

    public function handle(): int
    {
        $this->warn('Rebuild desativado. Os indicadores são calculados diretamente ao abrir ou atualizar o acompanhamento.');

        return self::SUCCESS;
    }
}
