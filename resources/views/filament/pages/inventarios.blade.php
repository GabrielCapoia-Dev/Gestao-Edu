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
                            <option value="5">5</option>
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
                        <button type="button" wire:click="mudarPagina({{ max(1, $paginacao['paginaAtual'] - 1) }})" @disabled($paginacao['paginaAtual']===1)>
                            Anterior
                        </button>
                        <button type="button" wire:click="mudarPagina({{ min($paginacao['totalPaginas'], $paginacao['paginaAtual'] + 1) }})" @disabled($paginacao['paginaAtual']===$paginacao['totalPaginas'])>
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

                <div class="inv-bars inv-bars--limit-10">
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
                    <h2>Impacto das baixas por escola</h2>
                    <p>Filtro atual: {{ $this->tipoBaixaSelecionadaLabel }}</p>
                </div>

                <div class="inv-panel-tools">
                    <label class="inv-field inv-field--small">
                        <span>Tipo de baixa</span>
                        <select wire:model.live="tipoBaixa">
                            @foreach ($this->tiposBaixaOptions as $valor => $label)
                            <option value="{{ $valor }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>
            </div>

            <div class="inv-bars inv-bars--compact inv-bars--limit-10">
                @forelse ($this->comparativoBaixas as $item)
                <div class="inv-bar">
                    <div class="inv-bar-label">
                        <span>{{ $item['escola_nome'] }}</span>
                        <strong>R$ {{ number_format((float) $item['valor_baixado'], 2, ',', '.') }}</strong>
                    </div>
                    <div class="inv-bar-track inv-bar-track--rose">
                        <span style="width: {{ $item['pct_barra'] }}%"></span>
                    </div>
                    <small>
                        {{ number_format((float) $item['quantidade_baixada_filtrada'], 3, ',', '.') }}
                        unidades • {{ $item['total_baixas_filtradas'] }} registros
                    </small>
                </div>
                @empty
                <p class="inv-empty-copy">Sem baixas registradas para o filtro selecionado.</p>
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

    @include('filament.pages.partials.inventory-page-styles')
</x-filament-panels::page>
