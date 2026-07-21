<div class="gi-event-detail">
    @if ($evento->descricao)
        <p class="gi-event-detail__description">{{ $evento->descricao }}</p>
    @endif

    <dl class="gi-event-detail__grid">
        <div><dt>Data</dt><dd>{{ $evento->data_inicio->format('d/m/Y') }}</dd></div>
        <div><dt>Horário geral</dt><dd>{{ $evento->data_inicio->format('H:i') }}–{{ $evento->data_fim->format('H:i') }}</dd></div>
        <div><dt>Categoria</dt><dd>{{ $evento->categoria->label() }}</dd></div>
        <div><dt>Publicação</dt><dd>{{ $evento->ativo ? 'Publicado' : 'Não publicado' }}</dd></div>
        <div>
            <dt>Distribuição</dt>
            <dd>
                @if ($evento->publicoAlvo?->todos_usuarios)
                    Todos os usuários do escopo
                @elseif ($evento->enviar_todas_escolas)
                    Todas as escolas do escopo
                @else
                    Escolas específicas
                @endif
            </dd>
        </div>
    </dl>

    @if (! $evento->enviar_todas_escolas && $evento->escolasAgendadas->isNotEmpty())
        <div class="gi-event-detail__schools">
            @foreach ($evento->escolasAgendadas as $agendamento)
                <div>
                    <strong>{{ $agendamento->escola?->nome }}</strong>
                    <span>{{ substr($agendamento->hora_inicio, 0, 5) }}–{{ substr($agendamento->hora_fim, 0, 5) }}</span>
                    @if ($agendamento->precisa_transporte)
                        <span>Transporte: {{ $agendamento->quantidade_estimada_transporte ?? 0 }} estudante(s) estimado(s)</span>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
