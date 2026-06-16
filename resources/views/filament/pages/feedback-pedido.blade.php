<x-filament-panels::page>
    @php
        $metricas = $this->getMetricas();
        $empresas = $this->getRankingEmpresas();
        $escolas = $this->getRankingEscolas();
        $notas = $this->getDistribuicaoNotas();
        $maiorNota = max($notas ?: [1]);
    @endphp

    <div class="fb-page">
        <section class="fb-metrics">
            <article class="fb-metric fb-metric--blue">
                <span>Media geral</span>
                <strong>{{ number_format((float) $metricas['media'], 2, ',', '.') }}</strong>
                <small>escala de 1 a 5</small>
            </article>

            <article class="fb-metric fb-metric--green">
                <span>Satisfação</span>
                <strong>{{ $metricas['satisfacao'] }}%</strong>
                <small>media das notas em percentual</small>
            </article>

            <article class="fb-metric fb-metric--amber">
                <span>Avaliações</span>
                <strong>{{ $metricas['total'] }}</strong>
                <small>registros filtrados</small>
            </article>

            <article class="fb-metric fb-metric--red">
                <span>Criticas</span>
                <strong>{{ $metricas['criticas'] }}</strong>
                <small>{{ $metricas['reabertos'] }} reabertura(s)</small>
            </article>
        </section>

        <section class="fb-grid">
            <article class="fb-panel">
                <header class="fb-panel-head">
                    <div>
                        <p>Empresas</p>
                        <h2>Desempenho por contratada</h2>
                    </div>
                </header>

                <div class="fb-ranking {{ count($empresas) > 5 ? 'fb-ranking--scroll' : '' }}">
                    @forelse ($empresas as $empresa)
                        <div class="fb-rank-row">
                            <div>
                                <strong>{{ $empresa['nome'] }}</strong>
                                <small>{{ $empresa['total'] }} avaliação(oes) - media {{ number_format((float) $empresa['media'], 2, ',', '.') }}</small>
                            </div>
                            <span>{{ $empresa['satisfacao'] }}%</span>
                            <div class="fb-bar"><i style="width: {{ $empresa['pct_barra'] }}%"></i></div>
                        </div>
                    @empty
                        <p class="fb-empty">Sem empresas avaliadas nos filtros atuais.</p>
                    @endforelse
                </div>
            </article>

            <article class="fb-panel">
                <header class="fb-panel-head">
                    <div>
                        <p>Escolas</p>
                        <h2>Satisfação por escola</h2>
                    </div>
                </header>

                <div class="fb-ranking {{ count($escolas) > 5 ? 'fb-ranking--scroll' : '' }}">
                    @forelse ($escolas as $escola)
                        <div class="fb-rank-row">
                            <div>
                                <strong>{{ $escola['nome'] }}</strong>
                                <small>{{ $escola['total'] }} avaliação(oes) - {{ $escola['criticas'] }} critica(s)</small>
                            </div>
                            <span>{{ $escola['satisfacao'] }}%</span>
                            <div class="fb-bar fb-bar--green"><i style="width: {{ $escola['pct_barra'] }}%"></i></div>
                        </div>
                    @empty
                        <p class="fb-empty">Sem escolas avaliadas nos filtros atuais.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="fb-panel fb-panel--wide">
            <header class="fb-panel-head">
                <div>
                    <p>Notas</p>
                    <h2>Distribuição das avaliações</h2>
                </div>
            </header>

            <div class="fb-note-grid">
                @foreach ($notas as $nota => $total)
                    <div class="fb-note">
                        <div>
                            <strong>{{ $nota }}/5</strong>
                            <span>{{ $total }}</span>
                        </div>
                        <div class="fb-note-track">
                            <i style="width: {{ $maiorNota > 0 ? round(($total / $maiorNota) * 100) : 0 }}%"></i>
                        </div>
                    </div>
                @endforeach
            </div>
        </section>

        <section class="fb-panel fb-panel--wide">
            <header class="fb-panel-head">
                <div>
                    <p>Registros</p>
                    <h2>Avaliações detalhadas</h2>
                </div>
            </header>

            {{ $this->table }}
        </section>
    </div>

    <style>
        .fb-page {
            display: grid;
            gap: 1rem;
        }

        .fb-metrics {
            display: grid;
            gap: 1rem;
            grid-template-columns: repeat(1, minmax(0, 1fr));
        }

        @media (min-width: 640px) {
            .fb-metrics {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        @media (min-width: 1180px) {
            .fb-metrics {
                grid-template-columns: repeat(4, minmax(0, 1fr));
            }
        }

        .fb-metric,
        .fb-panel {
            border: 1px solid #e5e7eb;
            background: #fff;
            border-radius: .75rem;
            box-shadow: 0 1px 2px rgba(15, 23, 42, .04);
        }

        .dark .fb-metric,
        .dark .fb-panel {
            border-color: #374151;
            background: #111827;
        }

        .fb-metric {
            padding: 1rem 1.1rem;
        }

        .fb-metric span,
        .fb-panel-head p {
            display: block;
            font-size: .72rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            margin: 0 0 .35rem;
        }

        .dark .fb-metric span,
        .dark .fb-panel-head p {
            color: #94a3b8;
        }

        .fb-metric strong {
            display: block;
            font-size: clamp(1.45rem, 2vw, 2rem);
            line-height: 1;
        }

        .fb-metric small,
        .fb-rank-row small {
            color: #64748b;
            font-size: .78rem;
        }

        .fb-metric--blue strong { color: #2563eb; }
        .fb-metric--green strong { color: #16a34a; }
        .fb-metric--amber strong { color: #d97706; }
        .fb-metric--red strong { color: #dc2626; }

        .fb-grid {
            display: grid;
            gap: 1rem;
            grid-template-columns: minmax(0, 1fr);
        }

        @media (min-width: 1024px) {
            .fb-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }
        }

        .fb-panel {
            overflow: hidden;
        }

        .fb-panel--wide {
            min-width: 0;
        }

        .fb-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding: 1rem 1.1rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .dark .fb-panel-head {
            border-color: #374151;
        }

        .fb-panel-head h2 {
            margin: 0;
            font-size: 1rem;
            font-weight: 700;
            color: #0f172a;
        }

        .dark .fb-panel-head h2 {
            color: #f8fafc;
        }

        .fb-ranking {
            display: grid;
            gap: .8rem;
            padding: 1rem 1.1rem;
        }

        .fb-ranking--scroll {
            max-height: 27rem;
            overflow-y: auto;
            padding-right: .85rem;
            scrollbar-gutter: stable;
        }

        .fb-ranking--scroll::-webkit-scrollbar {
            width: .55rem;
        }

        .fb-ranking--scroll::-webkit-scrollbar-track {
            background: transparent;
        }

        .fb-ranking--scroll::-webkit-scrollbar-thumb {
            border-radius: 999px;
            background: #cbd5e1;
        }

        .dark .fb-ranking--scroll::-webkit-scrollbar-thumb {
            background: #475569;
        }

        .fb-rank-row {
            display: grid;
            grid-template-columns: minmax(0, 1fr) auto;
            gap: .45rem .75rem;
            align-items: center;
        }

        .fb-rank-row strong {
            display: block;
            overflow-wrap: anywhere;
            color: #111827;
        }

        .dark .fb-rank-row strong {
            color: #f9fafb;
        }

        .fb-rank-row > span {
            font-weight: 700;
            color: #2563eb;
        }

        .fb-bar {
            grid-column: 1 / -1;
            height: .45rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .dark .fb-bar {
            background: #374151;
        }

        .fb-bar i,
        .fb-note-track i {
            display: block;
            height: 100%;
            border-radius: inherit;
            background: #2563eb;
        }

        .fb-bar--green i {
            background: #16a34a;
        }

        .fb-note-grid {
            display: grid;
            grid-template-columns: repeat(1, minmax(0, 1fr));
            gap: .75rem;
            padding: 1rem 1.1rem;
        }

        @media (min-width: 800px) {
            .fb-note-grid {
                grid-template-columns: repeat(5, minmax(0, 1fr));
            }
        }

        .fb-note {
            display: grid;
            gap: .55rem;
            padding: .85rem;
            border: 1px solid #e5e7eb;
            border-radius: .65rem;
            background: #f8fafc;
        }

        .dark .fb-note {
            border-color: #374151;
            background: #1f2937;
        }

        .fb-note div:first-child {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .5rem;
        }

        .fb-note span {
            font-weight: 700;
            color: #0f172a;
        }

        .dark .fb-note span {
            color: #f8fafc;
        }

        .fb-note-track {
            height: .45rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e5e7eb;
        }

        .dark .fb-note-track {
            background: #374151;
        }

        .fb-empty {
            color: #64748b;
            margin: 0;
        }
    </style>
</x-filament-panels::page>
