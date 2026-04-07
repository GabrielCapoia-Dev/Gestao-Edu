<x-filament-panels::page>
    @php($inventarioAtual = $this->inventarioAtual)

    <div class="gi-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Inventário Escolar</p>
                <h1>{{ $inventarioAtual?->escola?->nome ?? 'Inventário não selecionado' }}</h1>
                <p>{{ $inventarioAtual ? 'Gestão operacional do inventário da escola, com exportações, histórico e baixas.' : 'Selecione um inventário disponível para visualizar os dados.' }}</p>
            </div>

            <div class="gi-actions">
                <a href="{{ route('filament.admin.resources.pedidos-inventario.index') }}" class="gi-action gi-action--primary">
                    Pedidos da escola
                </a>
                <a href="{{ route('filament.admin.resources.balancos-inventario.index') }}" class="gi-action gi-action--ghost">
                    Balanços
                </a>
                @if ($inventarioAtual && $this->podeExportar)
                    <a href="{{ route('gestao-inventario.relatorio.pdf', ['inventario' => $inventarioAtual->id] + $this->filtrosExportacao) }}" class="gi-action gi-action--ghost" target="_blank">
                        Exportar PDF
                    </a>
                    <a href="{{ route('gestao-inventario.relatorio.xlsx', ['inventario' => $inventarioAtual->id] + $this->filtrosExportacao) }}" class="gi-action gi-action--ghost">
                        Exportar XLSX
                    </a>
                @endif
            </div>
        </section>

        @if ($inventarioAtual)
            <section class="gi-cards">
                @foreach ($this->cards as $card)
                    <article class="gi-card">
                        <span>{{ $card['titulo'] }}</span>
                        <strong>{{ $card['valor'] }}</strong>
                        <small>{{ $card['descricao'] }}</small>
                    </article>
                @endforeach
            </section>

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div class="gi-toolbar-left">
                        @if ($this->inventariosDisponiveis->count() > 1)
                            <label class="gi-field gi-field--select">
                                <span>Inventario</span>
                                <select wire:model.live="inventario">
                                    @foreach ($this->inventariosDisponiveis as $inventarioId => $inventarioNome)
                                        <option value="{{ $inventarioId }}">{{ $inventarioNome }}</option>
                                    @endforeach
                                </select>
                            </label>
                        @endif

                        <label class="gi-field">
                            <span>Buscar item</span>
                            <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Ex.: arroz, feijao, leite" />
                        </label>
                    </div>

                    <div class="gi-toolbar-right">
                        <label class="gi-field gi-field--small">
                            <span>Por pagina</span>
                            <select wire:model.live="porPagina">
                                <option value="8">8</option>
                                <option value="12">12</option>
                                <option value="20">20</option>
                            </select>
                        </label>
                    </div>
                </div>

                <div class="gi-tabs">
                    @foreach ($this->abas as $aba)
                        <button type="button" wire:click="mudarAba('{{ $aba['value'] }}')" class="{{ $aba['value'] === $abaAtiva ? 'is-active' : '' }}">
                            {{ $aba['label'] }}
                        </button>
                    @endforeach
                </div>

                <div class="gi-table-wrap">
                    <table class="gi-table">
                        <thead>
                            <tr>
                                <th><button type="button" wire:click="sortBy('nome')">Item</button></th>
                                <th><button type="button" wire:click="sortBy('tipo_item')">Categoria</button></th>
                                <th><button type="button" wire:click="sortBy('quantidade')">Quantidade</button></th>
                                <th><button type="button" wire:click="sortBy('valor_total')">Valor estimado</button></th>
                                <th><button type="button" wire:click="sortBy('atualizado')">Atualizado</button></th>
                                <th class="text-right">Acoes</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($this->itensFiltrados as $item)
                                <tr>
                                    <td>
                                        <strong>{{ $item['nome'] }}</strong>
                                        <small>{{ $item['descricao'] ?: 'Sem descricao' }}</small>
                                    </td>
                                    <td>{{ $item['tipo_label'] }}</td>
                                    <td>
                                        <strong>{{ number_format((float) $item['quantidade'], 3, ',', '.') }} {{ $item['unidade'] }}</strong>
                                        <small class="status status-{{ $item['status'] }}">{{ ucfirst($item['status']) }}</small>
                                    </td>
                                    <td>
                                        <strong>R$ {{ number_format((float) $item['valor_total'], 2, ',', '.') }}</strong>
                                        <small>R$ {{ number_format((float) $item['valor_unitario_referencia'], 2, ',', '.') }} por unidade</small>
                                    </td>
                                    <td>{{ $item['atualizado'] ?: 'N/A' }}</td>
                                    <td class="text-right">
                                        <div class="gi-row-actions">
                                            <button type="button" wire:click="abrirSlideOver({{ $item['inventario_estoque_id'] }})">Movimentacoes</button>
                                            <button type="button" wire:click="abrirModalBaixa({{ $item['inventario_estoque_id'] }})" @disabled((float) $item['quantidade'] <= 0)>Baixa</button>
                                            @if ($this->podeExportar)
                                                <a href="{{ route('gestao-inventario.item-relatorio.pdf', ['estoque' => $item['inventario_estoque_id']]) }}" target="_blank">Relatorio</a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="gi-empty">Nenhum item encontrado no inventario.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @php($paginacao = $this->paginacao)
                <div class="gi-pagination">
                    <span>Mostrando {{ $paginacao['de'] }}-{{ $paginacao['ate'] }} de {{ $paginacao['total'] }}</span>
                    <div>
                        <button type="button" wire:click="mudarPagina({{ max(1, $paginacao['paginaAtual'] - 1) }})" @disabled($paginacao['paginaAtual'] === 1)>Anterior</button>
                        <button type="button" wire:click="mudarPagina({{ min($paginacao['totalPaginas'], $paginacao['paginaAtual'] + 1) }})" @disabled($paginacao['paginaAtual'] === $paginacao['totalPaginas'])>Proxima</button>
                    </div>
                </div>
            </section>
        @endif
    </div>

    @if ($slideOverAberto)
        <div class="gi-overlay" wire:click="fecharSlideOver"></div>
        <aside class="gi-slideover">
            <header>
                <div>
                    <p class="gi-eyebrow">Historico do item</p>
                    <h3>{{ $itemSelecionadoNome }}</h3>
                    <small>{{ $totalMovimentacoesItem }} movimentacoes registradas</small>
                </div>
                <button type="button" wire:click="fecharSlideOver">Fechar</button>
            </header>

            <div class="gi-slideover-body">
                @forelse ($movimentacoes as $mov)
                    <article class="gi-mov">
                        <div>
                            <strong>{{ $mov['tipo_label'] }}</strong>
                            <small>{{ $mov['categoria'] }}</small>
                        </div>
                        <div class="gi-mov-meta">
                            <strong>{{ number_format((float) $mov['quantidade'], 3, ',', '.') }} {{ $itemSelecionadoUnidade }}</strong>
                            <small>{{ $mov['data'] }}</small>
                            <small>{{ $mov['registrado_por'] }}</small>
                        </div>
                    </article>
                @empty
                    <p class="gi-empty">Nenhuma movimentacao registrada.</p>
                @endforelse
            </div>
        </aside>
    @endif

    @if ($modalBaixaAberto)
        <div class="gi-overlay"></div>
        <section class="gi-modal">
            <header>
                <div>
                    <p class="gi-eyebrow">Registrar baixa</p>
                    <h3>{{ $baixaItemNome }}</h3>
                </div>
                <button type="button" wire:click="fecharModalBaixa">Fechar</button>
            </header>

            <div class="gi-modal-body">
                <label class="gi-field">
                    <span>Quantidade</span>
                    <input type="number" step="0.001" min="0.001" wire:model.defer="baixaQuantidade" />
                    @error('baixaQuantidade') <small class="error">{{ $message }}</small> @enderror
                </label>

                <label class="gi-field">
                    <span>Motivo</span>
                    <select wire:model.defer="baixaMotivo">
                        @foreach (\App\Models\Enums\MotivoBaixa::cases() as $motivo)
                            <option value="{{ $motivo->value }}">{{ $motivo->label() }}</option>
                        @endforeach
                    </select>
                    @error('baixaMotivo') <small class="error">{{ $message }}</small> @enderror
                </label>

                <label class="gi-field">
                    <span>Descricao</span>
                    <textarea rows="4" wire:model.defer="baixaDescricao"></textarea>
                    @error('baixaDescricao') <small class="error">{{ $message }}</small> @enderror
                </label>
            </div>

            <footer>
                <button type="button" wire:click="fecharModalBaixa" class="gi-action gi-action--ghost">Cancelar</button>
                <button type="button" wire:click="registrarBaixa" class="gi-action gi-action--primary">Confirmar baixa</button>
            </footer>
        </section>
    @endif

    <style>
        .gi-page { display: grid; gap: 1.5rem; }
        .gi-hero, .gi-panel, .gi-card, .gi-modal, .gi-slideover { border: 1px solid #d7e0ec; background: linear-gradient(180deg, #fff 0%, #f7fafc 100%); box-shadow: 0 18px 40px rgba(15, 23, 42, 0.06); }
        .gi-hero { border-radius: 1.5rem; padding: 1.75rem; display: flex; justify-content: space-between; gap: 1.5rem; align-items: end; }
        .gi-hero h1, .gi-modal h3, .gi-slideover h3 { margin: 0; color: #15314b; font-weight: 700; }
        .gi-eyebrow { text-transform: uppercase; letter-spacing: 0.12em; font-size: 0.72rem; font-weight: 700; color: #0f766e; margin: 0 0 0.35rem; }
        .gi-hero p, .gi-card span, .gi-card small, .gi-field span, .gi-table small, .gi-slideover small { color: #597086; }
        .gi-actions, .gi-row-actions { display: flex; gap: 0.65rem; flex-wrap: wrap; }
        .gi-action, .gi-row-actions button, .gi-row-actions a, .gi-pagination button, .gi-slideover header button, .gi-modal header button { border-radius: 999px; border: none; padding: 0.72rem 1rem; font-weight: 600; text-decoration: none; cursor: pointer; }
        .gi-action--primary { background: #0f766e; color: #fff; }
        .gi-action--ghost, .gi-row-actions button, .gi-row-actions a, .gi-pagination button, .gi-slideover header button, .gi-modal header button { background: #e6eff8; color: #15314b; }
        .gi-cards { display: grid; gap: 1rem; grid-template-columns: repeat(4, minmax(0, 1fr)); }
        .gi-card { border-radius: 1.25rem; padding: 1.2rem; display: grid; gap: 0.35rem; }
        .gi-card strong { font-size: 1.55rem; color: #15314b; }
        .gi-panel { border-radius: 1.35rem; padding: 1.3rem; display: grid; gap: 1rem; }
        .gi-toolbar { display: flex; justify-content: space-between; gap: 1rem; }
        .gi-toolbar-left, .gi-toolbar-right { display: flex; gap: 0.85rem; flex-wrap: wrap; }
        .gi-field { display: grid; gap: 0.45rem; font-weight: 600; color: #15314b; min-width: 220px; }
        .gi-field--small { min-width: 140px; }
        .gi-field input, .gi-field select, .gi-field textarea { border-radius: 0.95rem; border: 1px solid #c8d5e4; background: #fff; padding: 0.85rem 0.95rem; font-size: 0.95rem; color: #15314b; }
        .gi-tabs { display: flex; gap: 0.65rem; flex-wrap: wrap; }
        .gi-tabs button { border-radius: 999px; border: 1px solid #c8d5e4; background: #f4f8fb; color: #34506a; padding: 0.58rem 0.95rem; font-weight: 600; }
        .gi-tabs .is-active { background: #0f766e; color: #fff; border-color: #0f766e; }
        .gi-table-wrap { overflow: auto; border-radius: 1rem; border: 1px solid #d7e0ec; }
        .gi-table { width: 100%; border-collapse: collapse; }
        .gi-table th, .gi-table td { padding: 0.95rem 1rem; text-align: left; border-bottom: 1px solid #edf3f8; vertical-align: top; }
        .gi-table th { background: #f4f8fb; color: #34506a; font-size: 0.82rem; text-transform: uppercase; letter-spacing: 0.06em; }
        .gi-table th button { background: none; border: none; padding: 0; font: inherit; color: inherit; cursor: pointer; }
        .gi-table td strong { display: block; color: #15314b; }
        .status { display: inline-flex; padding: 0.18rem 0.55rem; border-radius: 999px; font-size: 0.76rem; font-weight: 700; }
        .status-normal { background: #dcfce7; color: #166534; }
        .status-critico { background: #fef3c7; color: #92400e; }
        .status-zerado { background: #fee2e2; color: #991b1b; }
        .text-right { text-align: right; }
        .gi-pagination { display: flex; justify-content: space-between; gap: 1rem; align-items: center; color: #597086; font-size: 0.9rem; }
        .gi-pagination div { display: flex; gap: 0.5rem; }
        .gi-overlay { position: fixed; inset: 0; background: rgba(15, 23, 42, 0.35); z-index: 40; }
        .gi-slideover { position: fixed; top: 0; right: 0; width: min(520px, 100%); height: 100vh; z-index: 50; box-shadow: -24px 0 48px rgba(15, 23, 42, 0.18); display: grid; grid-template-rows: auto 1fr; }
        .gi-slideover header, .gi-modal header { display: flex; justify-content: space-between; gap: 1rem; align-items: start; padding: 1.4rem; border-bottom: 1px solid #d7e0ec; }
        .gi-slideover-body { overflow: auto; padding: 1.2rem 1.4rem 2rem; display: grid; gap: 0.8rem; }
        .gi-mov { padding: 1rem; border-radius: 1rem; border: 1px solid #d7e0ec; background: #fff; display: flex; justify-content: space-between; gap: 1rem; }
        .gi-mov-meta { display: grid; justify-items: end; gap: 0.25rem; color: #15314b; }
        .gi-modal { position: fixed; z-index: 60; inset: 50% auto auto 50%; transform: translate(-50%, -50%); width: min(560px, calc(100vw - 2rem)); border-radius: 1.25rem; overflow: hidden; }
        .gi-modal-body { padding: 1.2rem 1.4rem; display: grid; gap: 0.9rem; }
        .gi-modal footer { display: flex; justify-content: flex-end; gap: 0.75rem; padding: 0 1.4rem 1.4rem; }
        .gi-empty, .error { color: #991b1b; }
        @media (max-width: 980px) { .gi-cards { grid-template-columns: 1fr 1fr; } .gi-toolbar { flex-direction: column; } }
        @media (max-width: 720px) { .gi-hero, .gi-actions, .gi-cards { flex-direction: column; grid-template-columns: 1fr; align-items: stretch; } .text-right { text-align: left; } }
    </style>
</x-filament-panels::page>
