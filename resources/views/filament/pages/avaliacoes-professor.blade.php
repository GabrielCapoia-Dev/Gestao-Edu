<x-filament-panels::page>
    @livewire(
        'avaliacoes.avaliacao-turma-workspace',
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
