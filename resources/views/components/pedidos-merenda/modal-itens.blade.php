<style>
    .mi-table {
        width: 100%;
        border-collapse: collapse;
        font-size: .875rem;
    }

    .mi-table thead tr {
        background: var(--gray-50, #f9fafb);
        font-size: .7rem;
        letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--gray-500, #6b7280);
        font-weight: 600;
    }

    .mi-table th,
    .mi-table td {
        padding: .65rem .875rem;
        text-align: left;
        vertical-align: middle;
    }

    .mi-table th.right,
    .mi-table td.right {
        text-align: right;
    }

    .mi-table tbody tr {
        border-top: 1px solid var(--gray-100, #f3f4f6);
        transition: opacity .2s;
    }

    .mi-table tbody tr:hover {
        background: var(--gray-50, #f9fafb);
    }

    .mi-item-nome {
        font-weight: 500;
        color: var(--gray-900, #111827);
    }

    .mi-item-unit {
        font-size: .75rem;
        color: var(--gray-400, #9ca3af);
        margin-left: .2rem;
    }

    .mi-secondary {
        color: var(--gray-500, #6b7280);
        font-size: .8125rem;
    }

    .mi-saldo-disp {
        font-weight: 600;
        color: #16a34a;
    }

    .mi-saldo-diff-pos {
        font-size: .75rem;
        color: #16a34a;
    }

    .mi-saldo-diff-neg {
        font-size: .75rem;
        color: #dc2626;
    }

    .mi-qty-label {
        display: inline-flex;
        align-items: center;
        padding: .2rem .55rem;
        border-radius: .375rem;
        background: var(--gray-100, #f3f4f6);
        color: var(--gray-700, #374151);
        font-size: .8125rem;
        font-weight: 600;
        white-space: nowrap;
    }

    .mi-row-removed td {
        opacity: .5;
    }

    .mi-badge-removed {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .15rem .5rem;
        border-radius: 9999px;
        background: #fee2e2;
        color: #991b1b;
        font-size: .7rem;
        font-weight: 600;
        white-space: nowrap;
        margin-left: .4rem;
        vertical-align: middle;
    }

    .mi-badge-pending {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .15rem .5rem;
        border-radius: 9999px;
        background: #fef9c3;
        color: #854d0e;
        font-size: .7rem;
        font-weight: 600;
        white-space: nowrap;
        margin-left: .4rem;
        vertical-align: middle;
    }

    .mi-unsaved-alert {
        display: flex;
        align-items: flex-start;
        gap: .65rem;
        padding: .75rem 1rem;
        border-radius: .5rem;
        background: #fefce8;
        border: 1px solid #fde047;
        color: #713f12;
        font-size: .8125rem;
        margin-bottom: 1rem;
    }

    .mi-unsaved-alert svg {
        flex-shrink: 0;
        width: 1.1rem;
        height: 1.1rem;
        margin-top: .05rem;
    }

    .mi-qty-wrap {
        display: flex;
        align-items: center;
        gap: .4rem;
    }

    .mi-qty-input {
        width: 6.5rem;
        border-radius: .375rem;
        border: 1px solid var(--gray-300, #d1d5db);
        background: #fff;
        color: var(--gray-900, #111827);
        font-size: .875rem;
        padding: .3rem .5rem;
        outline: none;
        font-family: inherit;
        box-sizing: border-box;
    }

    .mi-qty-input:focus {
        border-color: var(--primary-500, #6366f1);
        box-shadow: 0 0 0 2px var(--primary-200, #c7d2fe);
    }

    .mi-actions {
        display: flex;
        align-items: center;
        gap: .35rem;
    }

    .mi-btn-save {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .25rem .6rem;
        border-radius: .375rem;
        background: var(--primary-600, #4f46e5);
        color: #fff;
        font-size: .75rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity .15s;
    }

    .mi-btn-save:hover:not(:disabled) {
        opacity: .85;
    }

    .mi-btn-save:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    .mi-btn-remove {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        padding: .25rem .5rem;
        border-radius: .375rem;
        background: transparent;
        color: #dc2626;
        font-size: .75rem;
        font-weight: 500;
        border: 1px solid #fca5a5;
        cursor: pointer;
        white-space: nowrap;
        transition: background .15s;
    }

    .mi-btn-remove:hover:not(:disabled) {
        background: #fee2e2;
    }

    .mi-btn-remove:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    .mi-btn-restore {
        display: inline-flex;
        align-items: center;
        gap: .2rem;
        padding: .25rem .55rem;
        border-radius: .375rem;
        background: transparent;
        color: #16a34a;
        font-size: .75rem;
        font-weight: 500;
        border: 1px solid #86efac;
        cursor: pointer;
        white-space: nowrap;
        transition: background .15s;
    }

    .mi-btn-restore:hover:not(:disabled) {
        background: #dcfce7;
    }

    .mi-btn-restore:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    .mi-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 2.5rem 1rem;
        color: var(--gray-400, #9ca3af);
        font-size: .875rem;
    }

    .mi-empty svg {
        width: 2rem;
        height: 2rem;
        margin-bottom: .5rem;
    }

    .mi-badge-readonly {
        display: inline-flex;
        align-items: center;
        gap: .3rem;
        padding: .2rem .6rem;
        border-radius: 9999px;
        background: #fef3c7;
        color: #92400e;
        font-size: .75rem;
        font-weight: 500;
        margin-bottom: 1rem;
    }

    /* ── Entrega parcial ────────────────────────────────── */
    .mi-entrega-wrap {
        margin-top: .5rem;
        padding: .45rem .65rem;
        border-radius: .375rem;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        display: flex;
        align-items: center;
        gap: .5rem;
        flex-wrap: wrap;
    }

    .mi-entrega-label {
        font-size: .75rem;
        color: #15803d;
        font-weight: 500;
        white-space: nowrap;
    }

    .mi-entrega-input {
        width: 6.5rem;
        border-radius: .375rem;
        border: 1px solid #86efac;
        background: #fff;
        color: var(--gray-900, #111827);
        font-size: .875rem;
        padding: .3rem .5rem;
        outline: none;
        font-family: inherit;
        box-sizing: border-box;
    }

    .mi-entrega-input:focus {
        border-color: #16a34a;
        box-shadow: 0 0 0 2px #bbf7d0;
    }

    .mi-btn-entregar {
        display: inline-flex;
        align-items: center;
        gap: .25rem;
        padding: .25rem .65rem;
        border-radius: .375rem;
        background: #16a34a;
        color: #fff;
        font-size: .75rem;
        font-weight: 500;
        border: none;
        cursor: pointer;
        white-space: nowrap;
        transition: opacity .15s;
    }

    .mi-btn-entregar:hover:not(:disabled) {
        opacity: .85;
    }

    .mi-btn-entregar:disabled {
        opacity: .4;
        cursor: not-allowed;
    }

    /* badges status item */
    .mi-item-status {
        display: inline-flex;
        align-items: center;
        padding: .15rem .5rem;
        border-radius: 9999px;
        font-size: .7rem;
        font-weight: 600;
        white-space: nowrap;
        margin-left: .4rem;
        vertical-align: middle;
    }

    .mi-item-status-pendente {
        background: #fef3c7;
        color: #92400e;
    }

    .mi-item-status-parcial {
        background: #dbeafe;
        color: #1d4ed8;
    }

    .mi-item-status-completo {
        background: #dcfce7;
        color: #15803d;
    }

    .mi-col-entregue {
        font-weight: 600;
        color: #16a34a;
    }

    .mi-col-pendente {
        font-weight: 600;
        color: #d97706;
    }

    /* dark ──────────────────────────────────────────────── */
    .dark .mi-table thead tr {
        background: #1e293b;
        color: #94a3b8;
    }

    .dark .mi-table tbody tr {
        border-color: #334155;
    }

    .dark .mi-table tbody tr:hover {
        background: #1e293b;
    }

    .dark .mi-item-nome {
        color: #f1f5f9;
    }

    .dark .mi-secondary,
    .dark .mi-item-unit {
        color: #64748b;
    }

    .dark .mi-qty-input,
    .dark .mi-entrega-input {
        background: #0f172a;
        border-color: #334155;
        color: #f1f5f9;
    }

    .dark .mi-badge-readonly {
        background: #451a03;
        color: #fbbf24;
    }

    .dark .mi-qty-label {
        background: #1e293b;
        color: #94a3b8;
    }

    .dark .mi-badge-removed {
        background: #450a0a;
        color: #fca5a5;
    }

    .dark .mi-badge-pending {
        background: #422006;
        color: #fcd34d;
    }

    .dark .mi-unsaved-alert {
        background: #1c1a05;
        border-color: #854d0e;
        color: #fcd34d;
    }

    .dark .mi-entrega-wrap {
        background: #052e16;
        border-color: #166534;
    }

    .dark .mi-entrega-label {
        color: #4ade80;
    }

    .dark .mi-item-status-pendente {
        background: #451a03;
        color: #fcd34d;
    }

    .dark .mi-item-status-parcial {
        background: #1e3a5f;
        color: #93c5fd;
    }

    .dark .mi-item-status-completo {
        background: #052e16;
        color: #4ade80;
    }

    .dark .mi-col-entregue {
        color: #4ade80;
    }

    .dark .mi-col-pendente {
        color: #fbbf24;
    }
</style>

@if($itens->isEmpty())
<div class="mi-empty">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z" />
    </svg>
    Nenhum item encontrado neste pedido.
</div>
@else
@if(! $editavel)
<div class="mi-badge-readonly">
    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:.875rem;height:.875rem">
        <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" />
    </svg>
    Pedido entregue — somente visualização
</div>
@endif

<div>
    <table class="mi-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Empresa / Contrato</th>
                <th class="right">Qtd. Pedida</th>
                <th class="right">Entregue</th>
                <th class="right">Pendente</th>
            </tr>
        </thead>
        <tbody>
            @foreach($itens as $entry)
            @php
            $qtdEntregue = $entry['quantidade_entregue'];
            $qtdPendente = $entry['quantidade_pendente'];
            $itemCompleto = $qtdPendente <= 0;
                $itemParcial=$qtdEntregue> 0 && ! $itemCompleto;
                @endphp

                <tr
                    x-data="{
                    entregando: false,
                    qtdEntrega: '',

                    get pendente() {
                        return {{ $qtdPendente }};
                    },

                    get entregaValida() {
                        const v = parseFloat(this.qtdEntrega);
                        return !isNaN(v) && v > 0 && v <= this.pendente;
                    },

                    registrarEntrega() {
                        const v = parseFloat(this.qtdEntrega);
                        if (!this.entregaValida) return;

                        this.entregando = true;

                        $wire.salvarEntregaParcial({{ $entry['pedido_item_id'] }}, v)
                            .then(() => { this.qtdEntrega = ''; })
                            .finally(() => this.entregando = false);
                    }
                }">

                    {{-- Item --}}
                    <td>
                        <span class="mi-item-nome">{{ $entry['item_nome'] }}</span>
                        <span class="mi-item-unit">({{ $entry['unidade'] }})</span>

                        @if($itemCompleto)
                        <span class="mi-item-status mi-item-status-completo">Completo</span>
                        @elseif($itemParcial)
                        <span class="mi-item-status mi-item-status-parcial">Parcial</span>
                        @else
                        <span class="mi-item-status mi-item-status-pendente">Pendente</span>
                        @endif

                        @if($editavel && ! $itemCompleto)
                        <div class="mi-entrega-wrap">
                            <span class="mi-entrega-label">Entregar agora:</span>
                            <input
                                type="number"
                                step="0.001"
                                min="0.001"
                                max="{{ $qtdPendente }}"
                                placeholder="0.000"
                                x-model="qtdEntrega"
                                class="mi-entrega-input" />

                            <button
                                class="mi-btn-entregar"
                                :disabled="!entregaValida || entregando"
                                @click="registrarEntrega()">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.75rem;height:.75rem">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span x-text="entregando ? 'Salvando...' : 'Registrar'"></span>
                            </button>
                        </div>
                        @endif
                    </td>

                    {{-- Empresa --}}
                    <td>
                        <div class="mi-item-nome" style="font-size:.8125rem">{{ $entry['empresa'] }}</div>
                        <div class="mi-secondary">{{ $entry['numero_contrato'] }}</div>
                    </td>
                    
                    {{-- Qtd Pedida --}}
                    <td class="right">
                        <span class="mi-qty-label">
                            {{ number_format($entry['quantidade'], 3, ',', '.') }}
                        </span>
                    </td>

                    {{-- Entregue --}}
                    <td class="right mi-col-entregue">
                        {{ number_format($qtdEntregue, 3, ',', '.') }}
                    </td>

                    {{-- Pendente --}}
                    <td class="right mi-col-pendente">
                        {{ $itemCompleto ? '—' : number_format($qtdPendente, 3, ',', '.') }}
                    </td>
                </tr>
                @endforeach
        </tbody>
    </table>
</div>
@endif