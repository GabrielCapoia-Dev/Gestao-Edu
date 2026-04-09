<x-filament-panels::page>
<style>
    .fi-page-content{padding:0}.pm-page{padding:1rem;background:linear-gradient(180deg,#f5f7fb 0%,#eef5f3 100%);min-height:calc(100vh - 5rem)}
    .pm-grid{display:grid;grid-template-columns:repeat(12,minmax(0,1fr));gap:1rem}.pm-span-12{grid-column:span 12}.pm-span-6{grid-column:span 6}
    .pm-panel{background:rgba(255,255,255,.92);border:1px solid rgba(148,163,184,.26);border-radius:1.35rem;box-shadow:0 18px 45px rgba(15,23,42,.08);overflow:hidden}
    .pm-head{padding:1.1rem 1.2rem .2rem;display:flex;justify-content:space-between;gap:1rem;align-items:flex-start}.pm-body{padding:1rem 1.2rem 1.2rem}
    .pm-hero{padding:1.4rem;background:linear-gradient(135deg,#c6f1de,#d4f7f0)}.pm-title{margin:0;color:#14213d;font-size:1.65rem;font-weight:800}.pm-subtitle{margin:.45rem 0 0;color:#355070;font-size:.96rem;line-height:1.55}
    .pm-cards{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.85rem;margin-top:1rem}.pm-card{border-radius:1rem;padding:1rem}.pm-card span{display:block}.pm-card-label{text-transform:uppercase;font-size:.74rem;letter-spacing:.08em;font-weight:700;opacity:.7}.pm-card-value{font-size:1.8rem;font-weight:800;margin-top:.4rem}.pm-card-meta{margin-top:.2rem;font-size:.83rem;color:#4b5563}
    .pm-sky{background:linear-gradient(135deg,#dff5ff,#edf9ff)}.pm-amber{background:linear-gradient(135deg,#ffe3b0,#fff2d9)}.pm-blue{background:linear-gradient(135deg,#d8e7ff,#eef4ff)}.pm-rose{background:linear-gradient(135deg,#ffdbe6,#fff0f5)}
    .pm-filters{background:linear-gradient(135deg,#ffa500,#ffc25c)}.pm-actions{background:linear-gradient(135deg,#fff5bf,#ffe7aa)}.pm-awaiting{background:linear-gradient(180deg,#c9f0eb,#ecfdfa)}.pm-partial{background:linear-gradient(180deg,#ddd0ff,#f4f0ff)}.pm-completed{background:linear-gradient(180deg,#f9d0dd,#fff1f5)}
    .pm-section-title{margin:0;color:#14213d;font-size:1.06rem;font-weight:800}.pm-section-text{margin-top:.25rem;color:#64748b;font-size:.88rem;line-height:1.45}.pm-count{display:inline-flex;align-items:center;justify-content:center;min-width:2.1rem;height:2.1rem;border-radius:999px;background:rgba(255,255,255,.82);color:#14213d;font-size:.84rem;font-weight:800}
    .pm-form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.85rem}.pm-label{display:block;margin-bottom:.4rem;color:#1f2937;font-size:.84rem;font-weight:700}
    .pm-input,.pm-select{width:100%;border-radius:.95rem;border:1px solid rgba(15,23,42,.12);background:rgba(255,255,255,.92);padding:.78rem .9rem;color:#0f172a;font-size:.92rem;box-sizing:border-box;outline:none}
    .pm-input:focus,.pm-select:focus{border-color:#0284c7;box-shadow:0 0 0 4px rgba(2,132,199,.14)}.pm-statuses{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.55rem;margin-top:.25rem}
    .pm-check{display:flex;align-items:center;gap:.55rem;background:rgba(255,255,255,.84);border-radius:.85rem;padding:.65rem .8rem;font-size:.84rem;color:#1f2937}.pm-check input{width:1rem;height:1rem}
    .pm-actions-grid{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.8rem}.pm-action-card{border-radius:1rem;padding:1rem;background:rgba(255,255,255,.84);border:1px solid rgba(148,163,184,.18)}
    .pm-action-title{font-weight:800;color:#14213d;margin-bottom:.3rem}.pm-action-text{color:#64748b;font-size:.84rem;line-height:1.45;margin-bottom:.85rem}
    .pm-btn{display:inline-flex;align-items:center;justify-content:center;gap:.5rem;border-radius:.95rem;border:none;padding:.82rem 1rem;font-size:.9rem;font-weight:800;cursor:pointer;text-decoration:none;transition:transform .18s ease,opacity .18s ease}
    .pm-btn:hover{transform:translateY(-1px)}.pm-btn-primary{background:linear-gradient(135deg,#0284c7,#0369a1);color:#fff}.pm-btn-success{background:linear-gradient(135deg,#15803d,#166534);color:#fff}.pm-btn-light{background:rgba(255,255,255,.88);color:#14213d;border:1px solid rgba(15,23,42,.1)}.pm-btn-danger{background:linear-gradient(135deg,#dc2626,#b91c1c);color:#fff}
    .pm-list{display:grid;gap:.85rem}.pm-empty{padding:2.5rem 1rem;text-align:center;color:#64748b;background:rgba(255,255,255,.68);border-radius:1rem;border:1px dashed rgba(148,163,184,.3)}.pm-empty strong{display:block;color:#14213d;margin-bottom:.35rem}
    .pm-modal-shell{position:fixed;inset:0;z-index:60;display:flex;align-items:center;justify-content:center;padding:1rem}.pm-modal-bg{position:absolute;inset:0;background:rgba(15,23,42,.62);backdrop-filter:blur(4px)}
    .pm-modal{position:relative;z-index:1;width:min(1180px,100%);max-height:calc(100vh - 2rem);overflow:hidden;border-radius:1.45rem;background:#fff;box-shadow:0 35px 90px rgba(15,23,42,.28);display:flex;flex-direction:column}
    .pm-modal-head{padding:1.2rem 1.3rem;background:linear-gradient(135deg,#112031,#1d3557);color:#fff;display:flex;align-items:flex-start;justify-content:space-between;gap:1rem}.pm-modal-title{margin:0;font-size:1.15rem;font-weight:800}.pm-modal-text{margin-top:.3rem;color:rgba(255,255,255,.82);font-size:.88rem;line-height:1.5}
    .pm-modal-body{padding:1.15rem 1.25rem 1.25rem;overflow:auto;display:grid;gap:1rem;background:linear-gradient(180deg,#fcfdff 0%,#f6f9fc 100%)}.pm-modal-overview{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:.8rem}
    .pm-mini{border-radius:1rem;background:#fff;border:1px solid rgba(148,163,184,.18);padding:.9rem}.pm-mini span{display:block;font-size:.76rem;text-transform:uppercase;letter-spacing:.05em;color:#64748b;margin-bottom:.25rem}.pm-mini strong{display:block;color:#14213d;font-size:1rem}
    .pm-chip-list{display:flex;gap:.45rem;flex-wrap:wrap}.pm-chip{display:inline-flex;align-items:center;border-radius:999px;background:rgba(255,255,255,.78);border:1px solid rgba(148,163,184,.18);padding:.38rem .7rem;color:#475569;font-size:.76rem;font-weight:700}
    .pm-modal-foot{padding:1rem 1.25rem;border-top:1px solid rgba(148,163,184,.18);display:flex;justify-content:space-between;align-items:center;gap:.8rem;flex-wrap:wrap;background:#fff}
    @media (max-width:1100px){.pm-cards,.pm-modal-overview{grid-template-columns:repeat(2,minmax(0,1fr))}}
    @media (max-width:920px){.pm-span-6{grid-column:span 12}.pm-actions-grid,.pm-form{grid-template-columns:1fr}}
    @media (max-width:680px){.pm-page{padding:.45rem}.pm-cards,.pm-modal-overview,.pm-statuses{grid-template-columns:1fr}.pm-modal-head,.pm-modal-foot{flex-direction:column;align-items:stretch}.pm-btn{width:100%}}
</style>

<div class="pm-page">
    <div class="pm-grid">
        <section class="pm-panel pm-span-12">
            <div class="pm-hero">
                <h1 class="pm-title">Controle operacional dos pedidos da merenda</h1>
                <p class="pm-subtitle">A equipe acompanha o que esta aguardando entrega, o que saiu parcialmente e o historico de pedidos concluidos em uma unica tela, com foco nas acoes do dia a dia.</p>
                <div class="pm-cards">
                    @foreach($this->resumoCards as $card)
                        <article class="pm-card pm-{{ $card['cor'] }}">
                            <span class="pm-card-label">{{ $card['titulo'] }}</span>
                            <span class="pm-card-value">{{ $card['valor'] }}</span>
                            <span class="pm-card-meta">{{ $card['meta'] }}</span>
                        </article>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="pm-panel pm-span-6 pm-filters">
            <div class="pm-head">
                <div>
                    <h2 class="pm-section-title">Buscas e filtros</h2>
                    <p class="pm-section-text">Refine por texto, responsavel, periodo e status para deixar as filas mais limpas.</p>
                </div>
            </div>
            <div class="pm-body">
                <div class="pm-form">
                    <div>
                        <label class="pm-label">Busca geral</label>
                        <input type="text" wire:model.live.debounce.300ms="busca" class="pm-input" placeholder="Pedido, observacoes, item, empresa ou contrato">
                    </div>
                    <div>
                        <label class="pm-label">Criado por</label>
                        <select wire:model.live="criadoPor" class="pm-select">
                            <option value="">Todos</option>
                            @foreach($this->criadoresDisponiveis as $criador)
                                <option value="{{ $criador }}">{{ $criador }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="pm-label">Data inicial</label>
                        <input type="date" wire:model.live="dataInicio" class="pm-input">
                    </div>
                    <div>
                        <label class="pm-label">Data final</label>
                        <input type="date" wire:model.live="dataFim" class="pm-input">
                    </div>
                </div>
                <div style="margin-top:.95rem;">
                    <label class="pm-label">Status considerados</label>
                    <div class="pm-statuses">
                        @foreach(\App\Models\Enums\StatusPedidoMerenda::cases() as $status)
                            <label class="pm-check">
                                <input type="checkbox" wire:model.live="statusSelecionados" value="{{ $status->value }}">
                                <span>{{ $status->label() }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;gap:1rem;flex-wrap:wrap;margin-top:1rem;">
                    <label class="pm-check" style="max-width:20rem;">
                        <input type="checkbox" wire:model.live="mostrarCancelados">
                        <span>Mostrar cancelados no bloco final</span>
                    </label>
                    <button type="button" wire:click="limparFiltros" class="pm-btn pm-btn-light">Limpar filtros</button>
                </div>
            </div>
        </section>

        <section class="pm-panel pm-span-6 pm-actions">
            <div class="pm-head">
                <div>
                    <h2 class="pm-section-title">Acoes rapidas</h2>
                    <p class="pm-section-text">Atalhos para iniciar novos pedidos e trabalhar nas filas abertas.</p>
                </div>
            </div>
            <div class="pm-body">
                <div class="pm-actions-grid">
                    <article class="pm-action-card">
                        <div class="pm-action-title">Novo pedido</div>
                        <div class="pm-action-text">Abre o fluxo guiado para montar um novo pedido e reservar saldo dos contratos.</div>
                        <a href="{{ \App\Filament\Admin\Resources\PedidosMerenda\PedidosMerendaResource::getUrl('create') }}" class="pm-btn pm-btn-primary">Criar pedido</a>
                    </article>
                    <article class="pm-action-card">
                        <div class="pm-action-title">Exportar empenho</div>
                        <div class="pm-action-text">Disponivel nos pedidos aguardando, com dados do pedido, itens e empresas relacionadas em XLSX.</div>
                        <button type="button" class="pm-btn pm-btn-light" x-data @click="$el.closest('.pm-page').querySelector('[data-awaiting]')?.scrollIntoView({behavior:'smooth',block:'start'})">Ir para aguardando</button>
                    </article>
                    <article class="pm-action-card">
                        <div class="pm-action-title">Entregas em andamento</div>
                        <div class="pm-action-text">Use o modal de itens para registrar entrega parcial e ajustar a quantidade quando houver margem no contrato.</div>
                        <button type="button" class="pm-btn pm-btn-light" x-data @click="$el.closest('.pm-page').querySelector('[data-partial]')?.scrollIntoView({behavior:'smooth',block:'start'})">Ver entregas parciais</button>
                    </article>
                    <article class="pm-action-card">
                        <div class="pm-action-title">Historico final</div>
                        <div class="pm-action-text">Pedidos entregues e cancelados ficam no bloco final para conferencia e rastreio rapido.</div>
                        <button type="button" class="pm-btn pm-btn-light" x-data @click="$el.closest('.pm-page').querySelector('[data-completed]')?.scrollIntoView({behavior:'smooth',block:'start'})">Ver historico</button>
                    </article>
                </div>
            </div>
        </section>

        <section class="pm-panel pm-span-6 pm-awaiting" data-awaiting>
            <div class="pm-head">
                <div>
                    <h2 class="pm-section-title">Pedidos aguardando</h2>
                    <p class="pm-section-text">Pedidos sem entrega registrada, prontos para conferencia, exportacao de empenho e acoes operacionais.</p>
                </div>
                <span class="pm-count">{{ $this->pedidosAguardando->count() }}</span>
            </div>
            <div class="pm-body">
                <div class="pm-list">
                    @forelse($this->pedidosAguardando as $pedido)
                        @include('components.pedidos-merenda.order-card', ['pedido' => $pedido, 'page' => $this])
                    @empty
                        <div class="pm-empty"><strong>Nenhum pedido aguardando com os filtros atuais.</strong>Ajuste a busca ou crie um novo pedido para iniciar a fila.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="pm-panel pm-span-6 pm-partial" data-partial>
            <div class="pm-head">
                <div>
                    <h2 class="pm-section-title">Pedidos parcialmente entregues</h2>
                    <p class="pm-section-text">Pedidos em andamento com saldo pendente. O modal traz progresso e acao por item.</p>
                </div>
                <span class="pm-count">{{ $this->pedidosParciais->count() }}</span>
            </div>
            <div class="pm-body">
                <div class="pm-list">
                    @forelse($this->pedidosParciais as $pedido)
                        @include('components.pedidos-merenda.order-card', ['pedido' => $pedido, 'page' => $this])
                    @empty
                        <div class="pm-empty"><strong>Nenhum pedido parcialmente entregue no momento.</strong>Quando uma entrega parcial for registrada ela aparecera aqui automaticamente.</div>
                    @endforelse
                </div>
            </div>
        </section>

        <section class="pm-panel pm-span-12 pm-completed" data-completed>
            <div class="pm-head">
                <div>
                    <h2 class="pm-section-title">Pedidos entregues e cancelados</h2>
                    <p class="pm-section-text">@if($mostrarCancelados) Os cancelados tambem estao visiveis neste bloco. @else Ative o filtro de cancelados para incluir pedidos encerrados sem entrega total. @endif</p>
                </div>
                <span class="pm-count">{{ $this->pedidosFinalizados->count() }}</span>
            </div>
            <div class="pm-body">
                <div class="pm-list">
                    @forelse($this->pedidosFinalizados as $pedido)
                        @include('components.pedidos-merenda.order-card', ['pedido' => $pedido, 'page' => $this])
                    @empty
                        <div class="pm-empty"><strong>Nenhum pedido finalizado para exibir.</strong>Ajuste os filtros ou habilite a visualizacao de cancelados.</div>
                    @endforelse
                </div>
            </div>
        </section>
    </div>
</div>

@if($modalItensAberto && $this->pedidoSelecionado)
    @include('components.pedidos-merenda.modal-itens-novo', ['pedido' => $this->pedidoSelecionado, 'page' => $this])
@endif
</x-filament-panels::page>
