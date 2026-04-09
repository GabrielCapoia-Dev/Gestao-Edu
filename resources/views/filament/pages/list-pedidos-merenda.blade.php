<x-filament-panels::page>
    <div class="pm-page">
        <section class="pm-hero">
            <div>
                <p class="pm-eyebrow">Merenda Escolar</p>
                <h1>Panorama dos Pedidos de Merenda</h1>
                <p>Visao operacional dos pedidos com filtros, acompanhamento por status, exportacao de empenho e controle direto dos itens.</p>
            </div>

            <div class="pm-actions">
                <a href="{{ \App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource::getUrl('create') }}" class="pm-action pm-action--primary">
                    Novo pedido
                </a>
            </div>
        </section>

        <section class="pm-cards">
            @foreach ($this->resumoCards as $card)
                <article class="pm-card">
                    <span>{{ $card['titulo'] }}</span>
                    <strong>{{ $card['valor'] }}</strong>
                    <small>{{ $card['descricao'] }}</small>
                </article>
            @endforeach
        </section>

        <section class="pm-panel">
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

        <section class="pm-panel">
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

        <section class="pm-panel">
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

        <section class="pm-panel">
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
    .pm-page{display:grid;gap:1rem}.pm-hero,.pm-panel,.pm-card{background:#fff;border:1px solid #e2e8f0;border-radius:1.25rem;box-shadow:0 12px 32px rgba(15,23,42,.05)}
    .pm-hero{padding:1.5rem;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.pm-eyebrow,.pm-panel-kicker{text-transform:uppercase;letter-spacing:.08em;font-size:.72rem;font-weight:700;color:#64748b;margin:0 0 .35rem}
    .pm-hero h1,.pm-panel-head h2{margin:0;color:#0f172a}.pm-hero p{margin:.5rem 0 0;color:#475569;max-width:58rem;line-height:1.5}.pm-actions{display:flex;gap:.75rem;flex-wrap:wrap}
    .pm-action{display:inline-flex;align-items:center;justify-content:center;border-radius:.9rem;padding:.8rem 1rem;font-weight:700;text-decoration:none}.pm-action--primary{background:#0f766e;color:#fff}
    .pm-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:1rem}.pm-card{padding:1rem}.pm-card span{display:block;font-size:.78rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.pm-card strong{display:block;font-size:1.55rem;color:#0f172a;margin-top:.4rem}.pm-card small{display:block;margin-top:.3rem;color:#475569;line-height:1.45}
    .pm-panel{padding:1.1rem 1.2rem}.pm-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;margin-bottom:1rem}
    .pm-filter-grid{display:grid;grid-template-columns:minmax(0,2fr) repeat(4,minmax(0,1fr));gap:.85rem}.pm-status-grid{display:flex;gap:.75rem;flex-wrap:wrap;align-items:center;margin-top:.9rem}
    .pm-field{display:flex;flex-direction:column;gap:.35rem}.pm-field span{font-size:.8rem;font-weight:700;color:#475569}.pm-field input,.pm-field select{width:100%;border-radius:.9rem;border:1px solid #cbd5e1;background:#fff;padding:.75rem .85rem;color:#0f172a;outline:none}
    .pm-field input:focus,.pm-field select:focus{border-color:#0f766e;box-shadow:0 0 0 3px rgba(15,118,110,.12)}.pm-check{display:inline-flex;align-items:center;gap:.5rem;padding:.6rem .8rem;border:1px solid #e2e8f0;border-radius:.9rem;background:#f8fafc;color:#334155;font-size:.85rem}
    .pm-link-button{border:none;background:transparent;color:#0f766e;font-weight:700;cursor:pointer}.pm-link-button:hover{text-decoration:underline}
    .pm-table-wrap{overflow:auto}.pm-table{width:100%;border-collapse:collapse}.pm-table thead tr{background:#f8fafc}.pm-table th,.pm-table td{padding:.9rem .85rem;border-top:1px solid #e2e8f0;text-align:left;vertical-align:middle}
    .pm-table th{font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.pm-table td strong{display:block;color:#0f172a}.pm-table td small{display:block;color:#64748b;margin-top:.15rem}
    .pm-row-actions{display:flex;gap:.5rem;justify-content:flex-end;flex-wrap:wrap}.pm-row-actions a,.pm-row-actions button{border-radius:.8rem;padding:.55rem .75rem;border:1px solid #dbe4ee;background:#fff;color:#1e293b;font-weight:700;font-size:.82rem;text-decoration:none;cursor:pointer}
    .pm-row-actions .danger{color:#b91c1c;border-color:#fecaca}.pm-empty{text-align:center;color:#64748b;padding:2rem 1rem}.pm-pagination{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding-top:1rem}
    .pm-pagination span{color:#64748b;font-size:.88rem}.pm-pagination-controls{display:flex;gap:.6rem}.pm-pagination-controls button{border:1px solid #cbd5e1;background:#fff;border-radius:.8rem;padding:.55rem .85rem;font-weight:700;cursor:pointer}
    .pm-badge-warning,.pm-badge-info,.pm-badge-success,.pm-badge-danger,.pm-badge-neutral{display:inline-flex;align-items:center;padding:.35rem .65rem;border-radius:999px;font-size:.76rem;font-weight:700}
    .pm-badge-warning{background:#fef3c7;color:#92400e}.pm-badge-info{background:#dbeafe;color:#1d4ed8}.pm-badge-success{background:#dcfce7;color:#166534}.pm-badge-danger{background:#fee2e2;color:#991b1b}.pm-badge-neutral{background:#e2e8f0;color:#334155}
    .pm-modal-shell{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:1rem}.pm-modal-bg{position:absolute;inset:0;background:rgba(15,23,42,.52)}
    .pm-modal{position:relative;z-index:1;width:min(1120px,100%);max-height:calc(100vh - 2rem);overflow:auto;background:#fff;border-radius:1.2rem;box-shadow:0 24px 80px rgba(15,23,42,.22)}
    .pm-modal-head{padding:1rem 1.2rem;border-bottom:1px solid #e2e8f0;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.pm-modal-title{margin:0;color:#0f172a}.pm-modal-text{margin-top:.35rem;color:#64748b;line-height:1.45}.pm-modal-body{padding:1rem 1.2rem;display:grid;gap:1rem}.pm-modal-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
    .pm-mini{border:1px solid #e2e8f0;border-radius:1rem;background:#f8fafc;padding:.85rem}.pm-mini span{display:block;font-size:.74rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b}.pm-mini strong{display:block;margin-top:.25rem;color:#0f172a}
    .pm-chip-list{display:flex;gap:.5rem;flex-wrap:wrap}.pm-chip{display:inline-flex;align-items:center;padding:.35rem .65rem;border-radius:999px;background:#f8fafc;border:1px solid #e2e8f0;color:#475569;font-size:.76rem;font-weight:700}
    .pm-modal-foot{padding:1rem 1.2rem;border-top:1px solid #e2e8f0;display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap}
    @media (max-width:1100px){.pm-cards,.pm-modal-overview{grid-template-columns:repeat(2,minmax(0,1fr))}.pm-filter-grid{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:700px){.pm-hero,.pm-pagination,.pm-modal-head,.pm-modal-foot{flex-direction:column;align-items:stretch}.pm-cards,.pm-filter-grid,.pm-modal-overview{grid-template-columns:1fr}.pm-row-actions{justify-content:flex-start}}
</style>
</x-filament-panels::page>
