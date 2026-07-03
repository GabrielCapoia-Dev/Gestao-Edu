@php
    use App\Models\Pedido;

    $record = $getRecord();
    $descricao = $record instanceof Pedido ? trim((string) ($record->descricao_pedido ?? '')) : '';
    $podeExpandir = mb_strlen($descricao) > 140 || str_contains($descricao, "\n");
@endphp

<div class="pedido-card-description" x-data="{ expanded: false }">
    <div class="pedido-card-block-label">Descrição do pedido</div>

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
        <div class="pedido-card-description-empty">Não informado</div>
    @endif
</div>
