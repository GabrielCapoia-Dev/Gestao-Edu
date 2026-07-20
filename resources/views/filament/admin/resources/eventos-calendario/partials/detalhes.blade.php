<div class="gi-event-detail">
    @if ($evento->descricao)
        <p class="gi-event-detail__description">{{ $evento->descricao }}</p>
    @endif
    <dl class="gi-event-detail__grid">
        <div><dt>Período</dt><dd>{{ $evento->data_inicio->format('d/m/Y H:i') }} a {{ $evento->data_fim->format('d/m/Y H:i') }}</dd></div>
        <div><dt>Status</dt><dd>{{ $evento->statusEfetivo()->label() }}</dd></div>
        <div><dt>Categoria</dt><dd>{{ $evento->categoria->label() }}</dd></div>
        <div><dt>Prioridade</dt><dd>{{ $evento->prioridade->label() }}</dd></div>
        @if ($evento->escola)<div><dt>Escola</dt><dd>{{ $evento->escola->nome }}</dd></div>@endif
        @if ($evento->setor)<div><dt>Setor</dt><dd>{{ $evento->setor->nome_completo }}</dd></div>@endif
        @if ($evento->progresso !== null)<div><dt>Progresso</dt><dd>{{ number_format($evento->progresso, 0) }}%</dd></div>@endif
        <div><dt>Publicação</dt><dd>{{ $evento->ativo ? 'Publicado' : 'Não publicado' }}</dd></div>
    </dl>
</div>
