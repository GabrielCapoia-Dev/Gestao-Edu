<x-filament-panels::page>
    <style>
        /* Em Minhas Avaliações, mantém as turmas no layout clássico: uma por linha. */
        .av-professor-page .av-turma-grid {
            grid-template-columns: minmax(0, 1fr) !important;
        }
    </style>

    @livewire(
        'avaliacoes.avaliacao-turma-professor-workspace',
        [
            'avaliacaoId' => $initialAvaliacaoId,
            'turmaId' => $initialTurmaId,
            'escolaId' => $initialEscolaId,
            'serieId' => $initialSerieId,
            'modo' => 'professor',
            'canEdit' => $this->podeResponder(),
        ],
        key('avaliacoes-professor-workspace-' . ($initialAvaliacaoId ?? 'nova') . '-' . ($initialTurmaId ?? 'todas'))
    )
</x-filament-panels::page>