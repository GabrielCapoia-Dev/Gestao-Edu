@php
    $totalEstudantes = (int) $evento->escolasAgendadas
        ->where('precisa_transporte', true)
        ->sum('quantidade_estimada_transporte');
@endphp

<div class="gi-event-detail gi-event-detail--management">
    <header class="gi-event-detail__hero">
        <div class="gi-event-detail__hero-icon" aria-hidden="true">{{ mb_substr($evento->titulo, 0, 1) }}</div>
        <div class="gi-event-detail__hero-copy">
            <p class="gi-eyebrow">AGENDA ESCOLAR</p>
            <h2>{{ $evento->titulo }}</h2>
            <p>{{ $evento->local ?: 'Local não informado' }}</p>
        </div>
        <div class="gi-event-detail__hero-meta">
            <span>{{ $evento->data_inicio->format('d/m/Y') }}</span>
            <span>{{ $evento->data_inicio->format('H:i') }}–{{ $evento->data_fim->format('H:i') }}</span>
        </div>
    </header>
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
            <dd>{{ $evento->categoria === \App\Models\Enums\EventoCalendarioCategoria::OUTRO && filled($evento->categoria_detalhe)
                ? $evento->categoria_detalhe
                : ($evento->categoria?->label() ?? 'Não informada') }}</dd>
        </div>
        <div>
            <dt>Local</dt>
            <dd>{{ $evento->local ?: 'Não informado' }}</dd>
        </div>
        @if ($evento->possuiTransporte())
            <div>
                <dt>Total estimado</dt>
                <dd>{{ number_format($totalEstudantes, 0, ',', '.') }} estudante(s)</dd>
            </div>
        @endif
    </dl>

        <section class="gi-event-detail__section">
            <header>
                <div>
                    <p class="gi-eyebrow">Localização</p>
                    <h3>Local do evento</h3>
                </div>
            </header>
            <div
                class="gi-event-detail__map"
                x-data="eventoDetailMap({ latitude: {{ $evento->latitude !== null ? (float) $evento->latitude : 'null' }}, longitude: {{ $evento->longitude !== null ? (float) $evento->longitude : 'null' }}, query: @js($evento->local ?: 'Umuarama') })"
                x-init="init()"
                data-evento-detail-map
                data-latitude="{{ $evento->latitude }}"
                data-longitude="{{ $evento->longitude }}"
                aria-label="Mapa do local do evento"
            ></div>
        </section>

    @unless ($evento->enviar_todas_escolas)
    <section class="gi-event-detail__section">
        <header>
            <div>
                <p class="gi-eyebrow">Distribuição</p>
                <h3>Escolas participantes</h3>
            </div>
            <span class="gi-event-detail__count">
                {{ $evento->enviar_todas_escolas ? 'Evento genérico' : $evento->escolasAgendadas->count().' escola(s)' }}
            </span>
        </header>

        <div class="gi-event-detail__schools gi-event-detail__schools--management">
            @forelse ($evento->escolasAgendadas as $agendamento)
                    <article class="gi-event-detail__school">
                        <div class="gi-event-detail__school-heading">
                            <strong>{{ $agendamento->escola?->nome ?? 'Escola não informada' }}</strong>
                            @if ($agendamento->precisa_transporte)
                                <span>{{ number_format((int) $agendamento->quantidade_estimada_transporte, 0, ',', '.') }} aluno(s)</span>
                            @endif
                        </div>

                        <p>
                            <b>Horário:</b>
                            {{ substr((string) $agendamento->hora_inicio, 0, 5) }}–{{ substr((string) $agendamento->hora_fim, 0, 5) }}
                        </p>

                        @if ($agendamento->series->isNotEmpty())
                            <p><b>Séries:</b> {{ $agendamento->series->pluck('nome')->join(', ') }}</p>
                        @endif

                        @if ($agendamento->turmasDoEscopo->isNotEmpty())
                            <p>
                                <b>Turmas:</b>
                                {{ $agendamento->turmasDoEscopo->map(fn ($turma) => trim(($turma->serie?->nome ? $turma->serie->nome.' ' : '').$turma->nome))->join(', ') }}
                            </p>
                        @else
                            <p class="gi-event-detail__muted">Nenhuma turma encontrada no escopo informado.</p>
                        @endif
                    </article>
                @empty
                    <div class="gi-event-detail__empty">Nenhuma escola vinculada a este evento.</div>
            @endforelse
        </div>
    </section>
    @endunless

    @unless ($evento->enviar_todas_escolas)
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
    @endunless
</div>
