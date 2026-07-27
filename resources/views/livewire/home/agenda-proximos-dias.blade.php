<section class="home-agenda" aria-labelledby="home-agenda-title" wire:loading.class="home-agenda--loading">
    <header class="home-agenda__header">
        <div>
            <p class="home-agenda__eyebrow">PRÓXIMOS DIAS</p>
            <h2 id="home-agenda-title">Agenda e acompanhamentos</h2>
            <p>Eventos, prazos e atividades visíveis no seu contexto de acesso.</p>
        </div>

        <div class="home-agenda__header-actions">
            @if ($podeVisualizarRede ?? false)
                <div class="home-agenda__scope" role="tablist" aria-label="Escopo da agenda">
                    <button
                        type="button"
                        role="tab"
                        @class(['is-active' => $escopoAgenda === 'pessoal'])
                        aria-selected="{{ $escopoAgenda === 'pessoal' ? 'true' : 'false' }}"
                        wire:click="definirEscopo('pessoal')"
                    >
                        Para mim
                    </button>
                    <button
                        type="button"
                        role="tab"
                        @class(['is-active' => $escopoAgenda === 'rede'])
                        aria-selected="{{ $escopoAgenda === 'rede' ? 'true' : 'false' }}"
                        wire:click="definirEscopo('rede')"
                    >
                        Para a rede
                    </button>
                </div>
            @endif
            @if ($podeCriarEvento ?? false)
                {{ $this->novoEventoAction }}
            @endif
            <label class="home-agenda__days">
                <span>Período</span>
                <select wire:model.live="quantidadeDias" aria-label="Quantidade de dias">
                    @foreach ($periodOptions as $option)
                        <option value="{{ $option }}">{{ $option }} dias</option>
                    @endforeach
                </select>
            </label>
        </div>
    </header>

    <div wire:loading.flex class="home-agenda__skeleton" role="status" aria-live="polite">
        <span></span><span></span><span></span><span></span><span></span>
        <strong>Atualizando agenda...</strong>
    </div>

    <div wire:loading.remove>
        @if ($erro)
            <div class="home-agenda__message home-agenda__message--error" role="alert">
                <span>{{ $erro }}</span>
                <button type="button" wire:click="recarregar">Tentar novamente</button>
            </div>
        @else
            @if ($sourceErrors !== [])
                <div class="home-agenda__message" role="status">
                    <span>Algumas origens não puderam ser carregadas. Os demais eventos continuam disponíveis.</span>
                    <button type="button" wire:click="recarregar">Tentar novamente</button>
                </div>
            @endif
            @if ($truncated)
                <div class="home-agenda__message" role="status">A agenda atingiu o limite de itens. Selecione um período menor.</div>
            @endif

            <div class="home-agenda__grid">
                @forelse ($days as $day)
                    <article @class([
                        'home-agenda__day',
                        'is-today' => $day['date']->isToday(),
                        'is-weekend' => $day['date']->isWeekend(),
                    ])>
                        <header>
                            <span>{{ mb_strtoupper($day['date']->locale('pt_BR')->translatedFormat('D')) }}</span>
                            <strong>{{ $day['date']->format('d') }}</strong>
                            <small>{{ $day['date']->locale('pt_BR')->translatedFormat('M') }}</small>
                        </header>

                        <div class="home-agenda__events">
                            @forelse ($day['events'] as $event)
                                @php($eventDomId = 'agenda-evento-'.md5($day['date']->toDateString().'-'.$event->id))
                                <article
                                    class="home-agenda__event color-{{ $event->cor }}"
                                    wire:key="agenda-{{ $day['date']->toDateString() }}-{{ $event->id }}"
                                    x-data="{ aberto: false }"
                                    x-bind:class="{ 'is-open': aberto }"
                                >
                                    <button
                                        type="button"
                                        class="home-agenda__event-toggle"
                                        title="{{ $event->resumo ?: $event->titulo }}"
                                        x-on:click="aberto = ! aberto"
                                        x-bind:aria-expanded="aberto"
                                        aria-controls="{{ $eventDomId }}"
                                    >
                                        <span class="home-agenda__event-meta">
                                            {{ $event->diaInteiro ? 'Dia inteiro' : $event->inicio->format('H:i') }}
                                            · {{ $event->categoriaLabel }}
                                        </span>
                                        @if ($event->inicio->startOfDay()->lt($day['date']->startOfDay()) || $event->fim->startOfDay()->gt($day['date']->startOfDay()))
                                            <span class="home-agenda__continuation">
                                                @if ($event->inicio->startOfDay()->lt($day['date']->startOfDay()))← Desde {{ $event->inicio->format('d/m') }}@endif
                                                @if ($event->inicio->startOfDay()->lt($day['date']->startOfDay()) && $event->fim->startOfDay()->gt($day['date']->startOfDay())) · @endif
                                                @if ($event->fim->startOfDay()->gt($day['date']->startOfDay()))Continua →@endif
                                            </span>
                                        @endif
                                        <strong>{{ $event->titulo }}</strong>
                                        @if ($event->local)<span>{{ $event->local }}</span>@endif
                                        @if ($event->statusLabel)<span>{{ $event->statusLabel }}</span>@endif
                                        @if ($event->progresso !== null)
                                            <progress
                                                class="home-agenda__progress"
                                                max="100"
                                                value="{{ min(100, max(0, $event->progresso)) }}"
                                                aria-label="Progresso de {{ number_format($event->progresso, 0) }}%"
                                            ></progress>
                                            <small>{{ number_format($event->progresso, 0) }}% concluído</small>
                                        @endif
                                        <span class="home-agenda__event-indicator" aria-hidden="true"></span>
                                    </button>

                                    <div
                                        id="{{ $eventDomId }}"
                                        class="home-agenda__event-detail"
                                        x-cloak
                                        x-show="aberto"
                                        x-transition:enter="home-agenda__event-detail-transition"
                                        x-transition:enter-start="home-agenda__event-detail-collapsed"
                                        x-transition:enter-end="home-agenda__event-detail-expanded"
                                        x-transition:leave="home-agenda__event-detail-transition"
                                        x-transition:leave-start="home-agenda__event-detail-expanded"
                                        x-transition:leave-end="home-agenda__event-detail-collapsed"
                                    >
                                        @if ($event->resumo)<p>{{ $event->resumo }}</p>@endif
                                        <span>
                                            <strong>Período:</strong>
                                            @if ($event->diaInteiro)
                                                {{ $event->inicio->format('d/m/Y') }}
                                                @if (! $event->inicio->isSameDay($event->fim)) a {{ $event->fim->format('d/m/Y') }}@endif
                                            @else
                                                {{ $event->inicio->format('d/m/Y H:i') }} a {{ $event->fim->format('d/m/Y H:i') }}
                                            @endif
                                        </span>
                                        @if ($event->escola)<span><strong>Escola:</strong> {{ $event->escola }}</span>@endif
                                        @if ($event->local)<span><strong>Local:</strong> {{ $event->local }}</span>@endif
                                        @if ($event->setor)<span><strong>Setor:</strong> {{ $event->setor }}</span>@endif
                                        @if ($event->transporteEstimado !== null)
                                            <span><strong>Transporte:</strong> {{ $event->transporteEstimado }} estudante(s) estimado(s)</span>
                                        @endif
                                        @foreach ($event->transporteAlocacoes as $alocacao)
                                            <span><strong>Veículo:</strong> {{ $alocacao }}</span>
                                        @endforeach
                                        @if ($event->actionUrl)
                                            <a href="{{ $event->actionUrl }}">{{ $event->actionLabel ?: 'Acessar' }}</a>
                                        @endif
                                    </div>
                                </article>
                            @empty
                                <p class="home-agenda__empty">Nenhum item</p>
                            @endforelse
                            @if ($day['remaining'] > 0)
                                <button type="button" class="home-agenda__more" wire:click="alternarDia('{{ $day['date']->toDateString() }}')">
                                    +{{ $day['remaining'] }} {{ $day['remaining'] === 1 ? 'evento' : 'eventos' }}
                                </button>
                            @elseif ($day['expanded'] && count($day['events']) > 3)
                                <button type="button" class="home-agenda__more" wire:click="alternarDia('{{ $day['date']->toDateString() }}')">Mostrar menos</button>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="home-agenda__empty-state">
                        <strong>Nenhum evento encontrado</strong>
                        <span>Selecione outro período para consultar mais itens.</span>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    <x-filament-actions::modals />
</section>
