@php
    $qtdPedida = (float) ($pedido->quantidade_total_pedida ?? $pedido->itens->sum('quantidade_pedida'));
    $qtdEntregue = (float) ($pedido->quantidade_total_entregue ?? $pedido->itens->sum('quantidade_entregue'));
    $empresas = $pedido->itens
        ->map(fn ($item) => $item->contratoItem?->contrato?->empresaContratada?->nome)
        ->filter()
        ->unique()
        ->values();
    $contratos = $pedido->itens
        ->map(fn ($item) => $item->contratoItem?->contrato?->numero_contrato)
        ->filter()
        ->unique()
        ->values();
@endphp

<style>
    .pmo-order{border-radius:1.1rem;background:rgba(255,255,255,.88);border:1px solid rgba(148,163,184,.2);padding:1rem;display:grid;gap:.9rem}
    .pmo-top{display:flex;align-items:flex-start;justify-content:space-between;gap:.8rem}.pmo-title{margin:0;font-size:1rem;color:#14213d;font-weight:800}.pmo-subtitle{margin-top:.3rem;color:#64748b;font-size:.83rem}
    .pmo-grid{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.7rem}.pmo-kpi{border-radius:.95rem;background:#f8fafc;border:1px solid rgba(148,163,184,.14);padding:.8rem}.pmo-kpi strong{display:block;font-size:1rem;color:#14213d}.pmo-kpi span{display:block;margin-top:.18rem;font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}
    .pmo-notes{padding:.85rem .95rem;border-radius:.9rem;background:rgba(241,245,249,.84);color:#475569;font-size:.88rem;line-height:1.5}.pmo-actions{display:flex;align-items:center;justify-content:space-between;gap:.8rem;flex-wrap:wrap}.pmo-chips{display:flex;gap:.45rem;flex-wrap:wrap}
    .pmo-chip{display:inline-flex;align-items:center;gap:.35rem;border-radius:999px;background:rgba(255,255,255,.78);border:1px solid rgba(148,163,184,.18);padding:.38rem .7rem;color:#475569;font-size:.76rem;font-weight:700}
    .pmo-badge{display:inline-flex;align-items:center;padding:.4rem .7rem;border-radius:999px;font-size:.76rem;font-weight:800;white-space:nowrap}
    @media (max-width:1100px){.pmo-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}@media (max-width:680px){.pmo-grid{grid-template-columns:1fr}.pmo-top,.pmo-actions{flex-direction:column;align-items:stretch}}
</style>

<article class="pmo-order">
    <div class="pmo-top">
        <div>
            <h3 class="pmo-title">Pedido #{{ $pedido->id }}</h3>
            <div class="pmo-subtitle">
                Criado por {{ $pedido->criado_por ?: 'Nao informado' }} em {{ $pedido->created_at?->format('d/m/Y H:i') }}
            </div>
        </div>

        <span class="pmo-badge {{ $page->badgeStatusClasse($pedido->status) }}">{{ $page->formatarStatus($pedido->status) }}</span>
    </div>

    <div class="pmo-grid">
        <div class="pmo-kpi">
            <strong>{{ $pedido->itens_count ?? $pedido->itens->count() }}</strong>
            <span>Itens</span>
        </div>
        <div class="pmo-kpi">
            <strong>{{ number_format($qtdPedida, 3, ',', '.') }}</strong>
            <span>Qtd. pedida</span>
        </div>
        <div class="pmo-kpi">
            <strong>{{ number_format($qtdEntregue, 3, ',', '.') }}</strong>
            <span>Qtd. entregue</span>
        </div>
        <div class="pmo-kpi">
            <strong>{{ $empresas->count() }}</strong>
            <span>Empresas</span>
        </div>
    </div>

    @if(filled($pedido->observacoes))
        <div class="pmo-notes">{{ $pedido->observacoes }}</div>
    @endif

    <div class="pmo-actions">
        <div class="pmo-chips">
            @foreach($contratos->take(3) as $contrato)
                <span class="pmo-chip">Contrato {{ $contrato }}</span>
            @endforeach
            @if($contratos->count() > 3)
                <span class="pmo-chip">+{{ $contratos->count() - 3 }} contrato(s)</span>
            @endif
            @foreach($empresas->take(2) as $empresa)
                <span class="pmo-chip">{{ $empresa }}</span>
            @endforeach
        </div>

        <div style="display:flex;gap:.65rem;flex-wrap:wrap;">
            <button type="button" wire:click="abrirModalItens({{ $pedido->id }})" class="pm-btn pm-btn-light">Ver itens</button>

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
                    Cancelar
                </button>
            @endif
        </div>
    </div>
</article>
