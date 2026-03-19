<x-filament-panels::page>
<style>
    .pm-page { padding: 1.5rem; }
    .pm-header { margin-bottom: 1.5rem; }
    .pm-header h2 { font-size: 1.25rem; font-weight: 700; margin: 0 0 .25rem; color: var(--gray-900, #111827); }
    .pm-header p  { font-size: .875rem; color: var(--gray-500, #6b7280); margin: 0; }

    .pm-field-label { display: block; font-size: .875rem; font-weight: 500; color: var(--gray-700, #374151); margin-bottom: .375rem; }
    .pm-field-label span { font-weight: 400; color: var(--gray-400, #9ca3af); }
    .pm-textarea {
        width: 100%; border-radius: .5rem;
        border: 1px solid var(--gray-300, #d1d5db);
        background: var(--gray-50, #fff);
        color: var(--gray-900, #111827);
        font-size: .875rem; padding: .5rem .75rem;
        outline: none; resize: vertical;
        font-family: inherit;
        box-sizing: border-box;
        margin-bottom: 1.25rem;
    }
    .pm-textarea:focus { border-color: var(--primary-500, #6366f1); box-shadow: 0 0 0 2px var(--primary-200, #c7d2fe); }

    /* card */
    .pm-card {
        background: #fff;
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: .75rem;
        overflow: hidden;
        margin-bottom: 1rem;
        box-shadow: 0 1px 3px rgba(0,0,0,.06);
    }
    .pm-card-toolbar {
        display: flex; align-items: center; justify-content: space-between;
        padding: .75rem 1rem;
        border-bottom: 1px solid var(--gray-200, #e5e7eb);
    }
    .pm-card-toolbar-title { font-size: .875rem; font-weight: 600; color: var(--gray-700, #374151); }
    .pm-badge {
        display: inline-flex; align-items: center;
        padding: .1rem .5rem; border-radius: 9999px;
        font-size: .75rem; font-weight: 500;
        background: #ede9fe; color: #5b21b6;
        margin-left: .5rem;
    }

    /* botão primário */
    .pm-btn {
        display: inline-flex; align-items: center; gap: .375rem;
        padding: .4rem .85rem; border-radius: .5rem;
        font-size: .875rem; font-weight: 500;
        cursor: pointer; border: none; transition: opacity .15s;
    }
    .pm-btn:hover { opacity: .88; }
    .pm-btn-primary { background: var(--primary-600, #4f46e5); color: #fff; }
    .pm-btn-success { background: #16a34a; color: #fff; }
    .pm-btn-success:disabled { opacity: .5; cursor: not-allowed; }
    .pm-btn-outline {
        background: transparent;
        border: 1px solid var(--gray-300, #d1d5db);
        color: var(--gray-700, #374151);
        text-decoration: none;
    }
    .pm-btn-danger-ghost { background: transparent; border: none; color: #ef4444; padding: .25rem; cursor: pointer; }
    .pm-btn-danger-ghost:hover { color: #b91c1c; }

    /* empty state */
    .pm-empty {
        display: flex; flex-direction: column; align-items: center;
        justify-content: center; padding: 3rem 1rem;
        color: var(--gray-400, #9ca3af);
        font-size: .875rem;
    }
    .pm-empty svg { width: 2.5rem; height: 2.5rem; margin-bottom: .5rem; }

    /* tabela */
    .pm-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .pm-table thead tr {
        background: var(--gray-50, #f9fafb);
        text-transform: uppercase;
        font-size: .7rem; letter-spacing: .05em;
        color: var(--gray-500, #6b7280);
        font-weight: 600;
    }
    .pm-table th, .pm-table td { padding: .65rem 1rem; text-align: left; }
    .pm-table th.right, .pm-table td.right { text-align: right; }
    .pm-table tbody tr { border-top: 1px solid var(--gray-100, #f3f4f6); }
    .pm-table tbody tr:hover { background: var(--gray-50, #f9fafb); }
    .pm-table .td-item-nome { font-weight: 500; color: var(--gray-900, #111827); }
    .pm-table .td-unit { font-size: .75rem; color: var(--gray-400, #9ca3af); margin-left: .25rem; }
    .pm-table .td-secondary { color: var(--gray-600, #4b5563); }
    .pm-table .td-saldo { color: var(--gray-600, #4b5563); }
    .pm-table .td-qty { font-weight: 600; color: var(--gray-900, #111827); }

    /* footer actions */
    .pm-actions { display: flex; align-items: center; justify-content: flex-end; gap: .75rem; margin-top: .5rem; }

    /* ── MODAL ── */
    .pm-overlay {
        position: fixed; inset: 0; z-index: 50;
        display: flex; align-items: center; justify-content: center;
    }
    .pm-overlay-bg {
        position: absolute; inset: 0;
        background: rgba(0,0,0,.45);
        backdrop-filter: blur(2px);
    }
    .pm-modal {
        position: relative; z-index: 10;
        width: 100%; max-width: 42rem;
        margin: 1rem;
        background: #fff;
        border-radius: 1rem;
        box-shadow: 0 20px 60px rgba(0,0,0,.2);
        overflow: hidden;
    }
    .pm-modal-header {
        display: flex; align-items: center; justify-content: space-between;
        padding: 1rem 1.5rem;
        border-bottom: 1px solid var(--gray-200, #e5e7eb);
    }
    .pm-modal-header h3 { font-size: 1rem; font-weight: 600; margin: 0; color: var(--gray-900, #111827); }
    .pm-modal-body { padding: 1.25rem 1.5rem; display: flex; flex-direction: column; gap: 1.25rem; max-height: 70vh; overflow-y: auto; }
    .pm-modal-footer {
        display: flex; align-items: center; justify-content: flex-end; gap: .75rem;
        padding: .875rem 1.5rem;
        border-top: 1px solid var(--gray-200, #e5e7eb);
    }

    /* select nativo */
    .pm-select {
        width: 100%; border-radius: .5rem;
        border: 1px solid var(--gray-300, #d1d5db);
        background: #fff; color: var(--gray-900, #111827);
        font-size: .875rem; padding: .5rem .75rem;
        outline: none; font-family: inherit;
        box-sizing: border-box;
    }
    .pm-select:focus { border-color: var(--primary-500, #6366f1); box-shadow: 0 0 0 2px var(--primary-200, #c7d2fe); }
    .pm-field-hint { font-size: .75rem; color: var(--gray-400, #9ca3af); margin-top: .25rem; }

    /* contrato card no modal */
    .pm-contrato-row {
        display: flex; align-items: center; gap: .75rem;
        padding: .75rem;
        border: 1px solid var(--gray-200, #e5e7eb);
        border-radius: .5rem;
        background: var(--gray-50, #f9fafb);
    }
    .pm-contrato-info { flex: 1; min-width: 0; }
    .pm-contrato-empresa { font-size: .875rem; font-weight: 600; color: var(--gray-900, #111827); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    .pm-contrato-numero { font-size: .75rem; color: var(--gray-500, #6b7280); }
    .pm-contrato-numero span { font-weight: 500; color: var(--gray-700, #374151); }
    .pm-saldo-box { text-align: right; flex-shrink: 0; }
    .pm-saldo-label { font-size: .75rem; color: var(--gray-500, #6b7280); }
    .pm-saldo-value { font-size: .875rem; font-weight: 600; color: #16a34a; }
    .pm-qty-box { flex-shrink: 0; width: 7rem; }
    .pm-qty-label { font-size: .75rem; color: var(--gray-500, #6b7280); margin-bottom: .25rem; }
    .pm-qty-input {
        width: 100%; border-radius: .375rem;
        border: 1px solid var(--gray-300, #d1d5db);
        background: #fff; color: var(--gray-900, #111827);
        font-size: .875rem; padding: .3rem .5rem;
        outline: none; box-sizing: border-box; font-family: inherit;
    }
    .pm-qty-input:focus { border-color: var(--primary-500, #6366f1); box-shadow: 0 0 0 2px var(--primary-200, #c7d2fe); }

    /* aviso */
    .pm-alert-warn {
        display: flex; align-items: center; gap: .5rem;
        padding: .75rem; border-radius: .5rem;
        background: #fffbeb; color: #92400e;
        border: 1px solid #fde68a;
        font-size: .875rem;
    }
    .pm-alert-warn svg { width: 1rem; height: 1rem; flex-shrink: 0; }

    /* dark mode básico */
    @media (prefers-color-scheme: dark) {
        .pm-header h2 { color: #f9fafb; }
        .pm-textarea, .pm-select, .pm-qty-input { background: #1f2937; border-color: #374151; color: #f9fafb; }
        .pm-card { background: #1f2937; border-color: #374151; }
        .pm-card-toolbar { border-color: #374151; }
        .pm-card-toolbar-title { color: #e5e7eb; }
        .pm-table thead tr { background: #374151; color: #9ca3af; }
        .pm-table tbody tr { border-color: #374151; }
        .pm-table tbody tr:hover { background: #374151; }
        .pm-table .td-item-nome, .pm-table .td-qty { color: #f9fafb; }
        .pm-modal { background: #1f2937; }
        .pm-modal-header { border-color: #374151; }
        .pm-modal-header h3 { color: #f9fafb; }
        .pm-modal-footer { border-color: #374151; }
        .pm-contrato-row { background: #374151; border-color: #4b5563; }
        .pm-contrato-empresa { color: #f9fafb; }
        .pm-btn-outline { border-color: #374151; color: #d1d5db; }
    }
</style>

<div class="pm-page">

    {{-- CABEÇALHO --}}
    <div class="pm-header">
        <h2>Novo Pedido de Merenda</h2>
        <p>Monte a relação de itens antes de confirmar o pedido.</p>
    </div>

    {{-- OBSERVAÇÕES --}}
    <label class="pm-field-label">Observações <span>(opcional)</span></label>
    <textarea
        wire:model="observacoes"
        rows="2"
        class="pm-textarea"
        placeholder="Informações adicionais sobre este pedido..."
    ></textarea>

    {{-- TABELA DE ITENS EM MEMÓRIA --}}
    <div class="pm-card">
        <div class="pm-card-toolbar">
            <span class="pm-card-toolbar-title">
                Itens do Pedido
                @if(count($itensPedido) > 0)
                    <span class="pm-badge">{{ count($itensPedido) }}</span>
                @endif
            </span>
            <button wire:click="abrirModal" type="button" class="pm-btn pm-btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1rem;height:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15"/></svg>
                Adicionar Item
            </button>
        </div>

        @if(empty($itensPedido))
            <div class="pm-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 0 0-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 0 0-16.536-1.84M7.5 14.25 5.106 5.272M6 20.25a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Zm12.75 0a.75.75 0 1 1-1.5 0 .75.75 0 0 1 1.5 0Z"/></svg>
                Nenhum item adicionado ainda.
            </div>
        @else
            <table class="pm-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Contrato</th>
                        <th>Empresa</th>
                        <th class="right">Saldo Disponível</th>
                        <th class="right">Qtd. Pedida</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($itensPedido as $chave => $entry)
                        <tr>
                            <td>
                                <span class="td-item-nome">{{ $entry['item_nome'] }}</span>
                                <span class="td-unit">({{ $entry['unidade'] }})</span>
                            </td>
                            <td class="td-secondary">{{ $entry['numero_contrato'] }}</td>
                            <td class="td-secondary">{{ $entry['empresa'] }}</td>
                            <td class="right td-saldo">{{ number_format($entry['saldo'], 3, ',', '.') }}</td>
                            <td class="right td-qty">{{ number_format($entry['quantidade'], 3, ',', '.') }}</td>
                            <td class="right">
                                <button wire:click="removerItem('{{ $chave }}')" type="button" class="pm-btn-danger-ghost" title="Remover">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:1rem;height:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0"/></svg>
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- BOTÕES DE AÇÃO --}}
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
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1rem;height:1rem"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
            Confirmar Pedido
        </button>
    </div>

</div>

{{-- ================================================================ --}}
{{-- MODAL — ADICIONAR ITEM                                           --}}
{{-- ================================================================ --}}
@if($modalAberto)
    <div class="pm-overlay" x-data x-init="$el.querySelector('[data-modal-panel]').focus()">

        <div class="pm-overlay-bg" wire:click="fecharModal"></div>

        <div data-modal-panel tabindex="-1" class="pm-modal" style="outline:none">

            {{-- Header --}}
            <div class="pm-modal-header">
                <h3>Adicionar Item ao Pedido</h3>
                <button wire:click="fecharModal" class="pm-btn-danger-ghost" style="color:#9ca3af">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:1.25rem;height:1.25rem"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            {{-- Corpo --}}
            <div class="pm-modal-body">

                {{-- Select de item --}}
                <div>
                    <label class="pm-field-label">Item <span style="color:#ef4444">*</span></label>
                    <select wire:model.live="itemSelecionado" class="pm-select">
                        <option value="">Selecione um item...</option>
                        @foreach($this->itensComSaldo as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <p class="pm-field-hint">Apenas itens com saldo disponível em contratos ativos.</p>
                </div>

                {{-- Contratos com saldo --}}
                @if($itemSelecionado && count($contratosDoItem) > 0)
                    <div>
                        <label class="pm-field-label">Contratos com saldo disponível</label>
                        <div style="display:flex;flex-direction:column;gap:.75rem">
                            @foreach($contratosDoItem as $contratoItemId => $entry)
                                <div class="pm-contrato-row">
                                    <div class="pm-contrato-info">
                                        <div class="pm-contrato-empresa">{{ $entry['empresa'] }}</div>
                                        <div class="pm-contrato-numero">
                                            Contrato: <span>{{ $entry['numero_contrato'] }}</span>
                                        </div>
                                    </div>
                                    <div class="pm-saldo-box">
                                        <div class="pm-saldo-label">Saldo</div>
                                        <div class="pm-saldo-value">{{ number_format($entry['saldo'], 3, ',', '.') }}</div>
                                    </div>
                                    <div class="pm-qty-box">
                                        <div class="pm-qty-label">Qtd. a pedir</div>
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
                    </div>
                @elseif($itemSelecionado && count($contratosDoItem) === 0)
                    <div class="pm-alert-warn">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg>
                        Nenhum contrato ativo com saldo disponível para este item.
                    </div>
                @endif
            </div>

            {{-- Footer --}}
            <div class="pm-modal-footer">
                <button wire:click="fecharModal" type="button" class="pm-btn pm-btn-outline">Cancelar</button>
                <button wire:click="confirmarAdicaoItem" type="button" class="pm-btn pm-btn-primary">Confirmar Adição</button>
            </div>
        </div>
    </div>
@endif

</x-filament-panels::page>