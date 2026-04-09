<x-filament-panels::page>
<div class="pnr">

    {{-- ─── HERO ─── --}}
    <header class="pnr-hero">
        <div class="pnr-hero__text">
            <span class="pnr-label">Merenda Escolar</span>
            <h1>Panorama dos Pedidos</h1>
            <p>Visão operacional com filtros, acompanhamento por status, exportação de empenho e controle de itens.</p>
        </div>
        <a href="{{ \App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource::getUrl('create') }}" class="pnr-btn pnr-btn--primary">
            + Novo pedido
        </a>
    </header>

    {{-- ─── CARDS DE RESUMO ─── --}}
    <div class="pnr-summary">
        @foreach ($this->resumoCards as $card)
            <div class="pnr-summary__card">
                <span>{{ $card['titulo'] }}</span>
                <strong>{{ $card['valor'] }}</strong>
                <small>{{ $card['descricao'] }}</small>
            </div>
        @endforeach
    </div>

    {{-- ─── FILTROS + ATALHOS ─── --}}
    <div class="pnr-row">

        <section class="pnr-box pnr-box--grow">
            <div class="pnr-box__head">
                <span class="pnr-label">Busca e filtros</span>
                <h2>Pedidos</h2>
            </div>

            <div class="pnr-filters">
                <label class="pnr-field pnr-field--wide">
                    <span>Buscar pedido, item, empresa ou contrato</span>
                    <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Ex.: arroz, pedido 12, contrato 45" />
                </label>
                <label class="pnr-field">
                    <span>Criado por</span>
                    <select wire:model.live="criadoPor">
                        <option value="">Todos</option>
                        @foreach ($this->criadoresDisponiveis as $criador)
                            <option value="{{ $criador }}">{{ $criador }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="pnr-field">
                    <span>Data inicial</span>
                    <input type="date" wire:model.live="dataInicio" />
                </label>
                <label class="pnr-field">
                    <span>Data final</span>
                    <input type="date" wire:model.live="dataFim" />
                </label>
                <label class="pnr-field">
                    <span>Por página</span>
                    <select wire:model.live="porPagina">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>

            <div class="pnr-status-row">
                @foreach (\App\Models\Enums\StatusPedidoMerenda::cases() as $status)
                    <label class="pnr-chip">
                        <input type="checkbox" wire:model.live="statusSelecionados" value="{{ $status->value }}">
                        <span>{{ $status->label() }}</span>
                    </label>
                @endforeach
                <label class="pnr-chip">
                    <input type="checkbox" wire:model.live="mostrarCancelados">
                    <span>Mostrar cancelados</span>
                </label>
                <button type="button" wire:click="limparFiltros" class="pnr-link">Limpar filtros</button>
            </div>
        </section>

        <section class="pnr-box pnr-shortcuts">
            <div class="pnr-box__head">
                <span class="pnr-label">Atalhos</span>
                <h2>Navegação rápida</h2>
            </div>
            <div class="pnr-shortcuts__grid">
                <button type="button" class="pnr-shortcut" x-data
                    @click="document.querySelector('[data-secao=\'aguardando\']')?.scrollIntoView({behavior:'smooth',block:'start'})">
                    <strong>Fila aguardando</strong>
                    <span>Pedidos prontos para empenho</span>
                </button>
                <button type="button" class="pnr-shortcut" x-data
                    @click="document.querySelector('[data-secao=\'parcial\']')?.scrollIntoView({behavior:'smooth',block:'start'})">
                    <strong>Entregas parciais</strong>
                    <span>Complementos pendentes</span>
                </button>
                <button type="button" class="pnr-shortcut" x-data
                    @click="document.querySelector('[data-secao=\'finalizado\']')?.scrollIntoView({behavior:'smooth',block:'start'})">
                    <strong>Histórico</strong>
                    <span>Entregues e cancelados</span>
                </button>
            </div>
        </section>

    </div>

    {{-- ─── TABELA: AGUARDANDO ─── --}}
    <section class="pnr-box" data-secao="aguardando">
        <div class="pnr-box__head">
            <div>
                <span class="pnr-label">Fila principal</span>
                <h2>Pedidos aguardando</h2>
            </div>
        </div>
        @include('components.pedidos-merenda.table-list', [
            'pedidos'      => $this->pedidosAguardando,
            'paginacao'    => $this->paginacaoAguardando,
            'secao'        => 'aguardando',
            'page'         => $this,
            'mostrarEmpenho' => true,
        ])
    </section>

    {{-- ─── TABELA: PARCIAIS ─── --}}
    <section class="pnr-box" data-secao="parcial">
        <div class="pnr-box__head">
            <div>
                <span class="pnr-label">Em andamento</span>
                <h2>Pedidos parcialmente entregues</h2>
            </div>
        </div>
        @include('components.pedidos-merenda.table-list', [
            'pedidos'      => $this->pedidosParciais,
            'paginacao'    => $this->paginacaoParcial,
            'secao'        => 'parcial',
            'page'         => $this,
            'mostrarEmpenho' => false,
        ])
    </section>

    {{-- ─── TABELA: FINALIZADOS ─── --}}
    <section class="pnr-box" data-secao="finalizado">
        <div class="pnr-box__head">
            <div>
                <span class="pnr-label">Histórico</span>
                <h2>Pedidos entregues e cancelados</h2>
            </div>
        </div>
        @include('components.pedidos-merenda.table-list', [
            'pedidos'      => $this->pedidosFinalizados,
            'paginacao'    => $this->paginacaoFinalizado,
            'secao'        => 'finalizado',
            'page'         => $this,
            'mostrarEmpenho' => false,
        ])
    </section>

</div>

{{-- ─── MODAL ─── --}}
@if ($modalItensAberto && $this->pedidoSelecionado)
    @include('components.pedidos-merenda.modal-itens-novo', [
        'pedido' => $this->pedidoSelecionado,
        'page'   => $this,
    ])
@endif

<style>
/* ── Variáveis ── */
:root {
    --pnr-accent:   #0f766e;
    --pnr-accent-h: #0d5f58;
    --pnr-ink:      #0f172a;
    --pnr-muted:    #64748b;
    --pnr-subtle:   #475569;
    --pnr-surface:  #f8fafc;
    --pnr-line:     #e2e8f0;
    --pnr-white:    #ffffff;
    --pnr-r:        .6rem;
    --pnr-r-lg:     .85rem;
}

/* ── Layout raiz ── */
.pnr {
    display: flex;
    flex-direction: column;
    gap: .75rem;
}

/* ── Hero ── */
.pnr-hero {
    display: flex;
    align-items: flex-end;
    justify-content: space-between;
    gap: 1rem;
    padding: .25rem 0 .5rem;
    flex-wrap: wrap;
}
.pnr-hero h1 {
    margin: .2rem 0 .3rem;
    font-size: 1.25rem;
    font-weight: 600;
    color: var(--pnr-ink);
    line-height: 1.2;
}
.pnr-hero p {
    margin: 0;
    font-size: .83rem;
    color: var(--pnr-muted);
    line-height: 1.5;
    max-width: 52rem;
}

/* ── Label/kicker ── */
.pnr-label {
    display: block;
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .07em;
    color: var(--pnr-muted);
    margin-bottom: .15rem;
}

/* ── Botão primário ── */
.pnr-btn--primary {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .5rem 1rem;
    background: var(--pnr-accent);
    color: #fff;
    border: none;
    border-radius: var(--pnr-r);
    font-size: .83rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s;
}
.pnr-btn--primary:hover { background: var(--pnr-accent-h); }

/* ── Cards de resumo ── */
.pnr-summary {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: .6rem;
}
.pnr-summary__card {
    background: var(--pnr-white);
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r-lg);
    padding: .8rem 1rem;
}
.pnr-summary__card span {
    display: block;
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--pnr-muted);
}
.pnr-summary__card strong {
    display: block;
    font-size: 1.45rem;
    font-weight: 600;
    color: var(--pnr-ink);
    margin: .3rem 0 .2rem;
    line-height: 1;
}
.pnr-summary__card small {
    display: block;
    font-size: .75rem;
    color: var(--pnr-muted);
    line-height: 1.4;
}

