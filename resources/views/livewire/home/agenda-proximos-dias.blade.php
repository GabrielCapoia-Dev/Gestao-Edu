<section class="home-agenda" aria-labelledby="home-agenda-title" wire:loading.class="home-agenda--loading">
    <header class="home-agenda__header">
        <div>
            <p class="home-agenda__eyebrow">PRÓXIMOS DIAS</p>
            <h2 id="home-agenda-title">Agenda e acompanhamentos</h2>
            <p>Eventos, prazos e atividades visíveis no seu contexto de acesso.</p>
        </div>

        <div class="home-agenda__header-actions">
            @if ($manageUrl)<a class="home-agenda__manage" href="{{ $manageUrl }}">Gerenciar agenda</a>@endif
            <label class="home-agenda__days">
                <span>Período</span>
                <select wire:model.live="quantidadeDias" aria-label="Quantidade de dias">
                    @foreach ([7, 14, 21, 31] as $option)
                        @if ($option <= $maxDays)
                            <option value="{{ $option }}">{{ $option }} dias</option>
                        @endif
                    @endforeach
                </select>
            </label>
        </div>
    </header>

    <div class="home-agenda__filters" aria-label="Filtros da agenda">
        <label><span>Data inicial</span><input type="date" wire:model.live="dataInicial"></label>
        <label><span>Data final</span><input type="date" wire:model.live="dataFinal"></label>
        <label>
            <span>Categoria</span>
            <select wire:model.live="categoria">
                <option value="">Todas</option>
                @foreach ($categoryOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Status</span>
            <select wire:model.live="status">
                <option value="">Todos</option>
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Prioridade</span>
            <select wire:model.live="prioridade">
                <option value="">Todas</option>
                @foreach ($priorityOptions as $option)
                    <option value="{{ $option->value }}">{{ $option->label() }}</option>
                @endforeach
            </select>
        </label>
        @if ($showSchoolFilter)
            <label>
                <span>Buscar escola</span>
                <input type="search" wire:model.live.debounce.350ms="buscaEscola" placeholder="Nome da escola">
            </label>
            <label>
                <span>Escola</span>
                <select wire:model.live="escolaId">
                    <option value="">Todas</option>
                    @foreach ($schoolOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        @if ($showSectorFilter)
            <label>
                <span>Buscar setor</span>
                <input type="search" wire:model.live.debounce.350ms="buscaSetor" placeholder="Nome do setor">
            </label>
            <label>
                <span>Setor</span>
                <select wire:model.live="setorId">
                    <option value="">Todos</option>
                    @foreach ($sectorOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>
        @endif
        <label class="home-agenda__subject"><span>Assunto</span><input type="search" wire:model.live.debounce.400ms="assunto" placeholder="Buscar assunto"></label>
        <button type="button" class="home-agenda__clear" wire:click="limparFiltros">Limpar</button>
    </div>

    <div wire:loading.flex class="home-agenda__skeleton" role="status" aria-live="polite">
        <span></span><span></span><span></span><span></span>
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
                <div class="home-agenda__message" role="status">A agenda atingiu o limite de itens. Reduza o período ou aplique filtros.</div>
            @endif

            <div class="home-agenda__grid">
                @forelse ($days as $day)
                    <article class="home-agenda__day {{ $day['date']->isToday() ? 'is-today' : '' }}">
                        <header>
                            <span>{{ mb_strtoupper($day['date']->locale('pt_BR')->translatedFormat('D')) }}</span>
                            <strong>{{ $day['date']->format('d') }}</strong>
                            <small>{{ $day['date']->locale('pt_BR')->translatedFormat('M') }}</small>
                        </header>

                        <div class="home-agenda__events">
                            @forelse ($day['events'] as $event)
                                <button
                                    type="button"
                                    class="home-agenda__event color-{{ $event->cor }}"
                                    wire:key="agenda-{{ $day['date']->toDateString() }}-{{ $event->id }}"
                                    wire:click="abrirEvento(@js($event->source), @js($event->reference))"
                                    title="{{ $event->resumo ?: $event->titulo }}"
                                    aria-label="Ver detalhes de {{ $event->titulo }}"
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
                                    <span>{{ $event->statusLabel }}</span>
                                    @if ($event->progresso !== null)
                                        <progress
                                            class="home-agenda__progress"
                                            max="100"
                                            value="{{ min(100, max(0, $event->progresso)) }}"
                                            aria-label="Progresso de {{ number_format($event->progresso, 0) }}%"
                                        ></progress>
                                        <small>{{ number_format($event->progresso, 0) }}% concluído</small>
                                    @endif
                                </button>
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
                        <span>Ajuste o período ou os filtros para consultar outros itens.</span>
                    </div>
                @endforelse
            </div>
        @endif
    </div>

    <x-filament::modal id="agenda-event-detail" width="2xl">
        @if ($eventoAberto)
            <x-slot name="heading">{{ $eventoAberto['titulo'] }}</x-slot>
            <div class="home-agenda__modal">
                <span class="home-agenda__badge color-{{ $eventoAberto['cor'] }}">{{ $eventoAberto['categoria_label'] }}</span>
                @if ($eventoAberto['descricao'])<p>{{ $eventoAberto['descricao'] }}</p>@endif
                <dl>
                    <div><dt>Início</dt><dd>{{ \Illuminate\Support\Carbon::parse($eventoAberto['inicio'])->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Fim</dt><dd>{{ \Illuminate\Support\Carbon::parse($eventoAberto['fim'])->format('d/m/Y H:i') }}</dd></div>
                    <div><dt>Status</dt><dd>{{ $eventoAberto['status_label'] }}</dd></div>
                    <div><dt>Prioridade</dt><dd>{{ $eventoAberto['prioridade_label'] }}</dd></div>
                    @if ($eventoAberto['escola'])<div><dt>Escola</dt><dd>{{ $eventoAberto['escola'] }}</dd></div>@endif
                    @if ($eventoAberto['setor'])<div><dt>Setor</dt><dd>{{ $eventoAberto['setor'] }}</dd></div>@endif
                    <div><dt>Origem</dt><dd>{{ $eventoAberto['origem'] }}</dd></div>
                    @foreach ($eventoAberto['metadata'] as $label => $value)
                        @if (filled($value))<div><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endif
                    @endforeach
                </dl>
                @if ($eventoAberto['progresso'] !== null)
                    <div class="home-agenda__modal-progress">
                        <span>Progresso</span>
                        <strong>{{ number_format($eventoAberto['progresso'], 0) }}%</strong>
                        <progress max="100" value="{{ min(100, max(0, $eventoAberto['progresso'])) }}"></progress>
                    </div>
                @endif
            </div>
            <x-slot name="footer">
                <x-filament::button color="gray" x-on:click="$dispatch('close-modal', { id: 'agenda-event-detail' })" wire:click="fecharEvento">Fechar</x-filament::button>
                @if ($eventoAberto['action_url'])
                    <x-filament::button tag="a" :href="$eventoAberto['action_url']">{{ $eventoAberto['action_label'] ?: 'Acessar' }}</x-filament::button>
                @endif
            </x-slot>
        @endif
    </x-filament::modal>

</section>
