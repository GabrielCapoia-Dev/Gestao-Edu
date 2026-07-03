@php
    use App\Models\FeedbackPedido;

    $record = $getRecord();
    $comentario = '';

    if ($record instanceof FeedbackPedido) {
        $comentario = trim((string) ($record->descricao ?? ''));

        if ($comentario === '') {
            $comentario = $record->itens
                ->map(function ($item): ?string {
                    $texto = trim((string) ($item->comentario ?? ''));

                    if ($texto === '') {
                        return null;
                    }

                    $problema = $item->problema?->texto_problema ?: 'Problema';

                    return "{$problema}: {$texto}";
                })
                ->filter()
                ->join("\n");
        }
    }
@endphp

@if ($comentario !== '')
    <div style="min-width: 16rem; max-width: 34rem; border: 1px solid #fca5a5; background: #fff7f7; border-radius: 0.5rem; padding: 0.65rem 0.75rem;">
        <div style="color: #64748b; font-size: 0.75rem; line-height: 1rem; font-weight: 600;">Comentário</div>
        <div style="margin-top: 0.25rem; color: #0f172a; font-size: 0.875rem; line-height: 1.35; white-space: pre-line; overflow-wrap: anywhere;">{{ $comentario }}</div>
    </div>
@else
    <span style="color: #64748b;">Sem comentário</span>
@endif
