@php
    $totalEstudantes = (int) $evento->escolasAgendadas
        ->where('precisa_transporte', true)
        ->sum('quantidade_estimada_transporte');
    $escolasAbrangentes = $evento->enviar_todas_escolas
        ? ($evento->publicoAlvo?->escolas ?? collect())
        : collect();
@endphp

<div class="gi-event-detail gi-event-detail--management">
    <section class="gi-event-detail__intro">
        <div>
            <span class="gi-event-detail__status gi-event-detail__status--{{ $evento->status?->value ?? 'inativo' }}">
                {{ $evento->status?->label() ?? 'Não informado' }}
            </span>
            @if ($evento->possuiTransporte())
                <span class="gi-event-detail__transport">
                    <x-heroicon-o-truck aria-hidden="true" />
                    Transporte solicitado
                </span>
            @endif
        </div>

        @if ($evento->descricao)
            <p>{{ $evento->descricao }}</p>
        @else
            <p class="gi-event-detail__muted">Este evento não possui descrição.</p>
        @endif
    </section>

    <dl class="gi-event-detail__grid">
        <div>
            <dt>Criado por</dt>
            <dd>{{ $evento->criadoPor?->name ?? 'Usuário não informado' }}</dd>
        </div>
        <div>
            <dt>Criado em</dt>
            <dd>{{ $evento->created_at?->format('d/m/Y H:i') ?? 'Não informado' }}</dd>
        </div>
        <div>
            <dt>Data do evento</dt>
            <dd>{{ $evento->data_inicio->format('d/m/Y') }}</dd>
        </div>
        <div>
            <dt>Horário</dt>
            <dd>{{ $evento->data_inicio->format('H:i') }}–{{ $evento->data_fim->format('H:i') }}</dd>
        </div>
        <div>
            <dt>Categoria</dt>
            <dd>{{ $evento->categoria?->label() ?? 'Não informada' }}</dd>
        </div>
        <div>
            <dt>Local</dt>
            <dd>{{ $evento->local ?: 'Não informado' }}</dd>
        </div>
        <div>
            <dt>Total estimado</dt>
            <dd>{{ $evento->possuiTransporte() ? number_format($totalEstudantes, 0, ',', '.').' estudante(s)' : 'Transporte não solicitado' }}</dd>
        </div>
    </dl>

    <section class="gi-event-detail__section">
        <header>
            <div>
                <p class="gi-eyebrow">Distribuição</p>
                <h3>Escolas participantes</h3>
            </div>
            <span class="gi-event-detail__count">
                @if ($evento->enviar_todas_escolas)
                    {{ $escolasAbrangentes->isNotEmpty() ? $escolasAbrangentes->count().' escola(s)' : 'Todas do escopo' }}
                @else
                    {{ $evento->escolasAgendadas->count() }} escola(s)
                @endif
            </span>
        </header>

        @if ($evento->enviar_todas_escolas)
            <div class="gi-event-detail__schools gi-event-detail__schools--management">
                @forelse ($escolasAbrangentes as $escola)
                    <article class="gi-event-detail__school">
                        <div class="gi-event-detail__school-heading">
                            <strong>{{ $escola->nome }}</strong>
                        </div>
                    </article>
                @empty
                    <div class="gi-event-detail__empty">
                        O evento foi enviado para todas as escolas autorizadas no escopo do criador.
                    </div>
                @endforelse
            </div>
        @else
            <div class="gi-event-detail__schools gi-event-detail__schools--management">
                @forelse ($evento->escolasAgendadas as $agendamento)
                    <article class="gi-event-detail__school">
                        <div class="gi-event-detail__school-heading">
                            <strong>{{ $agendamento->escola?->nome ?? 'Escola não informada' }}</strong>
                            @if ($agendamento->precisa_transporte)
                                <span>{{ number_format((int) $agendamento->quantidade_estimada_transporte, 0, ',', '.') }} aluno(s)</span>
                            @endif
                        </div>

                        @if ($agendamento->series->isNotEmpty())
                            <p><b>Séries:</b> {{ $agendamento->series->pluck('nome')->join(', ') }}</p>
                        @endif

                        @if ($agendamento->turmas->isNotEmpty())
                            <p>
                                <b>Turmas:</b>
                                {{ $agendamento->turmas->map(fn ($turma) => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))->join(', ') }}
                            </p>
                        @endif

                        <p>
                            <b>Transporte:</b>
                            {{ $agendamento->precisa_transporte ? 'Solicitado' : 'Não solicitado' }}
                        </p>
                    </article>
                @empty
                    <div class="gi-event-detail__empty">Nenhuma escola vinculada a este evento.</div>
                @endforelse
            </div>
        @endif
    </section>

    @if ($evento->possuiTransporte())
        <section class="gi-event-detail__section gi-event-detail__section--allocations">
            <header>
                <div>
                    <p class="gi-eyebrow">Planejamento</p>
                    <h3>Alocação de transporte</h3>
                </div>
            </header>

            <livewire:transporte.evento-transporte-alocacoes-table
                :evento-id="$evento->getKey()"
                :key="'evento-transporte-alocacoes-'.$evento->getKey()"
            />
        </section>
    @endif

    <section class="gi-event-detail__section">
        <header>
            <div>
                <p class="gi-eyebrow">Auditoria</p>
                <h3>Histórico do evento</h3>
            </div>
        </header>

        <ol class="gi-event-history">
            @forelse ($evento->historicos as $historico)
                <li>
                    <span class="gi-event-history__marker" aria-hidden="true"></span>
                    <div>
                        <strong>{{ $historico->acao?->label() ?? 'Atualização' }}</strong>
                        <small>
                            {{ $historico->usuario?->name ?? 'Sistema' }} ·
                            {{ $historico->created_at?->format('d/m/Y H:i') }}
                        </small>
                        @if ($historico->motivo)
                            <p>{{ $historico->motivo }}</p>
                        @endif
                    </div>
                </li>
            @empty
                <li class="gi-event-detail__empty">Ainda não há movimentações registradas.</li>
            @endforelse
        </ol>
    </section>
</div>
