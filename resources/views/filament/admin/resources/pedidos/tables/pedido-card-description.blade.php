@php
    use App\Models\Pedido;

    $record = $getRecord();
    $descricao = $record instanceof Pedido ? trim((string) ($record->descricao_pedido ?? '')) : '';
@endphp

<div class="pedido-card-description">
    <div class="pedido-card-block-label">Descrição do pedido</div>

    @if ($descricao !== '')
        <div class="pedido-card-description-text">{{ $descricao }}</div>
    @else
        <div class="pedido-card-description-empty">Não informado</div>
    @endif
</div>
