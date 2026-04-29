@php
    $stats = $this->stats();
    $modoEnviadas = $modo === 'enviadas';
    $items = $modoEnviadas ? $this->envios() : $this->notificacoes();
    $temMais = $modoEnviadas ? $this->temMaisEnvios() : $this->temMaisNotificacoes();
@endphp

<x-filament-panels::page>
    <div class="notification-center" wire:poll.15s="refreshCentral">
        <div class="nc-header">
            <div class="nc-header__main">
                <div class="nc-header__icon">
                    <x-heroicon-o-bell-alert />
                </div>
                <div>
                    <p class="nc-kicker">Mensagens do sistema</p>
                    <h2>Central de notificações</h2>
                    <p class="nc-subtitle">Acompanhe avisos ativos, histórico de leitura e disparos enviados.</p>
                </div>
            </div>

            @if($stats['ativas'] > 0)
                <button type="button" class="nc-action nc-action--primary" wire:click="marcarTodasComoLidas" wire:loading.attr="disabled">
                    <x-heroicon-o-check-badge />
                    <span>Marcar ativas como lidas</span>
                </button>
            @endif
        </div>

        <div class="nc-stats">
            <button type="button" class="nc-stat {{ $modo === 'ativas' ? 'is-active' : '' }}" wire:click="setModo('ativas')">
                <span class="nc-stat__label">Ativas</span>
                <strong>{{ $stats['ativas'] }}</strong>
                <x-heroicon-o-inbox-stack />
            </button>

            <button type="button" class="nc-stat {{ $modo === 'historico' ? 'is-active' : '' }}" wire:click="setModo('historico')">
                <span class="nc-stat__label">Histórico</span>
                <strong>{{ $stats['historico'] }}</strong>
                <x-heroicon-o-archive-box />
            </button>

            <button type="button" class="nc-stat {{ $modo === 'todas' ? 'is-active' : '' }}" wire:click="setModo('todas')">
                <span class="nc-stat__label">Hoje</span>
                <strong>{{ $stats['hoje'] }}</strong>
                <x-heroicon-o-clock />
            </button>

            <div class="nc-stat nc-stat--urgent">
                <span class="nc-stat__label">Urgentes</span>
                <strong>{{ $stats['urgentes'] }}</strong>
                <x-heroicon-o-exclamation-triangle />
            </div>

            @if($this->podeCriarNotificacoes())
                <button type="button" class="nc-stat {{ $modo === 'enviadas' ? 'is-active' : '' }}" wire:click="setModo('enviadas')">
                    <span class="nc-stat__label">Enviadas</span>
                    <strong>{{ $stats['enviadas'] }}</strong>
                    <x-heroicon-o-megaphone />
                </button>
            @endif
        </div>

        <div class="nc-filters">
            <label class="nc-field nc-field--search">
                <span>Pesquisar</span>
                <div class="nc-input-wrap">
                    <x-heroicon-o-magnifying-glass />
                    <input type="search" wire:model.live.debounce.350ms="busca" placeholder="Título, mensagem ou público">
                </div>
            </label>

            <label class="nc-field">
                <span>Prioridade</span>
                <select wire:model.live="prioridade">
                    <option value="todas">Todas</option>
                    @foreach($this->prioridadeOptions() as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
            </label>

            <label class="nc-field">
                <span>Período</span>
                <select wire:model.live="periodo">
                    <option value="7">Últimos 7 dias</option>
                    <option value="30">Últimos 30 dias</option>
                    <option value="90">Últimos 90 dias</option>
                    <option value="todos">Todo o histórico</option>
                </select>
            </label>

            <button type="button" class="nc-action nc-action--ghost" wire:click="limparFiltros">
                <x-heroicon-o-x-mark />
                <span>Limpar</span>
            </button>
        </div>

        <div class="nc-list" wire:loading.class="is-loading">
            @forelse($items as $item)
                @if($modoEnviadas)
                    <article class="nc-item" wire:key="envio-{{ $item['id'] }}">
                        <div class="nc-item__rail">
                            <span class="{{ $item['prioridade_meta']['class'] }}">
                                <x-dynamic-component :component="$item['prioridade_meta']['icon']" />
                            </span>
                        </div>

                        <div class="nc-item__content">
                            <div class="nc-item__topline">
                                <span class="nc-pill">{{ $item['prioridade_label'] }}</span>
                                <span>{{ $item['destinatarios_count'] }} destinatário(s)</span>
                                <span>{{ $item['criada_em_humano'] }}</span>
                            </div>

                            <h3>{{ $item['titulo'] }}</h3>
                            <p>{{ $item['mensagem'] }}</p>

                            <div class="nc-item__meta">
                                <span><x-heroicon-o-users /> {{ $item['destino_label'] }}</span>
                                <span><x-heroicon-o-user-circle /> {{ $item['autor'] }}</span>
                                <span><x-heroicon-o-calendar-days /> {{ $item['criada_em'] }}</span>
                            </div>

                            @if(filled($item['url']))
                                <div class="nc-item__actions">
                                    <a class="nc-action nc-action--link" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer">
                                        <x-heroicon-o-arrow-top-right-on-square />
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                </div>
                            @endif
                        </div>
                    </article>
                @else
                    <article class="nc-item {{ $item['lida'] ? 'is-read' : 'is-unread' }}" wire:key="notificacao-{{ $item['id'] }}">
                        <div class="nc-item__rail">
                            <span class="{{ $item['prioridade_meta']['class'] }}">
                                <x-dynamic-component :component="$item['prioridade_meta']['icon']" />
                            </span>
                        </div>

                        <div class="nc-item__content">
                            <div class="nc-item__topline">
                                <span class="nc-pill">{{ $item['prioridade_label'] }}</span>
                                <span>{{ $item['criada_em_humano'] }}</span>
                                <span>{{ $item['lida'] ? 'Lida' : 'Ativa' }}</span>
                            </div>

                            <h3>{{ $item['titulo'] }}</h3>
                            <p>{{ $item['mensagem'] }}</p>

                            <div class="nc-item__meta">
                                @if(filled($item['escopo']))
                                    <span><x-heroicon-o-users /> {{ $item['escopo'] }}</span>
                                @endif

                                @if(filled($item['enviado_por_nome']))
                                    <span><x-heroicon-o-user-circle /> {{ $item['enviado_por_nome'] }}</span>
                                @endif

                                <span><x-heroicon-o-calendar-days /> {{ $item['criada_em'] }}</span>

                                @if($item['lida'] && filled($item['lida_em']))
                                    <span><x-heroicon-o-envelope-open /> Lida em {{ $item['lida_em'] }}</span>
                                @endif
                            </div>

                            <div class="nc-item__actions">
                                @if(filled($item['url']))
                                    <a class="nc-action nc-action--link" href="{{ $item['url'] }}" target="_blank" rel="noopener noreferrer" x-on:click="$wire.marcarComoLida('{{ $item['id'] }}')">
                                        <x-heroicon-o-arrow-top-right-on-square />
                                        <span>{{ $item['label'] }}</span>
                                    </a>
                                @endif

                                @if($item['lida'])
                                    <button type="button" class="nc-action nc-action--ghost" wire:click="marcarComoNaoLida('{{ $item['id'] }}')">
                                        <x-heroicon-o-envelope />
                                        <span>Marcar como ativa</span>
                                    </button>
                                @else
                                    <button type="button" class="nc-action nc-action--ghost" wire:click="marcarComoLida('{{ $item['id'] }}')">
                                        <x-heroicon-o-check />
                                        <span>Marcar como lida</span>
                                    </button>
                                @endif
                            </div>
                        </div>
                    </article>
                @endif
            @empty
                <div class="nc-empty">
                    <x-heroicon-o-bell-slash />
                    <h3>Nenhum registro encontrado</h3>
                    <p>Ajuste os filtros ou altere a aba selecionada.</p>
                </div>
            @endforelse
        </div>

        @if($temMais)
            <div class="nc-more">
                <button type="button" class="nc-action nc-action--ghost" wire:click="carregarMais">
                    <x-heroicon-o-arrow-down-circle />
                    <span>Carregar mais</span>
                </button>
            </div>
        @endif
    </div>

    <style>
        .notification-center,
        .notification-center * {
            box-sizing: border-box;
        }

        .notification-center {
            --nc-ink: #111827;
            --nc-muted: #64748b;
            --nc-line: #d8dee9;
            --nc-surface: #ffffff;
            --nc-soft: #f7f9fc;
            --nc-primary: #17368d;
            --nc-green: #0f766e;
            --nc-amber: #b45309;
            --nc-red: #b91c1c;
            display: flex;
            flex-direction: column;
            gap: 18px;
            color: var(--nc-ink);
        }

        .nc-header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
            padding: 22px;
            border: 1px solid var(--nc-line);
            border-radius: 8px;
            background: var(--nc-surface);
        }

        .nc-header__main {
            display: flex;
            align-items: center;
            gap: 14px;
            min-width: 0;
        }

        .nc-header__icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 48px;
            height: 48px;
            flex: 0 0 48px;
            border-radius: 8px;
            background: #eef6fc;
            color: var(--nc-primary);
        }

        .nc-header__icon svg,
        .nc-action svg,
        .nc-stat svg,
        .nc-input-wrap svg,
        .nc-item__meta svg,
        .nc-priority svg,
        .nc-empty svg {
            width: 18px;
            height: 18px;
            flex: 0 0 auto;
        }

        .nc-header h2 {
            margin: 2px 0;
            font-size: clamp(1.35rem, 2vw, 1.9rem);
            font-weight: 760;
        }

        .nc-kicker,
        .nc-subtitle {
            margin: 0;
            color: var(--nc-muted);
        }

        .nc-kicker {
            font-size: .78rem;
            font-weight: 700;
            text-transform: uppercase;
        }

        .nc-subtitle {
            font-size: .92rem;
        }

        .nc-stats {
            display: grid;
            grid-template-columns: repeat(5, minmax(0, 1fr));
            gap: 12px;
        }

        .nc-stat {
            position: relative;
            min-height: 92px;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            justify-content: space-between;
            gap: 8px;
            padding: 14px;
            border: 1px solid var(--nc-line);
            border-radius: 8px;
            background: var(--nc-surface);
            color: var(--nc-ink);
            text-align: left;
            transition: border-color .18s ease, background .18s ease, transform .18s ease;
        }

        button.nc-stat {
            cursor: pointer;
        }

        button.nc-stat:hover,
        .nc-stat.is-active {
            border-color: var(--nc-primary);
            background: #f1f6ff;
            transform: translateY(-1px);
        }

        .nc-stat--urgent {
            border-color: #f3c8c8;
            background: #fff7f7;
        }

        .nc-stat strong {
            font-size: 1.65rem;
            line-height: 1;
        }

        .nc-stat__label {
            color: var(--nc-muted);
            font-size: .84rem;
            font-weight: 700;
        }

        .nc-stat svg {
            position: absolute;
            right: 14px;
            bottom: 14px;
            color: var(--nc-muted);
        }

        .nc-filters {
            display: grid;
            grid-template-columns: minmax(220px, 1fr) minmax(160px, 220px) minmax(160px, 220px) auto;
            align-items: end;
            gap: 12px;
            padding: 16px;
            border: 1px solid var(--nc-line);
            border-radius: 8px;
            background: var(--nc-soft);
        }

        .nc-field {
            display: flex;
            flex-direction: column;
            gap: 6px;
            min-width: 0;
            color: var(--nc-muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .nc-input-wrap,
        .nc-field select {
            min-height: 40px;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            background: #fff;
        }

        .nc-input-wrap {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 0 11px;
            color: var(--nc-muted);
        }

        .nc-input-wrap input,
        .nc-field select {
            width: 100%;
            border: 0;
            outline: 0;
            color: var(--nc-ink);
            background: transparent;
            font-size: .92rem;
        }

        .nc-field select {
            padding: 0 11px;
        }

        .nc-list {
            display: flex;
            flex-direction: column;
            gap: 12px;
            min-height: 120px;
        }

        .nc-list.is-loading {
            opacity: .68;
        }

        .nc-item {
            display: grid;
            grid-template-columns: 48px minmax(0, 1fr);
            gap: 14px;
            padding: 16px;
            border: 1px solid var(--nc-line);
            border-radius: 8px;
            background: var(--nc-surface);
        }

        .nc-item.is-unread {
            border-left: 4px solid var(--nc-primary);
        }

        .nc-item.is-read {
            background: #fbfcfe;
        }

        .nc-item__rail {
            display: flex;
            justify-content: center;
        }

        .nc-priority {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 38px;
            height: 38px;
            border-radius: 8px;
        }

        .nc-priority--normal {
            color: var(--nc-green);
            background: #ecfdf5;
        }

        .nc-priority--info {
            color: var(--nc-primary);
            background: #eef6fc;
        }

        .nc-priority--high {
            color: var(--nc-amber);
            background: #fffbeb;
        }

        .nc-priority--urgent {
            color: var(--nc-red);
            background: #fef2f2;
        }

        .nc-item__content {
            min-width: 0;
        }

        .nc-item__topline,
        .nc-item__meta,
        .nc-item__actions {
            display: flex;
            align-items: center;
            flex-wrap: wrap;
            gap: 8px;
        }

        .nc-item__topline {
            margin-bottom: 8px;
            color: var(--nc-muted);
            font-size: .78rem;
            font-weight: 700;
        }

        .nc-pill {
            display: inline-flex;
            align-items: center;
            min-height: 24px;
            padding: 3px 9px;
            border-radius: 999px;
            background: #eef2f7;
            color: #334155;
        }

        .nc-item h3 {
            margin: 0 0 7px;
            font-size: 1rem;
            font-weight: 760;
            overflow-wrap: anywhere;
        }

        .nc-item p {
            margin: 0;
            color: #334155;
            font-size: .92rem;
            line-height: 1.55;
            overflow-wrap: anywhere;
        }

        .nc-item__meta {
            margin-top: 12px;
            color: var(--nc-muted);
            font-size: .82rem;
        }

        .nc-item__meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            min-width: 0;
        }

        .nc-item__actions {
            margin-top: 14px;
        }

        .nc-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            min-height: 38px;
            padding: 8px 12px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: .86rem;
            font-weight: 740;
            text-decoration: none;
            cursor: pointer;
            transition: background .18s ease, border-color .18s ease, color .18s ease;
        }

        .nc-action--primary {
            background: var(--nc-primary);
            color: #fff;
        }

        .nc-action--primary:hover {
            background: #0f2261;
        }

        .nc-action--ghost {
            border-color: #cbd5e1;
            background: #fff;
            color: #334155;
        }

        .nc-action--ghost:hover {
            border-color: var(--nc-primary);
            color: var(--nc-primary);
        }

        .nc-action--link {
            background: #eef6fc;
            color: var(--nc-primary);
        }

        .nc-action--link:hover {
            background: #d8ecf7;
        }

        .nc-empty {
            display: flex;
            min-height: 220px;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 24px;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
            background: #fff;
            text-align: center;
            color: var(--nc-muted);
        }

        .nc-empty svg {
            width: 42px;
            height: 42px;
            color: #94a3b8;
        }

        .nc-empty h3 {
            margin: 0;
            color: var(--nc-ink);
            font-size: 1rem;
        }

        .nc-empty p {
            margin: 0;
        }

        .nc-more {
            display: flex;
            justify-content: center;
        }

        @media (max-width: 1100px) {
            .nc-stats {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .nc-filters {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 720px) {
            .nc-header {
                align-items: stretch;
                flex-direction: column;
            }

            .nc-header__main {
                align-items: flex-start;
            }

            .nc-stats,
            .nc-filters {
                grid-template-columns: 1fr;
            }

            .nc-item {
                grid-template-columns: 1fr;
            }

            .nc-item__rail {
                justify-content: flex-start;
            }

            .nc-action {
                width: 100%;
            }
        }
    </style>
</x-filament-panels::page>
