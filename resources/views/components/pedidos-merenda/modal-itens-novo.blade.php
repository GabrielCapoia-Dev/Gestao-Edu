@php
    $itensPedido = $pedido->itens;
    $empresasPedido = $itensPedido
        ->map(fn ($item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
        ->filter()
        ->unique()
        ->values();
    $quantidadePedida = (float) $itensPedido->sum('quantidade_pedida');
    $quantidadeEntregue = (float) $itensPedido->sum('quantidade_entregue');
    $quantidadePendente = max(0, $quantidadePedida - $quantidadeEntregue);
    $podeEditar = in_array($pedido->status, [
        \App\Models\Enums\StatusPedidoMerenda::Aguardando,
        \App\Models\Enums\StatusPedidoMerenda::ParcialmenteEntregue,
    ], true);
@endphp

<style>
    .pm-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:2.35rem;padding:.55rem .9rem;border:1px solid var(--gray-200);border-radius:var(--radius-lg);background:#fff;color:var(--gray-700);font-size:var(--text-sm);line-height:var(--text-sm--line-height);font-weight:var(--font-weight-medium);text-decoration:none;cursor:pointer;transition:background-color .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease}
    .pm-btn-primary{border-color:var(--primary-600);background:var(--primary-600);color:#fff}
    .pm-btn-success{border-color:var(--success-600);background:var(--success-600);color:#fff}
    .pm-btn-danger{border-color:var(--danger-600);background:var(--danger-600);color:#fff}
    .pm-btn-light{background:#fff}
    .pm-input{width:100%;min-height:2.5rem;padding:.65rem .8rem;border:1px solid var(--gray-300);border-radius:var(--radius-lg);background:#fff;color:var(--gray-950);font-size:var(--text-sm);line-height:1.5}
    .pm-input:focus{outline:none;border-color:var(--primary-400);box-shadow:0 0 0 3px color-mix(in oklab,var(--primary-200) 70%,transparent)}
    .pmt-summary{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr))}
    .pmt-summary-card{border:1px solid var(--gray-200);border-radius:var(--radius-xl);background:#fff;padding:.8rem}
    .pmt-summary-card span{display:block;color:var(--gray-500);font-size:var(--text-xs);line-height:var(--text-xs--line-height);text-transform:uppercase;letter-spacing:.05em}
    .pmt-summary-card strong{display:block;margin-top:.2rem;color:var(--gray-950);font-size:var(--text-base);font-weight:var(--font-weight-semibold)}
    .pmt-meta{display:flex;gap:.5rem;flex-wrap:wrap}
    .pmt-notes{padding:.85rem .95rem;border:1px solid var(--gray-200);border-radius:var(--radius-xl);background:var(--gray-50);color:var(--gray-700);font-size:var(--text-sm);line-height:1.55}
    .pmt-table-wrap{overflow:auto;border:1px solid var(--gray-200);border-radius:var(--radius-xl);background:#fff}
    .pmt-table{width:100%;min-width:58rem;border-collapse:separate;border-spacing:0}
    .pmt-table th,.pmt-table td{padding:.85rem .95rem;text-align:left;vertical-align:top;border-bottom:1px solid var(--gray-200);font-size:var(--text-sm);line-height:1.5}
    .pmt-table th{position:sticky;top:0;z-index:1;background:var(--gray-50);color:var(--gray-600);text-transform:uppercase;letter-spacing:.06em;font-size:var(--text-xs);line-height:var(--text-xs--line-height);font-weight:var(--font-weight-semibold)}
    .pmt-table tbody tr:last-child td{border-bottom:0}
    .pmt-table td strong{display:block;color:var(--gray-950);font-weight:var(--font-weight-semibold)}
    .pmt-table td small{display:block;margin-top:.1rem;color:var(--gray-500)}
    .pmt-inline{display:flex;align-items:center;gap:.5rem;min-width:15rem}
    .pmt-inline .pm-input{min-width:7rem}
    .pmt-helper{display:block;margin-top:.2rem;color:var(--gray-500);font-size:var(--text-xs);line-height:1.45}
    .pmt-muted{color:var(--gray-400)}
    @media (hover:hover){
        .pm-btn:hover{border-color:var(--gray-300);background:var(--gray-50);color:var(--gray-950)}
        .pm-btn-primary:hover{border-color:var(--primary-700);background:var(--primary-700);color:#fff}
        .pm-btn-success:hover{border-color:var(--success-700);background:var(--success-700);color:#fff}
        .pm-btn-danger:hover{border-color:var(--danger-700);background:var(--danger-700);color:#fff}
    }
    @media (max-width:64rem){
        .pmt-summary{grid-template-columns:repeat(2,minmax(0,1fr))}
    }
    @media (max-width:48rem){
        .pmt-summary{grid-template-columns:1fr}
        .pmt-inline{flex-direction:column;align-items:stretch;min-width:11rem}
        .pmt-table{min-width:50rem}
    }
    :root.dark .pmt-summary-card,:root.dark .pmt-table-wrap{border-color:var(--gray-800);background:var(--gray-900)}
    :root.dark .pmt-summary-card span,:root.dark .pmt-table td small,:root.dark .pmt-helper,:root.dark .pmt-notes{color:var(--gray-400)}
    :root.dark .pmt-summary-card strong,:root.dark .pmt-table td strong{color:#fff}
    :root.dark .pmt-notes,:root.dark .pmt-table th{border-color:var(--gray-800);background:var(--gray-900)}
    :root.dark .pm-input{border-color:var(--gray-800);background:var(--gray-900);color:var(--gray-100)}
</style>

<div class="pm-modal-shell" x-data x-init="$el.querySelector('[data-modal]')?.focus()">
    <div class="pm-modal-bg" wire:click="fecharModalItens"></div>

    <div class="pm-modal" data-modal tabindex="-1">
        <div class="pm-modal-head">
            <div>
                <h3 class="pm-modal-title">Pedido #{{ $pedido->id }} · {{ $page->formatarStatus($pedido->status) }}</h3>
                <div class="pm-modal-text">Conferencia dos itens do pedido em formato de tabela, com foco no registro de entrega parcial.</div>
            </div>

            <button type="button" wire:click="fecharModalItens" class="pm-btn pm-btn-light">Fechar</button>
        </div>

        <div class="pm-modal-body">
            <div class="pmt-summary">
                <div class="pmt-summary-card">
                    <span>Itens</span>
                    <strong>{{ $itensPedido->count() }}</strong>
                </div>
                <div class="pmt-summary-card">
                    <span>Qtd. pedida</span>
                    <strong>{{ number_format($quantidadePedida, 3, ',', '.') }}</strong>
                </div>
                <div class="pmt-summary-card">
                    <span>Qtd. entregue</span>
                    <strong>{{ number_format($quantidadeEntregue, 3, ',', '.') }}</strong>
                </div>
                <div class="pmt-summary-card">
                    <span>Pendente</span>
                    <strong>{{ number_format($quantidadePendente, 3, ',', '.') }}</strong>
                </div>
            </div>

            <div class="pm-chip-list pmt-meta">
                <span class="pm-chip">Criado por {{ $pedido->criado_por ?: 'Nao informado' }}</span>
                <span class="pm-chip">{{ $pedido->created_at?->format('d/m/Y H:i') }}</span>
                <span class="pm-chip">{{ $empresasPedido->count() }} empresa(s)</span>
            </div>

            @if (filled($pedido->observacoes))
                <div class="pmt-notes">{{ $pedido->observacoes }}</div>
            @endif

            <div class="pmt-table-wrap">
                <table class="pmt-table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Empresa / Contrato</th>
                            <th>Pedida</th>
                            <th>Entregue</th>
                            <th>Pendente</th>
                            <th>Saldo contrato</th>
                            <th>Entrega parcial</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($itensPedido as $pedidoItem)
                            @php
                                $contratoItem = $pedidoItem->contratoItem;
                                $quantidadeItemPendente = (float) $pedidoItem->quantidade_pendente;
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $contratoItem?->item?->nome ?: 'Item nao encontrado' }}</strong>
                                    <small>{{ $contratoItem?->item?->unidade_medida?->value ?: '-' }}</small>
                                </td>
                                <td>
                                    <strong>{{ $contratoItem?->contrato?->empresaContratada?->nome ?: 'Empresa nao encontrada' }}</strong>
                                    <small>Contrato {{ $contratoItem?->contrato?->numero_contrato ?: '-' }}</small>
                                </td>
                                <td>
                                    <strong>{{ number_format((float) $pedidoItem->quantidade_pedida, 3, ',', '.') }}</strong>
                                </td>
                                <td>
                                    <strong>{{ number_format((float) $pedidoItem->quantidade_entregue, 3, ',', '.') }}</strong>
                                </td>
                                <td>
                                    <strong>{{ number_format($quantidadeItemPendente, 3, ',', '.') }}</strong>
                                </td>
                                <td>
                                    <strong>{{ number_format((float) ($contratoItem?->saldo_disponivel ?? 0), 3, ',', '.') }}</strong>
                                </td>
                                <td>
                                    @if ($podeEditar && $quantidadeItemPendente > 0)
                                        <div x-data="{ entrega: '', registrando: false }">
                                            <div class="pmt-inline">
                                                <input
                                                    type="number"
                                                    step="0.001"
                                                    min="0.001"
                                                    max="{{ number_format($quantidadeItemPendente, 3, '.', '') }}"
                                                    x-model="entrega"
                                                    class="pm-input"
                                                    placeholder="0.000"
                                                >
                                                <button
                                                    type="button"
                                                    class="pm-btn pm-btn-success"
                                                    :disabled="registrando || !parseFloat(entrega || 0)"
                                                    @click="
                                                        registrando = true;
                                                        $wire.salvarEntregaParcial({{ $pedidoItem->id }}, parseFloat(entrega || 0))
                                                            .then(() => entrega = '')
                                                            .finally(() => registrando = false);
                                                    "
                                                >
                                                    <span x-text="registrando ? 'Salvando...' : 'Registrar'"></span>
                                                </button>
                                            </div>
                                            <span class="pmt-helper">Registra somente a entrega agora, sem alterar a quantidade pedida.</span>
                                        </div>
                                    @else
                                        <span class="pmt-muted">Sem acao</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="pm-modal-foot">
            <div class="pm-chip-list">
                @foreach ($empresasPedido as $empresa)
                    <span class="pm-chip">{{ $empresa }}</span>
                @endforeach
            </div>

            <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
                @if ($pedido->status === \App\Models\Enums\StatusPedidoMerenda::Aguardando)
                    <a href="{{ route('pedidos-merenda.exportar-empenho', $pedido) }}" class="pm-btn pm-btn-light">Exportar empenho</a>
                @endif

                @if (in_array($pedido->status, [\App\Models\Enums\StatusPedidoMerenda::Aguardando, \App\Models\Enums\StatusPedidoMerenda::ParcialmenteEntregue], true))
                    <button
                        type="button"
                        class="pm-btn pm-btn-danger"
                        wire:click="cancelarPedido({{ $pedido->id }})"
                        wire:confirm="Confirma o cancelamento deste pedido? O saldo pendente sera devolvido aos contratos."
                    >
                        Cancelar pedido
                    </button>
                @endif
            </div>
        </div>
    </div>
</div>
