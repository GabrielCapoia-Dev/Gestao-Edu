<?php

namespace App\Services\Avaliacoes;

use Illuminate\Database\Query\Builder as QueryBuilder;

/**
 * O dashboard é um acesso agregado à avaliação inteira. Para não misturar
 * respostas antigas em JSON com respostas novas no relacional dentro da mesma
 * consulta, avaliações ativas selecionadas no acompanhamento são inicializadas
 * turma a turma antes da leitura agregada. Cada turma paga esse custo somente
 * uma vez.
 */
class AvaliacaoDashboardOnDemandQueryServiceLazy extends AvaliacaoDashboardOnDemandQueryService
{
    public function __construct(
        TurmaAvaliacaoAlunoScopeService $turmaAlunoScopeService,
        private readonly AvaliacaoMigracaoLazyService $migracaoLazy,
    ) {
        parent::__construct($turmaAlunoScopeService);
    }

    public function esperados(array $avaliacaoIds): QueryBuilder
    {
        $this->migracaoLazy->garantirAvaliacoesAtivas($avaliacaoIds);

        return parent::esperados($avaliacaoIds);
    }

    public function respostas(array $avaliacaoIds, bool $somenteCompletas = false): QueryBuilder
    {
        $this->migracaoLazy->garantirAvaliacoesAtivas($avaliacaoIds);

        return parent::respostas($avaliacaoIds, $somenteCompletas);
    }
}
