<x-filament-panels::page>
    <div class="pm-page">
        <section class="pm-hero">
            <div>
                <p class="pm-eyebrow">Merenda Escolar</p>
                <h1>Panorama dos Pedidos de Merenda</h1>
                <p>Visao clara do fluxo de pedidos, mantendo o acompanhamento por etapa com menos ruido visual.</p>
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

        <section class="pm-grid">
            <article class="pm-panel pm-panel--filters">
                <div class="pm-panel-head">
                    <div>
                        <p class="pm-panel-kicker">Busca e filtros</p>
                        <h2>Refinar pedidos</h2>
                    </div>
                </div>

                <div class="pm-filter-grid">
                    <label class="pm-field pm-field--wide">
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

                    <label class="pm-field">
                        <span>Data inicial</span>
                        <input type="date" wire:model.live="dataInicio" />
                    </label>

                    <label class="pm-field">
                        <span>Data final</span>
                        <input type="date" wire:model.live="dataFim" />
                    </label>

                    <label class="pm-field">
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

                <div class="pm-inline-tools">
                    <label class="pm-check">
                        <input type="checkbox" wire:model.live="mostrarCancelados">
                        <span>Incluir cancelados no historico final</span>
                    </label>

                    <button type="button" wire:click="limparFiltros" class="pm-link-button">Limpar filtros</button>
                </div>
            </article>

            <article class="pm-panel pm-panel--side" data-secao="parcial">
                <div class="pm-panel-head">
                    <div>
                        <p class="pm-panel-kicker">Em andamento</p>
                        <h2>Itens parcialmente entregues</h2>
                    </div>
                </div>

                @include('components.pedidos-merenda.table-partial-items', [
                    'itens' => $this->itensParciais,
                    'paginacao' => $this->paginacaoParcial,
                    'secao' => 'parcial',
                    'page' => $this,
                ])
            </article>
        </section>

        <section class="pm-panel pm-panel--wide" data-secao="aguardando">
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
                'compacta' => false,
                'exibirStatus' => false,
            ])
        </section>

        <section class="pm-panel pm-panel--wide" data-secao="finalizado">
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
                'compacta' => false,
                'exibirStatus' => true,
            ])
        </section>
    </div>

    @if ($modalItensAberto && $this->pedidoSelecionado)
        @include('components.pedidos-merenda.modal-itens-novo', ['pedido' => $this->pedidoSelecionado, 'page' => $this])
    @endif

