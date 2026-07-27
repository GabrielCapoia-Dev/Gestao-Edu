<div class="full-calendar">
    <header class="full-calendar__toolbar">
        <div class="full-calendar__navigation">
            <button type="button" wire:click="navegar(-1)" aria-label="Período anterior">‹</button>
            <button type="button" wire:click="irParaHoje">Hoje</button>
            <button type="button" wire:click="navegar(1)" aria-label="Próximo período">›</button>
            <strong>{{ $tituloPeriodo }}</strong>
        </div>

        <div class="full-calendar__views" role="tablist" aria-label="Visualização do calendário">
            <button type="button" @class(['is-active' => $visualizacao === 'mes']) wire:click="definirVisualizacao('mes')">Mês</button>
            <button type="button" @class(['is-active' => $visualizacao === 'semana']) wire:click="definirVisualizacao('semana')">Semana</button>
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

    <div wire:loading.flex class="full-calendar__loading" role="status">Atualizando calendário...</div>
</div>
