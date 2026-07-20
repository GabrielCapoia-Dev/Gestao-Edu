@php
    use App\Models\Pedido;

    $record = $getRecord();
    $feedback = $record instanceof Pedido ? $record->ultimoFeedback : null;

    if (! $feedback && $record instanceof Pedido && $record->relationLoaded('feedbackItens')) {
        $feedback = $record->feedbackItens->sortByDesc('created_at')->first()?->feedback;
    }

    $descricao = trim((string) ($feedback?->descricao ?? ''));
    $podeExpandir = mb_strlen($descricao) > 140 || str_contains($descricao, "\n");
@endphp

<div class="pedido-card-description" x-data="{ expanded: false }">
    <div class="pedido-card-block-label">Descrição final da avaliação</div>

    @if ($descricao !== '')
        <div
            @class([
                'pedido-card-description-text',
                'pedido-card-description-text--collapsible' => $podeExpandir,
            ])
            @if ($podeExpandir)
                :class="{ 'pedido-card-description-text--expanded': expanded }"
            @endif
        >{{ $descricao }}</div>

        @if ($podeExpandir)
            <button
                type="button"
                class="pedido-card-description-toggle"
                x-on:click.stop="expanded = ! expanded"
                x-text="expanded ? 'Ver menos' : 'Ver mais'"
            >Ver mais</button>
        @endif
    @else
        <div class="pedido-card-description-empty">
            {{ $feedback ? 'Avaliação concluída sem descrição final.' : 'Ainda sem avaliação final.' }}
        </div>
    @endif
</div>