<style>
    .pm-page{display:grid;gap:1rem;color:var(--gray-700)}
    .pm-hero,.pm-panel,.pm-card,.pm-modal{border:1px solid var(--gray-200);background:linear-gradient(180deg,var(--gray-50) 0%,#fff 100%);box-shadow:0 1px 2px rgba(15,23,42,.05),0 18px 40px rgba(15,23,42,.06)}
    .pm-hero{display:flex;justify-content:space-between;align-items:flex-end;gap:1rem;padding:1.25rem;border-radius:1rem}
    .pm-eyebrow,.pm-panel-kicker{margin:0 0 .35rem;color:var(--primary-600);text-transform:uppercase;letter-spacing:.08em;font-size:var(--text-xs);line-height:var(--text-xs--line-height);font-weight:var(--font-weight-semibold)}
    .pm-hero h1,.pm-panel h2,.pm-modal-title{margin:0;color:var(--gray-950);line-height:1.2;font-weight:var(--font-weight-bold);letter-spacing:-.02em}
    .pm-hero h1{font-size:clamp(1.45rem,2vw,1.9rem)}.pm-panel h2,.pm-modal-title{font-size:var(--text-lg);line-height:var(--text-lg--line-height)}
    .pm-hero p,.pm-panel-head p,.pm-card small,.pm-field span,.pm-table small,.pm-empty,.pm-pagination span,.pm-modal-text,.pm-chip{margin:0;color:var(--gray-500);font-size:var(--text-sm);line-height:1.5}
    .pm-actions{display:flex;gap:.75rem;flex-wrap:wrap}.pm-action,.pm-row-actions a,.pm-row-actions button,.pm-pagination-controls button{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;min-height:2.35rem;padding:.55rem .9rem;border:1px solid var(--gray-200);border-radius:var(--radius-lg);background:#fff;color:var(--gray-700);font-size:var(--text-sm);line-height:var(--text-sm--line-height);font-weight:var(--font-weight-medium);text-decoration:none;cursor:pointer;transition:background-color .15s ease,border-color .15s ease,color .15s ease,box-shadow .15s ease}
    .pm-action--primary{border-color:var(--primary-600);background:var(--primary-600);color:#fff}
    .pm-action:focus-visible,.pm-row-actions a:focus-visible,.pm-row-actions button:focus-visible,.pm-pagination-controls button:focus-visible,.pm-field input:focus,.pm-field select:focus{outline:none;border-color:var(--primary-400);box-shadow:0 0 0 3px color-mix(in oklab,var(--primary-200) 70%,transparent)}
    .pm-cards{display:grid;gap:1rem;grid-template-columns:repeat(auto-fit,minmax(13rem,1fr))}
    .pm-card{display:grid;gap:.35rem;padding:1rem;border-radius:var(--radius-xl)}
    .pm-card span{color:var(--gray-500);font-size:var(--text-sm);line-height:var(--text-sm--line-height)}
    .pm-card strong{color:var(--gray-950);font-size:clamp(1.2rem,1.7vw,1.7rem);line-height:1.15;font-weight:var(--font-weight-bold);letter-spacing:-.03em}
    .pm-grid{display:grid;gap:1rem;grid-template-columns:minmax(0,1fr) minmax(0,1.25fr);align-items:start}
    .pm-panel{display:grid;gap:.9rem;padding:1.1rem;border-radius:var(--radius-xl)}
    .pm-panel--wide{gap:1rem}
    .pm-panel-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem}
    .pm-filter-grid{display:grid;gap:.8rem;grid-template-columns:repeat(2,minmax(0,1fr))}
    .pm-field{display:grid;gap:.4rem;min-width:0}.pm-field--wide{grid-column:1 / -1}
    .pm-field input,.pm-field select{width:100%;min-height:2.65rem;padding:.72rem .85rem;border:1px solid var(--gray-300);border-radius:var(--radius-lg);background:#fff;color:var(--gray-950);font-size:var(--text-sm);line-height:1.5;transition:border-color .15s ease,box-shadow .15s ease,background-color .15s ease}
    .pm-inline-tools{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:.75rem}
    .pm-check{display:inline-flex;align-items:center;gap:.5rem;padding:.55rem .8rem;border:1px solid var(--gray-200);border-radius:999px;background:var(--gray-50);color:var(--gray-600);font-size:var(--text-sm);line-height:var(--text-sm--line-height)}
    .pm-link-button{border:0;padding:0;background:transparent;color:var(--primary-600);font-size:var(--text-sm);line-height:var(--text-sm--line-height);font-weight:var(--font-weight-medium);cursor:pointer}
    .pm-table-wrap{overflow:auto;border:1px solid var(--gray-200);border-radius:var(--radius-xl);background:#fff}
    .pm-table{width:100%;min-width:40rem;border-collapse:separate;border-spacing:0}
    .pm-table th,.pm-table td{padding:.85rem .95rem;text-align:left;vertical-align:top;border-bottom:1px solid var(--gray-200);font-size:var(--text-sm);line-height:1.5}
    .pm-table th{position:sticky;top:0;z-index:1;background:var(--gray-50);color:var(--gray-600);text-transform:uppercase;letter-spacing:.06em;font-size:var(--text-xs);line-height:var(--text-xs--line-height);font-weight:var(--font-weight-semibold)}
    .pm-table tbody tr:last-child td{border-bottom:0}.pm-table td strong{display:block;color:var(--gray-950);font-weight:var(--font-weight-semibold)}
    .pm-table .text-right{text-align:right}
    .pm-row-actions{display:flex;flex-wrap:wrap;gap:.5rem;justify-content:flex-end}
    .pm-row-actions .danger{color:var(--danger-700)}
    .pm-pagination{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:1rem}
    .pm-pagination-controls{display:flex;flex-wrap:wrap;gap:.5rem}
    .pm-badge-warning,.pm-badge-info,.pm-badge-success,.pm-badge-danger,.pm-badge-neutral{display:inline-flex;align-items:center;justify-content:center;gap:.35rem;padding:.25rem .625rem;border:1px solid transparent;border-radius:999px;font-size:var(--text-xs);line-height:var(--text-xs--line-height);font-weight:var(--font-weight-medium);white-space:nowrap}
    .pm-badge-warning{border-color:var(--warning-200);background:var(--warning-50);color:var(--warning-700)}
    .pm-badge-info{border-color:var(--primary-200);background:var(--primary-50);color:var(--primary-700)}
    .pm-badge-success{border-color:var(--success-200);background:var(--success-50);color:var(--success-700)}
    .pm-badge-danger{border-color:var(--danger-200);background:var(--danger-50);color:var(--danger-700)}
    .pm-badge-neutral{border-color:var(--gray-200);background:var(--gray-50);color:var(--gray-700)}
    .pm-empty{text-align:center;padding:1.5rem 1rem}
    .pm-modal-shell{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:.75rem}.pm-modal-bg{position:absolute;inset:0;background:rgba(15,23,42,.45);backdrop-filter:blur(3px)}
    .pm-modal{position:relative;z-index:1;width:min(1100px,100%);max-height:calc(100vh - 1.5rem);overflow:auto;border-radius:1rem}
    .pm-modal-head{display:flex;justify-content:space-between;align-items:flex-start;gap:1rem;padding:1rem 1.1rem;border-bottom:1px solid var(--gray-200)}
    .pm-modal-body{padding:1rem 1.1rem;display:grid;gap:.85rem}.pm-modal-overview{display:grid;gap:.75rem;grid-template-columns:repeat(4,minmax(0,1fr))}
    .pm-mini{border:1px solid var(--gray-200);border-radius:var(--radius-xl);background:#fff;padding:.8rem}.pm-mini span{display:block;color:var(--gray-500);font-size:var(--text-xs);line-height:var(--text-xs--line-height);text-transform:uppercase;letter-spacing:.05em}.pm-mini strong{display:block;margin-top:.2rem;color:var(--gray-950);font-size:var(--text-base);font-weight:var(--font-weight-semibold)}
    .pm-chip-list{display:flex;gap:.5rem;flex-wrap:wrap}.pm-chip{display:inline-flex;align-items:center;gap:.35rem;padding:.22rem .55rem;border:1px solid var(--gray-200);border-radius:999px;background:var(--gray-50);font-size:var(--text-xs);line-height:var(--text-xs--line-height);font-weight:var(--font-weight-medium)}
    .pm-modal-foot{display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;padding:1rem 1.1rem;border-top:1px solid var(--gray-200)}
    @media (hover:hover){.pm-action:hover,.pm-row-actions a:hover,.pm-row-actions button:hover,.pm-pagination-controls button:hover{border-color:var(--gray-300);background:var(--gray-50);color:var(--gray-950)}.pm-action--primary:hover{border-color:var(--primary-700);background:var(--primary-700);color:#fff}}
    @media (max-width:80rem){.pm-grid{grid-template-columns:1fr}}
    @media (max-width:48rem){.pm-page{gap:1rem}.pm-hero,.pm-panel,.pm-card{padding:1rem}.pm-hero,.pm-panel-head,.pm-pagination,.pm-modal-head,.pm-modal-foot{flex-direction:column;align-items:stretch}.pm-actions,.pm-pagination-controls{width:100%}.pm-actions>*,.pm-pagination-controls>*{flex:1 1 100%}.pm-filter-grid,.pm-modal-overview{grid-template-columns:1fr}.pm-table{min-width:36rem}.pm-table .text-right,.pm-row-actions{text-align:left;justify-content:flex-start}}
    :root.dark .pm-hero,:root.dark .pm-panel,:root.dark .pm-card,:root.dark .pm-modal{border-color:var(--gray-800);background:linear-gradient(180deg,var(--gray-900) 0%,var(--gray-950) 100%);box-shadow:0 1px 2px rgba(0,0,0,.35),0 18px 40px rgba(0,0,0,.22)}
    :root.dark .pm-hero h1,:root.dark .pm-panel h2,:root.dark .pm-modal-title,:root.dark .pm-card strong,:root.dark .pm-table td strong,:root.dark .pm-mini strong{color:#fff}
    :root.dark .pm-hero p,:root.dark .pm-panel-head p,:root.dark .pm-card small,:root.dark .pm-field span,:root.dark .pm-table small,:root.dark .pm-empty,:root.dark .pm-pagination span,:root.dark .pm-modal-text,:root.dark .pm-chip,:root.dark .pm-card span,:root.dark .pm-page{color:var(--gray-400)}
    :root.dark .pm-action,:root.dark .pm-row-actions a,:root.dark .pm-row-actions button,:root.dark .pm-pagination-controls button{border-color:var(--gray-700);background:var(--gray-900);color:var(--gray-200)}
    :root.dark .pm-action--primary{border-color:var(--primary-500);background:var(--primary-600);color:#fff}
    :root.dark .pm-field input,:root.dark .pm-field select,:root.dark .pm-table-wrap,:root.dark .pm-table th,:root.dark .pm-mini{border-color:var(--gray-800);background:var(--gray-900);color:var(--gray-100)}
</style>
</x-filament-panels::page>
