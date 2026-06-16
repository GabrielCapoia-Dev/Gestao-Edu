<x-filament-panels::page>
<style>
    .fi-page-content { padding: 0; }
    .pm-page { padding: 0.75rem; }
    .pm-layout { display: grid; gap: 1rem; }
    .pm-section {
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 0.9rem;
        padding: 1.25rem;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }
    .pm-section-title {
        font-size: 0.98rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 1rem;
    }
    .pm-summary-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }
    .pm-stat {
        border: 1px solid #e5e7eb;
        border-radius: 0.8rem;
        padding: 0.9rem 1rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
    }
    .pm-stat-label {
        display: block;
        font-size: 0.75rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.05em;
        color: #64748b;
        margin-bottom: 0.35rem;
    }
    .pm-stat-value {
        font-size: 1.25rem;
        font-weight: 700;
        color: #0f172a;
    }
    .pm-toolbar {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
        flex-wrap: wrap;
        margin-bottom: 1rem;
    }
    .pm-toolbar-left,
    .pm-toolbar-right {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .pm-badge {
        display: inline-flex;
        align-items: center;
        padding: 0.15rem 0.6rem;
        border-radius: 999px;
        background: #e0f2fe;
        color: #0369a1;
        font-size: 0.78rem;
        font-weight: 700;
    }
    .pm-field-label {
        display: block;
        font-size: 0.875rem;
        font-weight: 600;
        color: #334155;
        margin-bottom: 0.4rem;
    }
    .pm-field-label span { color: #94a3b8; font-weight: 500; }
    .pm-textarea,
    .pm-input,
    .pm-select,
    .pm-qty-input {
        width: 100%;
        border-radius: 0.7rem;
        border: 1px solid #cbd5e1;
        background: #fff;
        color: #0f172a;
        font-size: 0.92rem;
        padding: 0.7rem 0.85rem;
        box-sizing: border-box;
        outline: none;
    }
    .pm-textarea:focus,
    .pm-input:focus,
    .pm-select:focus,
    .pm-qty-input:focus {
        border-color: #0ea5e9;
        box-shadow: 0 0 0 3px rgba(14, 165, 233, 0.12);
    }
    .pm-textarea { resize: vertical; min-height: 88px; }
    .pm-search-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 0.85rem;
        margin-bottom: 1rem;
    }
    .pm-card {
        border: 1px solid #e5e7eb;
        border-radius: 0.85rem;
        overflow: hidden;
        background: #fff;
    }
    .pm-table-wrap { overflow-x: auto; }
    .pm-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.89rem;
    }
    .pm-table thead tr {
        background: #f8fafc;
        color: #64748b;
        text-transform: uppercase;
        font-size: 0.72rem;
        letter-spacing: 0.05em;
    }
    .pm-table th,
    .pm-table td {
        padding: 0.8rem 1rem;
        text-align: left;
        border-top: 1px solid #f1f5f9;
        vertical-align: middle;
    }
    .pm-table th.right,
    .pm-table td.right { text-align: right; }
    .pm-table tbody tr:hover { background: #f8fafc; }
    .pm-item-name { font-weight: 700; color: #0f172a; }
    .pm-item-meta,
    .pm-muted { color: #64748b; font-size: 0.82rem; }
    .pm-empty {
        padding: 2.5rem 1rem;
        text-align: center;
        color: #94a3b8;
        font-size: 0.92rem;
    }
    .pm-empty strong { display: block; color: #334155; margin-bottom: 0.35rem; }
    .pm-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.45rem;
        padding: 0.7rem 1rem;
        border-radius: 0.7rem;
        font-size: 0.9rem;
        font-weight: 700;
        cursor: pointer;
        border: none;
        text-decoration: none;
        transition: 0.18s ease;
    }
    .pm-btn:hover { transform: translateY(-1px); }
    .pm-btn-primary { background: #0284c7; color: #fff; }
    .pm-btn-success { background: #15803d; color: #fff; }
    .pm-btn-outline { background: #fff; color: #334155; border: 1px solid #cbd5e1; }
    .pm-btn-danger-ghost {
        background: transparent;
        color: #dc2626;
        border: none;
        padding: 0.25rem;
        cursor: pointer;
    }
    .pm-actions {
        display: flex;
        justify-content: flex-end;
        gap: 0.75rem;
        flex-wrap: wrap;
    }
    .pm-hint {
        font-size: 0.78rem;
        color: #94a3b8;
        margin-top: 0.35rem;
    }
    .pm-overlay {
        position: fixed;
        inset: 0;
        z-index: 50;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 1rem;
    }
    .pm-overlay-bg {
        position: absolute;
        inset: 0;
        background: rgba(15, 23, 42, 0.55);
        backdrop-filter: blur(3px);
    }
    .pm-modal {
        position: relative;
        z-index: 1;
        width: min(980px, 100%);
        max-height: calc(100vh - 2rem);
        overflow: hidden;
        border-radius: 1rem;
        background: #fff;
        box-shadow: 0 24px 80px rgba(15, 23, 42, 0.28);
        display: flex;
        flex-direction: column;
    }
    .pm-modal-header,
    .pm-modal-footer {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e5e7eb;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 0.75rem;
    }
    .pm-modal-footer {
        border-bottom: none;
        border-top: 1px solid #e5e7eb;
        justify-content: flex-end;
    }
    .pm-modal-header h3 {
        margin: 0;
        font-size: 1rem;
        font-weight: 700;
        color: #0f172a;
    }
    .pm-modal-body {
        padding: 1.25rem;
        overflow-y: auto;
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .pm-contract-grid {
        display: grid;
        gap: 0.75rem;
    }
    .pm-contract-card {
        border: 1px solid #e2e8f0;
        border-radius: 0.85rem;
        padding: 0.9rem;
        background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
        display: grid;
        grid-template-columns: minmax(0, 1.8fr) minmax(120px, 0.8fr) minmax(140px, 0.8fr);
        gap: 0.75rem;
        align-items: end;
    }
    .pm-contract-company { font-weight: 700; color: #0f172a; }
    .pm-contract-line { font-size: 0.82rem; color: #64748b; margin-top: 0.2rem; }
    .pm-contract-balance {
        border-radius: 0.75rem;
        background: #ecfccb;
        padding: 0.7rem 0.85rem;
        text-align: center;
    }
    .pm-contract-balance small {
        display: block;
        color: #4d7c0f;
        font-size: 0.72rem;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-bottom: 0.2rem;
    }
    .pm-contract-balance strong { color: #166534; font-size: 1rem; }
    .pm-alert {
        border: 1px solid #fde68a;
        background: #fffbeb;
        color: #92400e;
        border-radius: 0.8rem;
        padding: 0.9rem 1rem;
        font-size: 0.9rem;
    }
    .pm-subtle {
        font-size: 0.8rem;
        color: #64748b;
    }
    @media (max-width: 768px) {
        .pm-page { padding: 0.45rem; }
        .pm-section { padding: 1rem; }
        .pm-table th,
        .pm-table td { padding: 0.7rem 0.75rem; }
        .pm-contract-card {
            grid-template-columns: 1fr;
            align-items: stretch;
        }
        .pm-actions,
        .pm-modal-footer,
        .pm-modal-header { flex-direction: column; align-items: stretch; }
        .pm-btn { width: 100%; }
    }
    .dark .pm-section,
    .dark .pm-card,
    .dark .pm-modal { background: #0f172a; border-color: #334155; }
    .dark .pm-section-title,
    .dark .pm-modal-header h3,
    .dark .pm-stat-value,
    .dark .pm-item-name,
    .dark .pm-contract-company { color: #f8fafc; }
    .dark .pm-stat,
    .dark .pm-contract-card { background: linear-gradient(180deg, #0f172a 0%, #111827 100%); border-color: #334155; }
    .dark .pm-stat-label,
    .dark .pm-muted,
    .dark .pm-item-meta,
    .dark .pm-subtle,
    .dark .pm-field-label,
    .dark .pm-hint,
    .dark .pm-contract-line { color: #94a3b8; }
    .dark .pm-textarea,
    .dark .pm-input,
    .dark .pm-select,
    .dark .pm-qty-input { background: #111827; border-color: #334155; color: #f8fafc; }
    .dark .pm-btn-outline { background: #0f172a; border-color: #334155; color: #e2e8f0; }
    .dark .pm-table thead tr { background: #111827; color: #94a3b8; }
    .dark .pm-table th,
    .dark .pm-table td,
    .dark .pm-modal-header,
    .dark .pm-modal-footer { border-color: #334155; }
    .dark .pm-table tbody tr:hover { background: #111827; }
    .dark .pm-empty { color: #94a3b8; }
    .dark .pm-empty strong { color: #e2e8f0; }
    .dark .pm-contract-balance { background: #16341f; }
    .dark .pm-contract-balance small { color: #86efac; }
    .dark .pm-contract-balance strong { color: #dcfce7; }
    .dark .pm-alert { background: #2a2305; border-color: #854d0e; color: #fcd34d; }
</style>

<div class="pm-page">
    <div class="pm-layout">
        <div class="pm-summary-grid">
            <div class="pm-stat">
                <span class="pm-stat-label">Itens no pedido</span>
                <span class="pm-stat-value">{{ $this->totalItensPedido }}</span>
            </div>
            <div class="pm-stat">
                <span class="pm-stat-label">Quantidade total</span>
                <span class="pm-stat-value">{{ number_format($this->quantidadeTotalPedido, 3, ',', '.') }}</span>
            </div>
            <div class="pm-stat">
                <span class="pm-stat-label">Contratos usados</span>
                <span class="pm-stat-value">{{ $this->totalContratosSelecionados }}</span>
            </div>
            <div class="pm-stat">
                <span class="pm-stat-label">Empresas envolvidas</span>
                <span class="pm-stat-value">{{ $this->totalEmpresasSelecionadas }}</span>
            </div>
        </div>

        <div class="pm-section">
            <h3 class="pm-section-title">Resumo do pedido</h3>

            <label class="pm-field-label">Observações <span>(opcional)</span></label>
            <textarea
                wire:model.live.debounce.300ms="observacoes"
                class="pm-textarea"
                placeholder="Anote contexto, urgencia, observações de entrega ou qualquer detalhe util."
            ></textarea>

            <div class="pm-toolbar">
                <div class="pm-toolbar-left">
                    <strong style="font-size:0.95rem;color:#334155;">Itens adicionados</strong>
                    <span class="pm-badge">{{ count($this->itensPedidoFiltrados) }} visíveis</span>
                </div>
                <div class="pm-toolbar-right">
                    <div style="min-width:260px;max-width:360px;width:100%;">
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="buscaItensPedido"
                            class="pm-input"
                            placeholder="Buscar por item, empresa ou contrato..."
                        >
                    </div>
                    <button wire:click="abrirModal" type="button" class="pm-btn pm-btn-primary">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1rem;height:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                        Adicionar item
                    </button>
                </div>
            </div>

            <div class="pm-card">
                @if(empty($itensPedido))
                    <div class="pm-empty">
                        <strong>Nenhum item foi adicionado ainda.</strong>
                        Comece escolhendo um item com saldo disponível para montar o pedido.
                    </div>
                @elseif(empty($this->itensPedidoFiltrados))
                    <div class="pm-empty">
                        <strong>Nenhum resultado para a busca atual.</strong>
                        Ajuste os termos para localizar os itens já adicionados.
                    </div>
                @else
                    <div class="pm-table-wrap">
                        <table class="pm-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Contrato</th>
                                    <th>Empresa</th>
                                    <th class="right">Saldo</th>
                                    <th class="right">Qtd. pedida</th>
                                    <th class="right">Adicionado</th>
                                    <th class="right"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($this->itensPedidoFiltrados as $chave => $entry)
                                    <tr>
                                        <td>
                                            <div class="pm-item-name">{{ $entry['item_nome'] }}</div>
                                            <div class="pm-item-meta">{{ $entry['unidade'] }}</div>
                                        </td>
                                        <td class="pm-muted">{{ $entry['numero_contrato'] }}</td>
                                        <td class="pm-muted">{{ $entry['empresa'] }}</td>
                                        <td class="right pm-muted">{{ number_format($entry['saldo'], 3, ',', '.') }}</td>
                                        <td class="right"><strong>{{ number_format($entry['quantidade'], 3, ',', '.') }}</strong></td>
                                        <td class="right pm-muted">{{ \Illuminate\Support\Carbon::parse($entry['adicionado_em'] ?? now())->format('d/m/Y H:i') }}</td>
                                        <td class="right">
                                            <button wire:click="removerItem('{{ $chave }}')" type="button" class="pm-btn-danger-ghost" title="Remover item">
                                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" style="width:1rem;height:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" /></svg>
                                            </button>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>

        <div class="pm-actions">
            <a href="{{ \App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource::getUrl('index') }}" class="pm-btn pm-btn-outline">
                Cancelar
            </a>
            <button
                wire:click="confirmarPedido"
                type="button"
                class="pm-btn pm-btn-success"
                @if(empty($itensPedido)) disabled @endif
            >
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1rem;height:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                Confirmar pedido
            </button>
        </div>
    </div>
</div>

@if($modalAberto)
    <div class="pm-overlay" x-data x-init="$el.querySelector('[data-modal-panel]').focus()">
        <div class="pm-overlay-bg" wire:click="fecharModal"></div>

        <div data-modal-panel tabindex="-1" class="pm-modal" style="outline:none">
            <div class="pm-modal-header">
                <div>
                    <h3>Adicionar item ao pedido</h3>
                    <div class="pm-subtle">Busque o item, filtre os contratos e informe apenas as quantidades necessarias.</div>
                </div>
                <button wire:click="fecharModal" class="pm-btn-danger-ghost" type="button">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.2rem;height:1.2rem"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="pm-modal-body">
                <div class="pm-search-grid">
                    <div>
                        <label class="pm-field-label">Buscar item disponível</label>
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="buscaItemDisponivel"
                            class="pm-input"
                            placeholder="Nome, descrição ou unidade..."
                        >
                        <div class="pm-hint">Mostrando até 100 itens com saldo em contratos ativos.</div>
                    </div>
                    <div>
                        <label class="pm-field-label">Selecionar item</label>
                        <select wire:model.live="itemSelecionado" class="pm-select">
                            <option value="">Selecione um item...</option>
                            @foreach($this->itensComSaldo as $id => $label)
                                <option value="{{ $id }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>

                @if($itemSelecionado)
                    <div class="pm-search-grid">
                        <div>
                            <label class="pm-field-label">Filtrar por empresa</label>
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="filtroEmpresaModal"
                                class="pm-input"
                                placeholder="Nome da empresa contratada..."
                            >
                        </div>
                        <div>
                            <label class="pm-field-label">Filtrar por contrato</label>
                            <input
                                type="text"
                                wire:model.live.debounce.250ms="filtroContratoModal"
                                class="pm-input"
                                placeholder="Número do contrato..."
                            >
                        </div>
                    </div>
                @endif

                @if($itemSelecionado && count($contratosDoItem) > 0 && count($this->contratosFiltrados) > 0)
                    <div class="pm-toolbar" style="margin-bottom:0;">
                        <div class="pm-toolbar-left">
                            <strong style="font-size:0.92rem;color:#334155;">Contratos com saldo</strong>
                            <span class="pm-badge">{{ count($this->contratosFiltrados) }} resultados</span>
                        </div>
                    </div>

                    <div class="pm-contract-grid">
                        @foreach($this->contratosFiltrados as $contratoItemId => $entry)
                            <div class="pm-contract-card">
                                <div>
                                    <div class="pm-contract-company">{{ $entry['empresa'] }}</div>
                                    <div class="pm-contract-line">Contrato: <strong>{{ $entry['numero_contrato'] }}</strong></div>
                                </div>
                                <div class="pm-contract-balance">
                                    <small>Saldo disponível</small>
                                    <strong>{{ number_format($entry['saldo'], 3, ',', '.') }}</strong>
                                </div>
                                <div>
                                    <label class="pm-field-label" style="margin-bottom:0.3rem;">Qtd. a pedir</label>
                                    <input
                                        type="number"
                                        step="0.001"
                                        min="0"
                                        max="{{ $entry['saldo'] }}"
                                        placeholder="0.000"
                                        wire:change="atualizarQuantidade({{ $contratoItemId }}, $event.target.value)"
                                        class="pm-qty-input"
                                    />
                                </div>
                            </div>
                        @endforeach
                    </div>
                @elseif($itemSelecionado && count($contratosDoItem) > 0)
                    <div class="pm-alert">
                        Nenhum contrato corresponde aos filtros atuais. Ajuste a busca por empresa ou contrato para continuar.
                    </div>
                @elseif($itemSelecionado)
                    <div class="pm-alert">
                        Nenhum contrato ativo com saldo disponível foi encontrado para este item.
                    </div>
                @endif
            </div>

            <div class="pm-modal-footer">
                <button wire:click="fecharModal" type="button" class="pm-btn pm-btn-outline">Cancelar</button>
                <button wire:click="confirmarAdicaoItem" type="button" class="pm-btn pm-btn-primary">Confirmar adicao</button>
            </div>
        </div>
    </div>
@endif
</x-filament-panels::page>
