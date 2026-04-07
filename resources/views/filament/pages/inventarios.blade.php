<x-filament-panels::page>
    <div class="inv-page">
        <section class="inv-hero">
            <div>
                <p class="inv-eyebrow">Panorama da rede</p>
                <h1>Panorama Geral dos Inventários</h1>
                <p>Visão macro dos estoques das escolas, com foco em valor estimado, baixas e movimentações recentes.</p>
            </div>

            <div class="inv-actions">
                <a href="{{ route('filament.admin.resources.pedidos-inventario.index') }}" class="inv-action inv-action--primary">
                    Pedidos internos
                </a>
                <button type="button" wire:click="abrirSlideOver" class="inv-action inv-action--ghost">
                    Historico geral
                </button>
            </div>
        </section>

        <section class="inv-cards">
            <article class="inv-card">
                <span>Total de inventarios</span>
                <strong>{{ $this->metricas->total_inventarios }}</strong>
                <small>unidades com inventario vinculado</small>
            </article>
            <article class="inv-card">
                <span>Valor estimado</span>
                <strong>R$ {{ number_format((float) $this->metricas->valor_total, 2, ',', '.') }}</strong>
                <small>referencia contratual agregada</small>
            </article>
            <article class="inv-card">
                <span>Movimentacoes</span>
                <strong>{{ $this->metricas->total_movimentacoes }}</strong>
                <small>historico consolidado da rede</small>
            </article>
            <article class="inv-card">
                <span>Quantidade baixada</span>
                <strong>{{ number_format((float) $this->metricas->quantidade_baixada, 3, ',', '.') }}</strong>
                <small>{{ $this->metricas->total_baixas }} baixas registradas</small>
            </article>
        </section>

        <section class="inv-grid">
            <article class="inv-panel">
                <div class="inv-panel-head">
                    <div>
                        <p class="inv-panel-kicker">Busca e filtros</p>
                        <h2>Inventários</h2>
                    </div>
                </div>

                <div class="inv-filter-grid">
                    <label class="inv-field">
                        <span>Buscar escola ou inventário</span>
                        <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Ex.: Escola Central" />
                    </label>

                    <label class="inv-field inv-field--small">
                        <span>Por pagina</span>
                        <select wire:model.live="porPagina">
                            <option value="6">6</option>
                            <option value="10">10</option>
                            <option value="15">15</option>
                        </select>
                    </label>
                </div>

                <div class="inv-table-wrap">
                    <table class="inv-table">
                        <thead>
                            <tr>
                                <th>Escola</th>
                                <th>Itens</th>
                                <th>Valor estimado</th>
                                <th>Baixas</th>
                                <th>Última movimentação</th>
                                <th class="text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->inventariosResumo as $inventario)
                                <tr>
                                    <td>
                                        <strong>{{ $inventario['escola_nome'] }}</strong>
                                        <small>{{ $inventario['inventario_nome'] }}</small>
                                    </td>
                                    <td>
                                        <strong>{{ $inventario['total_itens'] }}</strong>
                                        <small>{{ number_format((float) $inventario['quantidade_total'], 3, ',', '.') }} unidades</small>
                                    </td>
                                    <td>
                                        <strong>R$ {{ number_format((float) $inventario['valor_total'], 2, ',', '.') }}</strong>
                                        <small>{{ $inventario['itens_criticos'] }} criticos</small>
                                    </td>
                                    <td>
                                        <strong>{{ number_format((float) $inventario['quantidade_baixada'], 3, ',', '.') }}</strong>
                                        <small>{{ $inventario['total_baixas'] }} registros</small>
                                    </td>
                                    <td>{{ $inventario['ultima_movimentacao'] }}</td>
                                    <td class="text-right">
                                        <a href="{{ route('filament.admin.pages.gestao-inventario', ['inventario' => $inventario['inventario_id']]) }}" class="inv-link">
                                            Abrir inventário
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="inv-empty">Nenhum inventário encontrado.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php($paginacao = $this->paginacao)
                <div class="inv-pagination">
                    <span>Mostrando {{ $paginacao['de'] }}-{{ $paginacao['ate'] }} de {{ $paginacao['total'] }}</span>

                    <div>
                        <button type="button" wire:click="mudarPagina({{ max(1, $paginacao['paginaAtual'] - 1) }})" @disabled($paginacao['paginaAtual'] === 1)>
                            Anterior
                        </button>
                        <button type="button" wire:click="mudarPagina({{ min($paginacao['totalPaginas'], $paginacao['paginaAtual'] + 1) }})" @disabled($paginacao['paginaAtual'] === $paginacao['totalPaginas'])>
                            Proxima
                        </button>
                    </div>
                </div>
            </article>

            <article class="inv-panel">
                <div class="inv-panel-head">
                    <div>
                        <p class="inv-panel-kicker">Comparativo</p>
                        <h2>Valor por escola</h2>
                    </div>
                </div>

                <div class="inv-bars">
                    @forelse ($this->comparativoValor as $item)
                        <div class="inv-bar">
                            <div class="inv-bar-label">
                                <span>{{ $item['escola_nome'] }}</span>
                                <strong>R$ {{ number_format((float) $item['valor_total'], 2, ',', '.') }}</strong>
                            </div>
                            <div class="inv-bar-track">
                                <span style="width: {{ $item['pct_barra'] }}%"></span>
                            </div>
                        </div>
                    @empty
                        <p class="inv-empty-copy">Sem dados de valor para comparar.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="inv-panel inv-panel--wide">
            <div class="inv-panel-head">
                <div>
                    <p class="inv-panel-kicker">Comparativo</p>
                    <h2>Baixas por escola</h2>
                </div>
            </div>

            <div class="inv-bars inv-bars--compact">
                @forelse ($this->comparativoBaixas as $item)
                    <div class="inv-bar">
                        <div class="inv-bar-label">
                            <span>{{ $item['escola_nome'] }}</span>
                            <strong>{{ number_format((float) $item['quantidade_baixada'], 3, ',', '.') }}</strong>
                        </div>
                        <div class="inv-bar-track inv-bar-track--rose">
                            <span style="width: {{ $item['pct_barra'] }}%"></span>
                        </div>
                    </div>
                @empty
                    <p class="inv-empty-copy">Sem baixas registradas.</p>
                @endforelse
            </div>
        </section>
    </div>

    @if ($slideOverAberto)
        <div class="inv-overlay" wire:click="fecharSlideOver"></div>
        <aside class="inv-slideover">
            <header>
                <div>
                    <p class="inv-panel-kicker">Historico consolidado</p>
                    <h3>Últimas movimentações</h3>
                </div>
                <button type="button" wire:click="fecharSlideOver">Fechar</button>
            </header>

            <div class="inv-slideover-body">
                @forelse ($movimentacoes as $mov)
                    <article class="inv-mov">
                        <div>
                            <strong>{{ $mov['item_nome'] }}</strong>
                            <small>{{ $mov['escola_nome'] ?? 'Rede' }} • {{ $mov['categoria'] }}</small>
                        </div>
                        <div class="inv-mov-meta">
                            <span class="badge badge-{{ $mov['tipo'] }}">{{ $mov['tipo_label'] }}</span>
                            <strong>{{ number_format((float) $mov['quantidade'], 3, ',', '.') }}</strong>
                            <small>{{ $mov['data'] }}</small>
                        </div>
                    </article>
                @empty
                    <p class="inv-empty-copy">Nenhuma movimentacao encontrada.</p>
                @endforelse
            </div>
        </aside>
    @endif

    <style>
        .inv-page { display: grid; gap: 1.5rem; }
        .inv-hero, .inv-panel, .inv-card { border: 1px solid #d7e0ec; background: linear-gradient(180deg, #fff 0%, #f7fafc 100%); box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06); }
        .inv-hero { display: flex; justify-content: space-between; gap: 1.5rem; align-items: end; border-radius: 1.5rem; padding: 1.75rem; }
        .inv-hero h1, .inv-panel h2, .inv-slideover h3 { margin: 0; color: #15314b; font-weight: 700; }
        .inv-hero p, .inv-panel-head p, .inv-card small, .inv-field span, .inv-table small { margin: 0; color: #597086; }
        .inv-eyebrow, .inv-panel-kicker { text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.72rem; font-weight: 700; color: #0f766e; }
        .inv-actions { display: flex; gap: 0.75rem; flex-wrap: wrap; }
        .inv-action, .inv-link, .inv-pagination button, .inv-slideover header button { border-radius: 999px; padding: 0.72rem 1rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; }
        .inv-action--primary { background: #0f766e; color: #fff; }
        .inv-action--ghost, .inv-link, .inv-pagination button, .inv-slideover header button { background: #e6eff8; color: #15314b; }
        .inv-cards { display: grid; gap: 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .inv-card { border-radius: 1.25rem; padding: 1.25rem; display: grid; gap: 0.35rem; }
        .inv-card span { font-size: 0.85rem; color: #597086; }
        .inv-card strong { font-size: 1.6rem; color: #15314b; }
        .inv-grid { display: grid; gap: 1rem; grid-template-columns: 1.5fr 1fr; }
        .inv-panel { border-radius: 1.35rem; padding: 1.3rem; display: grid; gap: 1rem; }
        .inv-panel-head { display: flex; justify-content: space-between; align-items: center; }
        .inv-filter-grid { display: grid; grid-template-columns: 1fr 180px; gap: 0.85rem; }
        .inv-field { display: grid; gap: 0.45rem; font-weight: 600; color: #15314b; }
        .inv-field input, .inv-field select { border-radius: 0.95rem; border: 1px solid #c8d5e4; background: #fff; padding: 0.85rem 0.95rem; font-size: 0.95rem; color: #15314b; }
        .inv-table-wrap { overflow: auto; border-radius: 1rem; border: 1px solid #d7e0ec; }
        .inv-table { width: 100%; border-collapse: collapse; }
        .inv-table th, .inv-table td { padding: 0.95rem 1rem; text-align: left; border-bottom: 1px solid #edf3f8; vertical-align: top; }
        .inv-table th { background: #f4f8fb; color: #34506a; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .inv-table td strong { display: block; color: #15314b; }
        .text-right { text-align: right; }
        .inv-pagination { display: flex; justify-content: space-between; gap: 1rem; align-items: center; color: #597086; font-size: 0.9rem; }
        .inv-pagination div { display: flex; gap: 0.5rem; }
        .inv-bars { display: grid; gap: 0.95rem; }
        .inv-bar { display: grid; gap: 0.45rem; }
        .inv-bar-label { display: flex; justify-content: space-between; gap: 0.75rem; align-items: center; color: #15314b; font-size: 0.95rem; }
        .inv-bar-track { height: 0.85rem; background: #e6eff8; border-radius: 999px; overflow: hidden; }
        .inv-bar-track span { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #0f766e 0%, #22c55e 100%); }
        .inv-bar-track--rose span { background: linear-gradient(90deg, #fb7185 0%, #f97316 100%); }
        .inv-empty, .inv-empty-copy { color: #597086; text-align: center; padding: 1rem 0; }
        .inv-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.35); z-index: 40; }
        .inv-slideover { position: fixed; top: 0; right: 0; width: min(560px, 100%); height: 100vh; background: #f8fbfd; z-index: 50; box-shadow: -24px 0 48px rgba(15, 23, 42, 0.18); display: grid; grid-template-rows: auto 1fr; }
        .inv-slideover header { display: flex; justify-content: space-between; gap: 1rem; align-items: start; padding: 1.5rem; border-bottom: 1px solid #d7e0ec; }
        .inv-slideover-body { overflow: auto; padding: 1.25rem 1.5rem 2rem; display: grid; gap: 0.85rem; }
        .inv-mov { padding: 1rem; border-radius: 1rem; border: 1px solid #d7e0ec; background: #fff; display: flex; justify-content: space-between; gap: 1rem; }
        .inv-mov small { display: block; color: #597086; }
        .inv-mov-meta { display: grid; justify-items: end; gap: 0.35rem; color: #15314b; }
        .badge { border-radius: 999px; padding: 0.25rem 0.65rem; font-size: 0.78rem; font-weight: 700; }
        .badge-entrada { background: #dcfce7; color: #166534; }
        .badge-saida { background: #fee2e2; color: #991b1b; }
        .badge-transferencia { background: #dbeafe; color: #1d4ed8; }
        @media (max-width: 1100px) { .inv-cards, .inv-grid { grid-template-columns: 1fr 1fr; } }
        @media (max-width: 840px) {
            .inv-hero, .inv-panel-head, .inv-cards, .inv-grid { grid-template-columns: 1fr; flex-direction: column; align-items: stretch; }
            .inv-filter-grid { grid-template-columns: 1fr; }
            .text-right { text-align: left; }
        }
    </style>
</x-filament-panels::page>
