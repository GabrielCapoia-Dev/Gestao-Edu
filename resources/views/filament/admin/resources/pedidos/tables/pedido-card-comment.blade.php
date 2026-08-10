@php
    use App\Models\Pedido;

    $record = $getRecord();
    $comentario = $record instanceof Pedido ? trim((string) ($record->comentario_gestor ?? '')) : '';
    $autor = $record instanceof Pedido && $comentario !== ''
        ? $record->comentarioGestorUsuarioNomeExibicao()
        : null;
@endphp

<div class="pedido-card-comment">
    <div class="pedido-card-block-label">Comentário</div>

    @if ($comentario !== '')
        <div class="pedido-card-comment-text">{{ $comentario }}</div>

        @if ($autor || ($record instanceof Pedido && $record->comentario_gestor_at))
            <div class="pedido-card-comment-meta">
                @if ($autor)
                    {{ $autor }}
                @endif

                @if ($record instanceof Pedido && $record->comentario_gestor_at)
                    <span>{{ $record->comentario_gestor_at->format('d/m/Y H:i') }}</span>
                @endif
            </div>
        @endif
    @else
        <div class="pedido-card-comment-empty">Sem comentário</div>
    @endif
</div>
