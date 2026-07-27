<div class="full-calendar">
    <header class="full-calendar__toolbar">
        <div class="full-calendar__navigation">
            <button type="button" wire:click="navegar(-1)" aria-label="Período anterior">‹</button>
            <button type="button" wire:click="irParaHoje">Hoje</button>
            <button type="button" wire:click="navegar(1)" aria-label="Próximo período">›</button>
            <strong>{{ $tituloPeriodo }}</strong>
        </div>

        <div class="full-calendar__toolbar-actions">
            <button type="button" class="full-calendar__export" wire:click="exportarCalendario">
                Exportar calendário
            </button>

            <div class="full-calendar__views" role="tablist" aria-label="Visualização do calendário">
                <button type="button" @class(['is-active' => $visualizacao === 'ano']) wire:click="definirVisualizacao('ano')">Ano</button>
                <button type="button" @class(['is-active' => $visualizacao === 'mes']) wire:click="definirVisualizacao('mes')">Mês</button>
                <button type="button" @class(['is-active' => $visualizacao === 'semana']) wire:click="definirVisualizacao('semana')">Semana</button>
            </div>
        </div>
    </header>

    @if ($erro)
        <p class="full-calendar__message" role="alert">{{ $erro }}</p>
    @else
        @if ($sourceErrors !== [])
            <p class="full-calendar__message">Algumas origens não puderam ser carregadas.</p>
        @endif
        @if ($truncated)
            <p class="full-calendar__message">O período atingiu o limite de eventos.</p>
        @endif

        @if ($visualizacao === 'ano')
            <div class="full-calendar__year-grid">
                @foreach ($months as $month)
                    <article class="full-calendar__month">
                        <button type="button" wire:click="abrirMes('{{ $month['date']->toDateString() }}')">
                            {{ ucfirst($month['date']->locale('pt_BR')->translatedFormat('F')) }}
                        </button>

                        <div class="full-calendar__month-weekdays" aria-hidden="true">
                            @foreach (['S', 'T', 'Q', 'Q', 'S', 'S', 'D'] as $weekday)
                                <span>{{ $weekday }}</span>
                            @endforeach
                        </div>

                        <div class="full-calendar__month-days">
                            @for ($offset = 0; $offset < $month['offset']; $offset++)
                                <span aria-hidden="true"></span>
                            @endfor

                            @foreach ($month['days'] as $day)
                                <button
                                    type="button"
                                    @class(['has-events' => $day['events'] !== [], 'is-today' => $day['date']->isToday()])
                                    wire:click="abrirMes('{{ $day['date']->toDateString() }}')"
                                    title="{{ $day['events'] === [] ? 'Sem eventos' : count($day['events']).' evento(s)' }}"
                                >
                                    <span>{{ $day['date']->format('d') }}</span>
                                    @if ($day['events'] !== [])
                                        <strong>{{ count($day['events']) > 9 ? '9+' : count($day['events']) }}</strong>
                                    @endif
                                </button>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        @else
            <div class="full-calendar__viewport">
                <div class="full-calendar__weekdays" aria-hidden="true">
                    @foreach (['Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb', 'Dom'] as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>

                <div class="full-calendar__grid">
                    @for ($offset = 0; $offset < $offsetInicial; $offset++)
                        <span class="full-calendar__day full-calendar__day--empty" aria-hidden="true"></span>
                    @endfor

                    @foreach ($days as $day)
                        <article @class(['full-calendar__day', 'is-today' => $day['date']->isToday()])>
                            <header>
                                <strong>{{ $day['date']->format('d') }}</strong>
                                @if ($visualizacao === 'semana')
                                    <span>{{ $day['date']->locale('pt_BR')->translatedFormat('M') }}</span>
                                @endif
                            </header>

                            <div class="full-calendar__events">
                                @forelse ($day['events'] as $event)
                                    <article
                                        class="full-calendar__event color-{{ $event->cor }}"
                                        @style(["--agenda-event-color: {$event->corDestaque}" => filled($event->corDestaque)])
                                        title="{{ $event->resumo ?: $event->titulo }}"
                                    >
                                        <span>
                                            {{ $event->diaInteiro ? 'Dia inteiro' : $event->inicio->format('H:i') }}
                                            @if ($event->source === 'reservas_veiculos' && filled($event->solicitante))
                                                · {{ $event->solicitante }}
                                            @endif
                                            · {{ $event->categoriaLabel }}
                                        </span>
                                        <strong>{{ $event->titulo }}</strong>
                                        @if ($event->escola)<small>{{ $event->escola }}</small>@endif
                                        @if ($event->statusLabel)<small>{{ $event->statusLabel }}</small>@endif
                                    </article>
                                @empty
                                    <span class="full-calendar__empty">Sem eventos</span>
                                @endforelse
                            </div>
                        </article>
                    @endforeach
                </div>
            </div>
        @endif
    @endif

    <div wire:loading.flex class="full-calendar__loading" role="status">Atualizando calendário...</div>
</div>