/* ── Row dois painéis ── */
.pnr-row {
    display: grid;
    grid-template-columns: minmax(0,1fr) 280px;
    gap: .75rem;
    align-items: start;
}

/* ── Box genérico ── */
.pnr-box {
    background: var(--pnr-white);
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r-lg);
    padding: .9rem 1rem;
}
.pnr-box--grow { flex: 1; }
.pnr-box__head {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    margin-bottom: .75rem;
}
.pnr-box__head h2 {
    margin: 0;
    font-size: .95rem;
    font-weight: 600;
    color: var(--pnr-ink);
}

/* ── Filtros ── */
.pnr-filters {
    display: grid;
    grid-template-columns: minmax(0,2fr) repeat(4, minmax(0,1fr));
    gap: .55rem;
}
.pnr-field {
    display: flex;
    flex-direction: column;
    gap: .25rem;
}
.pnr-field--wide { grid-column: span 1; }
.pnr-field span {
    font-size: .72rem;
    font-weight: 600;
    color: var(--pnr-subtle);
}
.pnr-field input,
.pnr-field select {
    width: 100%;
    box-sizing: border-box;
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r);
    background: var(--pnr-surface);
    padding: .5rem .7rem;
    font-size: .83rem;
    color: var(--pnr-ink);
    outline: none;
    transition: border-color .15s, box-shadow .15s;
}
.pnr-field input:focus,
.pnr-field select:focus {
    border-color: var(--pnr-accent);
    box-shadow: 0 0 0 2px rgba(15,118,110,.1);
    background: var(--pnr-white);
}

