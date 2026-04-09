<x-filament-panels::page>
    <div class="pm-page">
        <section class="pm-panel pm-panel--full">
            <div class="pm-hero">
                <div>
                    <p class="pm-eyebrow">Merenda Escolar</p>
                    <h1>Panorama dos Pedidos de Merenda</h1>
                    <p>Visao operacional dos pedidos com filtros, acompanhamento por status, exportacao de empenho e controle direto dos itens.</p>
                </div>
            </div>

            <div class="pm-cards">
                @foreach ($this->resumoCards as $card)
                    <article class="pm-card">
                        <span>{{ $card['titulo'] }}</span>
                        <strong>{{ $card['valor'] }}</strong>
                        <small>{{ $card['descricao'] }}</small>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="pm-panel pm-panel--half">
            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Busca e filtros</p>
                    <h2>Pedidos</h2>
                </div>
            </div>

            <div class="pm-filter-grid">
                <label class="pm-field">
                    <span>Buscar pedido, item, empresa ou contrato</span>
                    <input type="text" wire:model.live.debounce.300ms="busca" placeholder="Ex.: arroz, pedido 12, contrato 45" />
                </label>

                <label class="pm-field">
                    <span>Criado por</span>
                    <select wire:model.live="criadoPor">
                        <option value="">Todos</option>
                        @foreach ($this->criadoresDisponiveis as $criador)
                            <option value="{{ $criador }}">{{ $criador }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="pm-field pm-field--small">
                    <span>Data inicial</span>
                    <input type="date" wire:model.live="dataInicio" />
                </label>

                <label class="pm-field pm-field--small">
                    <span>Data final</span>
                    <input type="date" wire:model.live="dataFim" />
                </label>

                <label class="pm-field pm-field--small">
                    <span>Por pagina</span>
                    <select wire:model.live="porPagina">
                        <option value="5">5</option>
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                </label>
            </div>

            <div class="pm-status-grid">
                @foreach (\App\Models\Enums\StatusPedidoMerenda::cases() as $status)
                    <label class="pm-check">
                        <input type="checkbox" wire:model.live="statusSelecionados" value="{{ $status->value }}">
                        <span>{{ $status->label() }}</span>
                    </label>
                @endforeach

                <label class="pm-check">
                    <input type="checkbox" wire:model.live="mostrarCancelados">
                    <span>Mostrar cancelados na ultima tabela</span>
                </label>

                <button type="button" wire:click="limparFiltros" class="pm-link-button">Limpar filtros</button>
            </div>
        </section>

        <section class="pm-panel pm-panel--half">
            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Acoes</p>
                    <h2>Atalhos operacionais</h2>
                </div>
            </div>

            <div class="pm-action-grid">
                <a href="{{ \App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource::getUrl('create') }}" class="pm-action-card pm-action-card--primary">
                    <strong>Novo pedido</strong>
                    <span>Inicia um novo pedido de merenda com reserva de itens dos contratos.</span>
                </a>

                <button type="button" class="pm-action-card" x-data @click="$el.closest('.pm-page').querySelector('[data-secao=\"aguardando\"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                    <strong>Fila aguardando</strong>
                    <span>Va direto para a tabela com pedidos prontos para empenho e tratativas.</span>
                </button>

                <button type="button" class="pm-action-card" x-data @click="$el.closest('.pm-page').querySelector('[data-secao=\"parcial\"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                    <strong>Entregas parciais</strong>
                    <span>Acesse rapidamente a fila que ainda precisa de entregas complementares.</span>
                </button>

                <button type="button" class="pm-action-card" x-data @click="$el.closest('.pm-page').querySelector('[data-secao=\"finalizado\"]')?.scrollIntoView({ behavior: 'smooth', block: 'start' })">
                    <strong>Historico final</strong>
                    <span>Consulte pedidos entregues e, se habilitado, os cancelados.</span>
                </button>
            </div>
        </section>

        <section class="pm-panel pm-panel--half" data-secao="aguardando">
            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Fila principal</p>
                    <h2>Pedidos aguardando</h2>
                </div>
            </div>

            @include('components.pedidos-merenda.table-list', [
                'pedidos' => $this->pedidosAguardando,
                'paginacao' => $this->paginacaoAguardando,
                'secao' => 'aguardando',
                'page' => $this,
                'mostrarEmpenho' => true,
            ])
        </section>

        <section class="pm-panel pm-panel--half" data-secao="parcial">
            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Em andamento</p>
                    <h2>Pedidos parcialmente entregues</h2>
                </div>
            </div>

            @include('components.pedidos-merenda.table-list', [
                'pedidos' => $this->pedidosParciais,
                'paginacao' => $this->paginacaoParcial,
                'secao' => 'parcial',
                'page' => $this,
                'mostrarEmpenho' => false,
            ])
        </section>

        <section class="pm-panel pm-panel--full" data-secao="finalizado">
            <div class="pm-panel-head">
                <div>
                    <p class="pm-panel-kicker">Historico</p>
                    <h2>Pedidos entregues e cancelados</h2>
                </div>
            </div>

            @include('components.pedidos-merenda.table-list', [
                'pedidos' => $this->pedidosFinalizados,
                'paginacao' => $this->paginacaoFinalizado,
                'secao' => 'finalizado',
                'page' => $this,
                'mostrarEmpenho' => false,
            ])
        </section>
    </div>

    @if ($modalItensAberto && $this->pedidoSelecionado)
        @include('components.pedidos-merenda.modal-itens-novo', ['pedido' => $this->pedidoSelecionado, 'page' => $this])
    @endif

<style>
    .pm-page{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:.75rem}
    .pm-panel,.pm-card{background:#fff;border:1px solid #e2e8f0;border-radius:1rem;box-shadow:0 8px 20px rgba(15,23,42,.04)}
    .pm-panel--full{grid-column:1 / -1}.pm-panel--half{grid-column:span 6}
    .pm-hero{padding:1rem 1rem .25rem}.pm-eyebrow,.pm-panel-kicker{text-transform:uppercase;letter-spacing:.08em;font-size:.68rem;font-weight:700;color:#64748b;margin:0 0 .25rem}
    .pm-hero h1,.pm-panel-head h2{margin:0;color:#0f172a;line-height:1.2}.pm-hero h1{font-size:1.35rem}.pm-panel-head h2{font-size:1rem}.pm-hero p{margin:.35rem 0 0;color:#475569;max-width:52rem;line-height:1.45;font-size:.9rem}
    .pm-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.75rem;padding:0 1rem 1rem}.pm-card{padding:.85rem}.pm-card span{display:block;font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.pm-card strong{display:block;font-size:1.3rem;color:#0f172a;margin-top:.25rem;line-height:1.1}.pm-card small{display:block;margin-top:.2rem;color:#475569;line-height:1.35;font-size:.8rem}
    .pm-panel{padding:.9rem 1rem}.pm-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:.75rem;margin-bottom:.75rem}
    .pm-filter-grid{display:grid;grid-template-columns:minmax(0,2fr) repeat(4,minmax(0,1fr));gap:.7rem}.pm-status-grid{display:flex;gap:.55rem;flex-wrap:wrap;align-items:center;margin-top:.7rem}
    .pm-field{display:flex;flex-direction:column;gap:.25rem}.pm-field span{font-size:.76rem;font-weight:700;color:#475569}.pm-field input,.pm-field select{width:100%;border-radius:.75rem;border:1px solid #cbd5e1;background:#fff;padding:.62rem .75rem;color:#0f172a;outline:none;font-size:.88rem}
    .pm-field input:focus,.pm-field select:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.10)}.pm-check{display:inline-flex;align-items:center;gap:.45rem;padding:.5rem .65rem;border:1px solid #e2e8f0;border-radius:.75rem;background:#f8fafc;color:#334155;font-size:.8rem}
    .pm-link-button{border:none;background:transparent;color:#0f766e;font-weight:700;cursor:pointer}.pm-link-button:hover{text-decoration:underline}
    .pm-action-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.7rem}.pm-action-card{display:flex;flex-direction:column;gap:.3rem;align-items:flex-start;justify-content:flex-start;padding:.8rem .85rem;border:1px solid #e2e8f0;border-radius:.85rem;background:#f8fafc;color:#1e293b;text-decoration:none;cursor:pointer;text-align:left}
    .pm-action-card strong{color:#0f172a;font-size:.88rem}.pm-action-card span{color:#64748b;line-height:1.35;font-size:.8rem}.pm-action-card--primary{background:#0f766e;border-color:#0f766e}.pm-action-card--primary strong,.pm-action-card--primary span{color:#fff}
    .pm-table-wrap{overflow:auto}.pm-table{width:100%;border-collapse:collapse}.pm-table thead tr{background:#f8fafc}.pm-table th,.pm-table td{padding:.9rem .85rem;border-top:1px solid #e2e8f0;text-align:left;vertical-align:middle}
    .pm-table th,.pm-table td{padding:.7rem .7rem;border-top:1px solid #e2e8f0;text-align:left;vertical-align:middle}.pm-table th{font-size:.72rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;white-space:nowrap}
    .pm-table td strong{display:block;color:#0f172a;font-size:.88rem;line-height:1.3}.pm-table td small{display:block;color:#64748b;margin-top:.1rem;font-size:.78rem;line-height:1.3}
    .pm-row-actions{display:flex;gap:.4rem;justify-content:flex-end;flex-wrap:wrap}.pm-row-actions a,.pm-row-actions button{border-radius:.7rem;padding:.45rem .6rem;border:1px solid #dbe4ee;background:#fff;color:#1e293b;font-weight:700;font-size:.78rem;text-decoration:none;cursor:pointer;line-height:1.2}
    .pm-row-actions .danger{color:#b91c1c;border-color:#fecaca}.pm-empty{text-align:center;color:#64748b;padding:1.4rem .75rem;font-size:.88rem}.pm-pagination{display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap;padding-top:.75rem}
    .pm-pagination span{color:#64748b;font-size:.82rem}.pm-pagination-controls{display:flex;gap:.45rem}.pm-pagination-controls button{border:1px solid #cbd5e1;background:#fff;border-radius:.7rem;padding:.45rem .7rem;font-weight:700;cursor:pointer;font-size:.8rem}
    .pm-badge-warning,.pm-badge-info,.pm-badge-success,.pm-badge-danger,.pm-badge-neutral{display:inline-flex;align-items:center;padding:.35rem .65rem;border-radius:999px;font-size:.76rem;font-weight:700}
    .pm-badge-warning{background:#fef3c7;color:#92400e}.pm-badge-info{background:#dbeafe;color:#1d4ed8}.pm-badge-success{background:#dcfce7;color:#166534}.pm-badge-danger{background:#fee2e2;color:#991b1b}.pm-badge-neutral{background:#e2e8f0;color:#334155}
    .pm-badge-warning,.pm-badge-info,.pm-badge-success,.pm-badge-danger,.pm-badge-neutral{padding:.28rem .55rem;font-size:.72rem}
    .pm-modal-shell{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:.75rem}.pm-modal-bg{position:absolute;inset:0;background:rgba(15,23,42,.52)}
    .pm-modal{position:relative;z-index:1;width:min(1120px,100%);max-height:calc(100vh - 1.5rem);overflow:auto;background:#fff;border-radius:1rem;box-shadow:0 20px 60px rgba(15,23,42,.18)}
    .pm-modal-head{padding:.9rem 1rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;gap:.75rem;align-items:flex-start}.pm-modal-title{margin:0;color:#0f172a;font-size:1rem}.pm-modal-text{margin-top:.25rem;color:#64748b;line-height:1.4;font-size:.84rem}.pm-modal-body{padding:.9rem 1rem;display:grid;gap:.75rem}.pm-modal-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.65rem}
    .pm-mini{border:1px solid #e2e8f0;border-radius:.85rem;background:#f8fafc;padding:.7rem}.pm-mini span{display:block;font-size:.68rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.pm-mini strong{display:block;margin-top:.2rem;color:#0f172a;font-size:.9rem}
    .pm-chip-list{display:flex;gap:.4rem;flex-wrap:wrap}.pm-chip{display:inline-flex;align-items:center;padding:.28rem .55rem;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:.72rem;font-weight:700}
    .pm-modal-foot{padding:.9rem 1rem;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:.75rem;flex-wrap:wrap}
    @media (max-width:1100px){.pm-cards,.pm-modal-overview,.pm-action-grid{grid-template-columns:repeat(2,minmax(0,1fr))}.pm-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:800px){.pm-panel--half,.pm-panel--full{grid-column:1 / -1}}
    @media (max-width:700px){.pm-page{gap:.6rem}.pm-panel{padding:.8rem}.pm-hero{padding:.85rem .85rem .2rem}.pm-cards{padding:0 .85rem .85rem;gap:.6rem}.pm-pagination,.pm-modal-head,.pm-modal-foot{flex-direction:column;align-items:stretch}.pm-cards,.pm-filter-grid,.pm-modal-overview,.pm-action-grid{grid-template-columns:1fr}.pm-row-actions{justify-content:flex-start}.pm-table{min-width:720px}}
</style>
</x-filament-panels::page>
