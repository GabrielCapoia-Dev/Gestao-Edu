@php
    use App\Models\Pedido;

    $record = $getRecord();
    $feedback = $record instanceof Pedido ? $record->ultimoFeedback : null;

    if (! $feedback && $record instanceof Pedido && $record->relationLoaded('feedbackItens')) {
        $feedback = $record->feedbackItens->sortByDesc('created_at')->first()?->feedback;
    }

    $comentarios = $feedback?->itens
        ?->where('pedido_id', $record?->getKey())
        ->pluck('comentario')
        ->map(fn ($comentario): string => trim((string) $comentario))
        ->filter()
        ->values() ?? collect();

    if ($comentarios->isEmpty() && filled($feedback?->descricao)) {
        $comentarios = collect([trim((string) $feedback->descricao)]);
    }

    $comentario = $comentarios->implode(' • ');
    $podeExpandir = mb_strlen($comentario) > 180 || str_contains($comentario, "\n");
@endphp

<div class="pedido-card-feedback-comments" x-data="{ expanded: false }">
    <div class="pedido-card-block-label">
        {{ $comentarios->count() > 1 ? 'Comentários dos problemas' : 'Comentário do problema' }}
    </div>

    @if ($comentario !== '')
        <div
            @class([
                'pedido-card-feedback-comments-text',
                'pedido-card-feedback-comments-text--collapsible' => $podeExpandir,
            ])
            @if ($podeExpandir)
                :class="{ 'pedido-card-feedback-comments-text--expanded': expanded }"
            @endif
        >{{ $comentario }}</div>

        @if ($podeExpandir)
            <button
                type="button"
                class="pedido-card-description-toggle"
                x-on:click.stop="expanded = ! expanded"
                x-text="expanded ? 'Ver menos' : 'Ver mais'"
            >Ver mais</button>
        @endif
    @else
        <div class="pedido-card-description-empty">Comentário não informado.</div>
    @endif
</div>
