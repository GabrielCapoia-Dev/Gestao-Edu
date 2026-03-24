<style>
    /* ── modal-itens ── */
    .mi-table { width: 100%; border-collapse: collapse; font-size: .875rem; }
    .mi-table thead tr {
        background: var(--gray-50, #f9fafb);
        font-size: .7rem; letter-spacing: .05em;
        text-transform: uppercase;
        color: var(--gray-500, #6b7280);
        font-weight: 600;
    }
    .mi-table th, .mi-table td { padding: .65rem .875rem; text-align: left; vertical-align: middle; }
    .mi-table th.right, .mi-table td.right { text-align: right; }
    .mi-table tbody tr { border-top: 1px solid var(--gray-100, #f3f4f6); }
    .mi-table tbody tr:hover { background: var(--gray-50, #f9fafb); }

    .mi-item-nome  { font-weight: 500; color: var(--gray-900, #111827); }
    .mi-item-unit  { font-size: .75rem; color: var(--gray-400, #9ca3af); margin-left: .2rem; }
    .mi-secondary  { color: var(--gray-500, #6b7280); font-size: .8125rem; }

    /* saldo */
    .mi-saldo-disp { font-weight: 600; color: #16a34a; }
    .mi-saldo-diff-pos { font-size: .75rem; color: #16a34a; }
    .mi-saldo-diff-neg { font-size: .75rem; color: #dc2626; }
    .mi-saldo-diff-zero { font-size: .75rem; color: var(--gray-400, #9ca3af); }

    /* qtd solicitada (label) */
    .mi-qty-label {
        display: inline-flex; align-items: center;
        padding: .2rem .55rem; border-radius: .375rem;
        background: var(--gray-100, #f3f4f6);
        color: var(--gray-700, #374151);
        font-size: .8125rem; font-weight: 600;
        white-space: nowrap;
    }

    /* input inline de quantidade */
    .mi-qty-wrap { display: flex; align-items: center; gap: .4rem; }
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
    .mi-qty-input:focus { border-color: var(--primary-500, #6366f1); box-shadow: 0 0 0 2px var(--primary-200, #c7d2fe); }
    .mi-qty-readonly { font-weight: 600; color: var(--gray-900, #111827); }

    .mi-btn-save {
        display: inline-flex; align-items: center; gap: .25rem;
        padding: .25rem .6rem; border-radius: .375rem;
        background: var(--primary-600, #4f46e5); color: #fff;
        font-size: .75rem; font-weight: 500;
        border: none; cursor: pointer; white-space: nowrap;
        transition: opacity .15s;
    }
    .mi-btn-save:hover:not(:disabled) { opacity: .85; }
    .mi-btn-save:disabled { opacity: .4; cursor: not-allowed; }

    /* empty */
    .mi-empty {
        display: flex; flex-direction: column; align-items: center;
        padding: 2.5rem 1rem; color: var(--gray-400, #9ca3af); font-size: .875rem;
    }
    .mi-empty svg { width: 2rem; height: 2rem; margin-bottom: .5rem; }

    /* readonly badge */
    .mi-badge-readonly {
        display: inline-flex; align-items: center; gap: .3rem;
        padding: .2rem .6rem; border-radius: 9999px;
        background: #fef3c7; color: #92400e;
        font-size: .75rem; font-weight: 500;
        margin-bottom: 1rem;
    }

    /* dark */
    .dark .mi-table thead tr { background: #1e293b; color: #94a3b8; }
    .dark .mi-table tbody tr { border-color: #334155; }
    .dark .mi-table tbody tr:hover { background: #1e293b; }
    .dark .mi-item-nome, .dark .mi-qty-readonly { color: #f1f5f9; }
    .dark .mi-secondary { color: #64748b; }
    .dark .mi-item-unit { color: #64748b; }
    .dark .mi-qty-input { background: #0f172a; border-color: #334155; color: #f1f5f9; }
    .dark .mi-badge-readonly { background: #451a03; color: #fbbf24; }
    .dark .mi-qty-label { background: #1e293b; color: #94a3b8; }
</style>

@if($itens->isEmpty())
    <div class="mi-empty">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 0 1-2.247 2.118H6.622a2.25 2.25 0 0 1-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125Z"/>
        </svg>
        Nenhum item encontrado neste pedido.
    </div>
@else
    @if(! $editavel)
        <div class="mi-badge-readonly">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width:.875rem;height:.875rem">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z"/>
            </svg>
            Pedido entregue — somente visualização
        </div>
    @endif

    <table class="mi-table">
        <thead>
            <tr>
                <th>Item</th>
                <th>Empresa / Contrato</th>
                <th class="right">Saldo Disponível</th>
                <th class="right">Qtd. Solicitada</th>
                @if($editavel)
                    <th class="right">Nova Qtd.</th>
                    <th></th>
                @endif
            </tr>
        </thead>
        <tbody>
            @foreach($itens as $entry)
                {{--
                    Alpine state:
                      qty         → valor atual do input
                      originalQty → última quantidade confirmada (atualiza após cada save)
                                    usado para: habilitar botão e exibir diff no saldo
                      saving      → flag de loading
                --}}
                <tr x-data="{
                    qty: {{ $entry['quantidade'] }},
                    originalQty: {{ $entry['quantidade'] }},
                    saving: false,
                    get diff() { return this.qty - this.originalQty },
                    get saldoSeNaoExistisse() { return {{ $entry['saldo_com_pedido'] }} },
                    get saldoAposEdicao() { return this.saldoSeNaoExistisse - this.qty }
                }">
                    <td>
                        <span class="mi-item-nome">{{ $entry['item_nome'] }}</span>
                        <span class="mi-item-unit">({{ $entry['unidade'] }})</span>
                    </td>

                    <td>
                        <div class="mi-item-nome" style="font-size:.8125rem">{{ $entry['empresa'] }}</div>
                        <div class="mi-secondary">{{ $entry['numero_contrato'] }}</div>
                    </td>

                    {{-- Saldo: mostra o disponível APÓS a quantidade atual do input --}}
                    <td class="right">
                        <div class="mi-saldo-disp" x-text="saldoAposEdicao.toLocaleString('pt-BR', {minimumFractionDigits:3, maximumFractionDigits:3})"></div>
                        @if($editavel)
                            {{-- Diferença em relação ao original: quanto o saldo vai mudar --}}
                            <div
                                x-show="diff !== 0"
                                :class="diff > 0 ? 'mi-saldo-diff-neg' : 'mi-saldo-diff-pos'"
                                x-text="(diff > 0 ? '▼ ' : '▲ ') + Math.abs(diff).toLocaleString('pt-BR', {minimumFractionDigits:3, maximumFractionDigits:3})"
                            ></div>
                        @endif
                    </td>

                    {{-- Qtd. Solicitada: label com o valor salvo (originalQty) --}}
                    <td class="right">
                        <span class="mi-qty-label" x-text="originalQty.toLocaleString('pt-BR', {minimumFractionDigits:3, maximumFractionDigits:3})"></span>
                    </td>

                    @if($editavel)
                        <td class="right">
                            <div class="mi-qty-wrap" style="justify-content:flex-end">
                                <input
                                    type="number"
                                    step="0.001"
                                    min="0.001"
                                    max="{{ $entry['saldo_com_pedido'] }}"
                                    x-model.number="qty"
                                    class="mi-qty-input"
                                />
                            </div>
                        </td>

                        <td>
                            <button
                                class="mi-btn-save"
                                :disabled="saving || qty === originalQty || qty <= 0 || qty > saldoSeNaoExistisse"
                                @click="
                                    saving = true;
                                    $wire.salvarQuantidade({{ $entry['pedido_item_id'] }}, qty)
                                        .then(() => { originalQty = qty })
                                        .finally(() => saving = false)
                                "
                            >
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" style="width:.75rem;height:.75rem">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/>
                                </svg>
                                <span x-text="saving ? 'Salvando...' : 'Salvar'"></span>
                            </button>
                        </td>
                    @endif
                </tr>
            @endforeach
        </tbody>
    </table>
@endif