/* ── Status chips ── */
.pnr-status-row {
    display: flex;
    flex-wrap: wrap;
    gap: .45rem;
    align-items: center;
    margin-top: .65rem;
}
.pnr-chip {
    display: inline-flex;
    align-items: center;
    gap: .35rem;
    padding: .35rem .6rem;
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r);
    background: var(--pnr-surface);
    color: var(--pnr-subtle);
    font-size: .78rem;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.pnr-chip:hover { border-color: #cbd5e1; background: #f1f5f9; }
.pnr-chip input { margin: 0; }
.pnr-link {
    border: none;
    background: transparent;
    color: var(--pnr-accent);
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    padding: 0;
    text-decoration: none;
}
.pnr-link:hover { text-decoration: underline; }

/* ── Atalhos ── */
.pnr-shortcuts__grid {
    display: flex;
    flex-direction: column;
    gap: .45rem;
}
.pnr-shortcut {
    display: flex;
    flex-direction: column;
    gap: .2rem;
    padding: .65rem .8rem;
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r);
    background: var(--pnr-surface);
    text-align: left;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.pnr-shortcut:hover { border-color: var(--pnr-accent); background: #f0fdf9; }
.pnr-shortcut strong {
    font-size: .83rem;
    font-weight: 600;
    color: var(--pnr-ink);
}
.pnr-shortcut span {
    font-size: .75rem;
    color: var(--pnr-muted);
    line-height: 1.3;
}

/* ── Tabela ── */
.pnr-table-wrap { overflow-x: auto; }
.pnr-table {
    width: 100%;
    border-collapse: collapse;
}
.pnr-table thead tr { background: var(--pnr-surface); }
.pnr-table th,
.pnr-table td {
    padding: .6rem .75rem;
    border-top: .5px solid var(--pnr-line);
    text-align: left;
    vertical-align: middle;
}
.pnr-table th {
    font-size: .68rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: .06em;
    color: var(--pnr-muted);
}
.pnr-table td strong {
    display: block;
    font-size: .83rem;
    font-weight: 600;
    color: var(--pnr-ink);
}
.pnr-table td small {
    display: block;
    font-size: .75rem;
    color: var(--pnr-muted);
    margin-top: .1rem;
}

/* ── Ações por linha ── */
.pnr-row-actions {
    display: flex;
    gap: .35rem;
    justify-content: flex-end;
    flex-wrap: wrap;
}
.pnr-row-actions a,
.pnr-row-actions button {
    padding: .35rem .6rem;
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r);
    background: var(--pnr-white);
    color: var(--pnr-ink);
    font-size: .75rem;
    font-weight: 600;
    text-decoration: none;
    cursor: pointer;
    transition: border-color .15s, background .15s;
}
.pnr-row-actions a:hover,
.pnr-row-actions button:hover { background: var(--pnr-surface); border-color: #cbd5e1; }
.pnr-row-actions .danger { color: #b91c1c; border-color: #fecaca; }
.pnr-row-actions .danger:hover { background: #fef2f2; }

/* ── Vazio e paginação ── */
.pnr-empty {
    text-align: center;
    color: var(--pnr-muted);
    padding: 1.75rem 1rem;
    font-size: .85rem;
}
.pnr-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    padding-top: .75rem;
    border-top: .5px solid var(--pnr-line);
    margin-top: .5rem;
}
.pnr-pagination span { color: var(--pnr-muted); font-size: .78rem; }
.pnr-pagination-controls { display: flex; gap: .35rem; }
.pnr-pagination-controls button {
    border: .5px solid var(--pnr-line);
    background: var(--pnr-white);
    border-radius: var(--pnr-r);
    padding: .35rem .7rem;
    font-size: .78rem;
    font-weight: 600;
    cursor: pointer;
    transition: background .15s;
}
.pnr-pagination-controls button:hover { background: var(--pnr-surface); }

/* ── Badges ── */
.pnr-badge {
    display: inline-flex;
    align-items: center;
    padding: .22rem .55rem;
    border-radius: 999px;
    font-size: .68rem;
    font-weight: 700;
    white-space: nowrap;
}
.pnr-badge--warning  { background: #fef3c7; color: #92400e; }
.pnr-badge--info     { background: #dbeafe; color: #1d4ed8; }
.pnr-badge--success  { background: #dcfce7; color: #166534; }
.pnr-badge--danger   { background: #fee2e2; color: #991b1b; }
.pnr-badge--neutral  { background: #e2e8f0; color: #334155; }

/* ── Modal ── */
.pnr-modal-shell {
    position: fixed;
    inset: 0;
    z-index: 60;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: .75rem;
}
.pnr-modal-bg {
    position: absolute;
    inset: 0;
    background: rgba(15,23,42,.45);
}
.pnr-modal {
    position: relative;
    z-index: 1;
    width: min(1080px, 100%);
    max-height: calc(100vh - 1.5rem);
    overflow-y: auto;
    background: var(--pnr-white);
    border-radius: var(--pnr-r-lg);
    box-shadow: 0 16px 48px rgba(15,23,42,.16);
}
.pnr-modal-head {
    padding: .85rem 1rem;
    border-bottom: .5px solid var(--pnr-line);
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: .75rem;
    position: sticky;
    top: 0;
    background: var(--pnr-white);
    z-index: 1;
}
.pnr-modal-head h3 {
    margin: 0;
    font-size: .95rem;
    font-weight: 600;
    color: var(--pnr-ink);
}
.pnr-modal-head p {
    margin: .2rem 0 0;
    font-size: .8rem;
    color: var(--pnr-muted);
    line-height: 1.4;
}
.pnr-modal-body {
    padding: .85rem 1rem;
    display: grid;
    gap: .75rem;
}
.pnr-modal-overview {
    display: grid;
    grid-template-columns: repeat(4, minmax(0,1fr));
    gap: .55rem;
}
.pnr-mini {
    border: .5px solid var(--pnr-line);
    border-radius: var(--pnr-r);
    background: var(--pnr-surface);
    padding: .6rem .75rem;
}
.pnr-mini span {
    display: block;
    font-size: .68rem;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: .05em;
    color: var(--pnr-muted);
}
.pnr-mini strong {
    display: block;
    margin-top: .2rem;
    font-size: .85rem;
    font-weight: 600;
    color: var(--pnr-ink);
}
.pnr-chip-list { display: flex; gap: .35rem; flex-wrap: wrap; }
.pnr-chip-sm {
    display: inline-flex;
    align-items: center;
    padding: .22rem .55rem;
    border-radius: 999px;
    background: var(--pnr-surface);
    border: .5px solid var(--pnr-line);
    color: var(--pnr-subtle);
    font-size: .72rem;
    font-weight: 600;
}
.pnr-modal-foot {
    padding: .85rem 1rem;
    border-top: .5px solid var(--pnr-line);
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: .75rem;
    flex-wrap: wrap;
    position: sticky;
    bottom: 0;
    background: var(--pnr-white);
}

/* ── Responsivo ── */
@media (max-width: 1100px) {
    .pnr-summary { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .pnr-filters  { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .pnr-modal-overview { grid-template-columns: repeat(2, minmax(0,1fr)); }
}
@media (max-width: 860px) {
    .pnr-row { grid-template-columns: 1fr; }
    .pnr-shortcuts__grid { flex-direction: row; flex-wrap: wrap; }
    .pnr-shortcut { flex: 1 1 140px; }
}
@media (max-width: 600px) {
    .pnr-summary { grid-template-columns: repeat(2, minmax(0,1fr)); }
    .pnr-filters  { grid-template-columns: 1fr 1fr; }
    .pnr-modal-overview { grid-template-columns: 1fr 1fr; }
    .pnr-pagination { flex-direction: column; align-items: stretch; }
    .pnr-modal-head,
    .pnr-modal-foot { flex-direction: column; align-items: stretch; }
    .pnr-row-actions { justify-content: flex-start; }
    .pnr-hero { flex-direction: column; align-items: flex-start; }
}
@media (max-width: 440px) {
    .pnr-summary { grid-template-columns: 1fr; }
    .pnr-filters  { grid-template-columns: 1fr; }
}
</style>
</x-filament-panels::page>