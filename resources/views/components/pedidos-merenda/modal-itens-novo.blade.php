@php
    $itensPedido = $pedido->itens;
    $empresasPedido = $itensPedido
        ->map(fn ($item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
        ->filter()
        ->unique()
        ->values();
    $quantidadePedida = (float) $itensPedido->sum('quantidade_pedida');
    $quantidadeEntregue = (float) $itensPedido->sum('quantidade_entregue');
    $progresso = $quantidadePedida > 0 ? min(100, ($quantidadeEntregue / $quantidadePedida) * 100) : 0;
    $podeEditar = in_array($pedido->status, [
        \App\Models\Enums\StatusPedidoMerenda::Aguardando,
        \App\Models\Enums\StatusPedidoMerenda::ParcialmenteEntregue,
    ], true);
@endphp

<style>
    .pmm-items{display:grid;gap:.85rem}.pmm-card{border-radius:1.15rem;background:#fff;border:1px solid rgba(148,163,184,.2);overflow:hidden}
    .pmm-top{padding:1rem 1rem .8rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.pmm-name{margin:0;font-size:1rem;color:#14213d;font-weight:800}
    .pmm-meta{margin-top:.3rem;color:#64748b;font-size:.83rem;line-height:1.45}.pmm-progress{margin-top:.85rem;height:.6rem;border-radius:999px;background:#e2e8f0;overflow:hidden}.pmm-progress>span{display:block;height:100%;border-radius:inherit;background:linear-gradient(135deg,#10b981,#0284c7)}
    .pmm-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem;padding:0 1rem 1rem}.pmm-stat{border-radius:.95rem;background:#f8fafc;padding:.8rem;border:1px solid rgba(148,163,184,.12)}.pmm-stat span{display:block;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.2rem}.pmm-stat strong{color:#14213d;font-size:.98rem}
    .pmm-actions{padding:0 1rem 1rem;display:grid;grid-template-columns:1.2fr 1fr;gap:.85rem}.pmm-box{border-radius:1rem;background:linear-gradient(180deg,#fff,#f8fafc);border:1px solid rgba(148,163,184,.18);padding:.9rem}.pmm-box h4{margin:0 0 .8rem;color:#14213d;font-size:.92rem;font-weight:800}
    .pmm-form{display:flex;align-items:flex-end;gap:.6rem;flex-wrap:wrap}.pmm-helper{margin-top:.45rem;color:#64748b;font-size:.77rem}
    @media (max-width:1100px){.pmm-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:920px){.pmm-actions{grid-template-columns:1fr}}@media (max-width:680px){.pmm-grid{grid-template-columns:1fr}.pmm-top{flex-direction:column;align-items:stretch}}
</style>

<div class="pm-modal-shell" x-data x-init="$el.querySelector('[data-modal]')?.focus()">
    <div class="pm-modal-bg" wire:click="fecharModalItens"></div>

    <div class="pm-modal" data-modal tabindex="-1">
        <div class="pm-modal-head">
            <div>
                <h3 class="pm-modal-title">Pedido #{{ $pedido->id }} · {{ $page->formatarStatus($pedido->status) }}</h3>
                <div class="pm-modal-text">Visual de conferencia com item, empresa, contrato, progresso de entrega e ajustes operacionais no mesmo lugar.</div>
            </div>
            <button type="button" wire:click="fecharModalItens" class="pm-btn pm-btn-light">Fechar</button>
        </div>

        <div class="pm-modal-body">
            <div class="pm-modal-overview">
                <div class="pm-mini"><span>Itens</span><strong>{{ $itensPedido->count() }}</strong></div>
                <div class="pm-mini"><span>Qtd. pedida</span><strong>{{ number_format($quantidadePedida, 3, ',', '.') }}</strong></div>
                <div class="pm-mini"><span>Qtd. entregue</span><strong>{{ number_format($quantidadeEntregue, 3, ',', '.') }}</strong></div>
                <div class="pm-mini"><span>Empresas</span><strong>{{ $empresasPedido->count() }}</strong></div>
            </div>

            <div class="pm-chip-list">
                <span class="pm-chip">Criado por {{ $pedido->criado_por ?: 'Nao informado' }}</span>
                <span class="pm-chip">{{ $pedido->created_at?->format('d/m/Y H:i') }}</span>
                <span class="pm-chip">Progresso {{ number_format($progresso, 1, ',', '.') }}%</span>
                @foreach($empresasPedido as $empresa)
                    <span class="pm-chip">{{ $empresa }}</span>
                @endforeach
            </div>

            @if(filled($pedido->observacoes))
                <div class="pmo-notes">{{ $pedido->observacoes }}</div>
            @endif

            <div class="pmm-items">
                @foreach($itensPedido as $pedidoItem)
                    @php
                        $contratoItem = $pedidoItem->contratoItem;
                        $quantidadeItemPedida = (float) $pedidoItem->quantidade_pedida;
                        $quantidadeItemEntregue = (float) $pedidoItem->quantidade_entregue;
                        $quantidadeItemPendente = (float) $pedidoItem->quantidade_pendente;
                        $progressoItem = $quantidadeItemPedida > 0 ? min(100, ($quantidadeItemEntregue / $quantidadeItemPedida) * 100) : 0;
                        $statusItem = $quantidadeItemPendente <= 0 ? 'Completo' : ($quantidadeItemEntregue > 0 ? 'Parcial' : 'Pendente');
                        $statusClasse = $quantidadeItemPendente <= 0 ? 'pm-status-done' : ($quantidadeItemEntregue > 0 ? 'pm-status-partial' : 'pm-status-waiting');
                    @endphp

                    <article class="pmm-card">
                        <div class="pmm-top">
                            <div style="flex:1;">
                                <h4 class="pmm-name">{{ $contratoItem?->item?->nome ?: 'Item nao encontrado' }}</h4>
                                <div class="pmm-meta">
                                    {{ $contratoItem?->contrato?->empresaContratada?->nome ?: 'Empresa nao encontrada' }}
                                    · Contrato {{ $contratoItem?->contrato?->numero_contrato ?: '-' }}
                                    · {{ $contratoItem?->item?->unidade_medida?->value ?: '-' }}
                                </div>
                                <div class="pmm-progress"><span style="width: {{ number_format($progressoItem, 2, '.', '') }}%;"></span></div>
                            </div>
                            <span class="pmo-badge {{ $statusClasse }}">{{ $statusItem }}</span>
                        </div>

                        <div class="pmm-grid">
                            <div class="pmm-stat"><span>Qtd. pedida</span><strong>{{ number_format($quantidadeItemPedida, 3, ',', '.') }}</strong></div>
                            <div class="pmm-stat"><span>Qtd. entregue</span><strong>{{ number_format($quantidadeItemEntregue, 3, ',', '.') }}</strong></div>
                            <div class="pmm-stat"><span>Pendente</span><strong>{{ number_format($quantidadeItemPendente, 3, ',', '.') }}</strong></div>
                            <div class="pmm-stat"><span>Saldo no contrato</span><strong>{{ number_format((float) $contratoItem?->saldo_disponivel, 3, ',', '.') }}</strong></div>
                        </div>

                        @if($podeEditar)
                            <div class="pmm-actions">
                                <div class="pmm-box" x-data="{ quantidade: '{{ number_format($quantidadeItemPedida, 3, '.', '') }}', salvando: false }">
                                    <h4>Ajustar quantidade pedida</h4>
                                    <div class="pmm-form">
                                        <div style="flex:1;min-width:12rem;">
                                            <label class="pm-label">Nova quantidade</label>
                                            <input type="number" step="0.001" min="0" x-model="quantidade" class="pm-input">
                                        </div>
                                        <button
                                            type="button"
                                            class="pm-btn pm-btn-primary"
                                            :disabled="salvando"
                                            @click="
                                                salvando = true;
                                                $wire.salvarQuantidade({{ $pedidoItem->id }}, parseFloat(quantidade || 0))
                                                    .finally(() => salvando = false);
                                            "
                                        >
                                            <span x-text="salvando ? 'Salvando...' : 'Salvar quantidade'"></span>
                                        </button>
                                    </div>
                                    <div class="pmm-helper">O total nao pode ficar abaixo do ja entregue nem acima do saldo disponivel no contrato.</div>
                                </div>

                                <div class="pmm-box" x-data="{ entrega: '', registrando: false }">
                                    <h4>Registrar entrega parcial</h4>
                                    <div class="pmm-form">
                                        <div style="flex:1;min-width:12rem;">
                                            <label class="pm-label">Quantidade entregue agora</label>
                                            <input type="number" step="0.001" min="0.001" max="{{ number_format($quantidadeItemPendente, 3, '.', '') }}" x-model="entrega" class="pm-input">
                                        </div>
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
                                            <span x-text="registrando ? 'Registrando...' : 'Registrar entrega'"></span>
                                        </button>
                                    </div>
                                    <div class="pmm-helper">Registro direto no item com reflexo no estoque e no status geral do pedido.</div>
                                </div>
                            </div>
                        @endif
                    </article>
                @endforeach
            </div>
        </div>

        <div class="pm-modal-foot">
            <div class="pm-chip-list">
                <span class="pm-chip">{{ $itensPedido->count() }} item(ns)</span>
                <span class="pm-chip">{{ $empresasPedido->count() }} empresa(s)</span>
            </div>

            <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
                @if($pedido->status === \App\Models\Enums\StatusPedidoMerenda::Aguardando)
                    <a href="{{ route('pedidos-merenda.exportar-empenho', $pedido) }}" class="pm-btn pm-btn-light">Exportar empenho</a>
                @endif
                @if(in_array($pedido->status, [\App\Models\Enums\StatusPedidoMerenda::Aguardando, \App\Models\Enums\StatusPedidoMerenda::ParcialmenteEntregue], true))
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
