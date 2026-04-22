<x-filament-panels::page>
    <div class="dav-page">
        <section class="dav-hero">
            <div class="dav-hero-grid"></div>
            <div class="dav-hero-glow"></div>

            <div class="dav-hero-content">
                <p class="dav-eyebrow">Relatórios Pedagógicos</p>
                <h1>Dashboard Dinâmico de Avaliações</h1>
                <p>
                    Acompanhe preenchimento, cobertura por escola e distribuição de alternativas com filtros em tempo real.
                </p>
                <small>Atualizado em {{ $ultimaAtualizacao ?: '-' }}</small>
            </div>

            @if ($this->podeExportar)
                <div class="dav-hero-actions">
                    <button type="button" class="dav-action" wire:click="exportarPdf">
                        Exportar PDF
                    </button>
                    <button type="button" class="dav-action dav-action--primary" wire:click="exportarXlsx">
                        Exportar XLSX
                    </button>
                </div>
            @endif
        </section>

        <section class="dav-panel">
            <div class="dav-panel-head">
                <h3>Filtros Analíticos</h3>
                <button type="button" class="dav-action" wire:click="limparFiltros">
                    Limpar filtros
                </button>
            </div>

            <div class="dav-filters-grid">
                <label class="dav-field">
                    <span>Avaliação</span>
                    <select wire:model.live="filtros.avaliacao_id">
                        <option value="">Todas</option>
                        @foreach ($this->avaliacoesOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Período</span>
                    <select wire:model.live="filtros.periodo_id">
                        <option value="">Todos</option>
                        @foreach ($this->periodosOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Tipo</span>
                    <select wire:model.live="filtros.tipo_id">
                        <option value="">Todos</option>
                        @foreach ($this->tiposOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Status da avaliação</span>
                    <select wire:model.live="filtros.status">
                        @foreach ($this->statusOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Séries</span>
                    <select wire:model.live="filtros.series_ids" multiple size="5">
                        @foreach ($this->seriesOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Turnos</span>
                    <select wire:model.live="filtros.turnos" multiple size="5">
                        @foreach ($this->turnosOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Componentes</span>
                    <select wire:model.live="filtros.componentes_ids" multiple size="5">
                        @foreach ($this->componentesOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Escolas</span>
                    <select wire:model.live="filtros.escolas_ids" multiple size="5">
                        @foreach ($this->escolasOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Professores</span>
                    <select wire:model.live="filtros.professores_ids" multiple size="5">
                        @foreach ($this->professoresOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Pautas</span>
                    <select wire:model.live="filtros.pautas_ids" multiple size="5">
                        @foreach ($this->pautasOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="dav-field">
                    <span>Alternativas</span>
                    <select wire:model.live="filtros.alternativas_ids" multiple size="5">
                        @foreach ($this->alternativasOptions as $id => $label)
                            <option value="{{ $id }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            @if ($filtrosAplicados !== [])
                <div class="dav-filter-chips">
                    @foreach ($filtrosAplicados as $label => $valor)
                        <span class="dav-chip">
                            <strong>{{ $label }}:</strong> {{ $valor }}
                        </span>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="dav-kpi-grid">
            <article class="dav-kpi">
                <span class="dav-kpi-label">Avaliações no escopo</span>
                <strong>{{ $cards['total_avaliacoes'] ?? 0 }}</strong>
            </article>
            <article class="dav-kpi">
                <span class="dav-kpi-label">Avaliações ativas</span>
                <strong>{{ $cards['total_avaliacoes_ativas'] ?? 0 }}</strong>
            </article>
            <article class="dav-kpi">
                <span class="dav-kpi-label">Respostas registradas</span>
                <strong>{{ $cards['total_respostas'] ?? 0 }}</strong>
            </article>
            <article class="dav-kpi">
                <span class="dav-kpi-label">Escolas preenchidas</span>
                <strong>{{ $cards['escolas_preenchidas'] ?? 0 }} / {{ $cards['total_escolas'] ?? 0 }}</strong>
            </article>
            <article class="dav-kpi dav-kpi--blue">
                <span class="dav-kpi-label">% escolas preenchidas</span>
                <strong>{{ number_format((float) ($cards['percentual_escolas_preenchidas'] ?? 0), 1, ',', '.') }}%</strong>
            </article>
            <article class="dav-kpi dav-kpi--green">
                <span class="dav-kpi-label">% turmas preenchidas</span>
                <strong>{{ number_format((float) ($cards['percentual_turmas_preenchidas'] ?? 0), 1, ',', '.') }}%</strong>
                <small>{{ $cards['turmas_respondidas'] ?? 0 }} de {{ $cards['turmas_esperadas'] ?? 0 }} turmas</small>
            </article>
        </section>

        <section class="dav-chart-grid">
            <article class="dav-card">
                <header>
                    <h3>Preenchimento por Escola</h3>
                    <p>Percentual de escolas do escopo com ao menos uma turma respondida.</p>
                </header>

                @php($escolasPercent = (float) ($cards['percentual_escolas_preenchidas'] ?? 0))
                <div class="dav-ring-wrap">
                    <div class="dav-ring" style="--progress: {{ $escolasPercent }};">
                        <div class="dav-ring-inner">
                            <strong>{{ number_format($escolasPercent, 1, ',', '.') }}%</strong>
                        </div>
                    </div>
                    <div class="dav-ring-meta">
                        <span>
                            <strong>{{ $cards['escolas_preenchidas'] ?? 0 }}</strong> escolas com preenchimento
                        </span>
                        <span>
                            <strong>{{ $cards['escolas_nao_preenchidas'] ?? 0 }}</strong> escolas sem preenchimento
                        </span>
                    </div>
                </div>
            </article>

            <article class="dav-card">
                <header>
                    <h3>Avaliações Ativas por Escola</h3>
                    <p>Volume de avaliações ativas associadas por escola no recorte selecionado.</p>
                </header>

                <div class="dav-bars">
                    @forelse ($graficoAvaliacoesAtivasPorEscola as $item)
                        <div class="dav-bar-row">
                            <div class="dav-bar-top">
                                <span>{{ $item['nome'] }}</span>
                                <strong>{{ $item['total'] }}</strong>
                            </div>
                            <div class="dav-bar-track">
                                <div class="dav-bar-fill" style="width: {{ $item['percentual_barra'] }}%;"></div>
                            </div>
                        </div>
                    @empty
                        <p class="dav-empty">Sem dados para o recorte atual.</p>
                    @endforelse
                </div>
            </article>
        </section>

        <section class="dav-card">
            <header>
                <h3>{{ $distribuicaoAlternativas['titulo'] ?? 'Distribuição de alternativas' }}</h3>
                <p>
                    {{ $distribuicaoAlternativas['subtitulo'] ?? '' }}
                    @if (($distribuicaoAlternativas['total_respostas'] ?? 0) > 0)
                        ({{ $distribuicaoAlternativas['total_respostas'] }} respostas)
                    @endif
                </p>
            </header>

            <div class="dav-bars dav-bars--alt">
                @forelse (($distribuicaoAlternativas['itens'] ?? []) as $item)
                    <div class="dav-bar-row">
                        <div class="dav-bar-top">
                            <span>{{ $item['nome'] }}</span>
                            <strong>{{ $item['total'] }} ({{ number_format((float) $item['percentual'], 1, ',', '.') }}%)</strong>
                        </div>
                        <div class="dav-bar-track">
                            <div class="dav-bar-fill dav-bar-fill--alt" style="width: {{ $item['percentual_barra'] }}%;"></div>
                        </div>
                    </div>
                @empty
                    <p class="dav-empty">Nenhuma alternativa com respostas no recorte atual.</p>
                @endforelse
            </div>
        </section>

        <section class="dav-card">
            <header>
                <h3>Progresso por Escola</h3>
                <p>Turmas esperadas x turmas respondidas por escola.</p>
            </header>

            <div class="dav-table-wrap">
                <table class="dav-table">
                    <thead>
                        <tr>
                            <th>Escola</th>
                            <th class="text-right">Avaliações</th>
                            <th class="text-right">Avaliações resp.</th>
                            <th class="text-right">Turmas esperadas</th>
                            <th class="text-right">Turmas resp.</th>
                            <th class="text-right">% progresso</th>
                            <th class="text-right">Respostas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($tabelaEscolas as $item)
                            <tr>
                                <td>{{ $item['nome'] }}</td>
                                <td class="text-right">{{ $item['avaliacoes_no_escopo'] }}</td>
                                <td class="text-right">{{ $item['avaliacoes_respondidas'] }}</td>
                                <td class="text-right">{{ $item['turmas_esperadas'] }}</td>
                                <td class="text-right">{{ $item['turmas_respondidas'] }}</td>
                                <td class="text-right">
                                    <span class="dav-badge {{ $item['esta_preenchida'] ? 'dav-badge--ok' : 'dav-badge--muted' }}">
                                        {{ number_format((float) $item['percentual_turmas'], 1, ',', '.') }}%
                                    </span>
                                </td>
                                <td class="text-right">{{ $item['respostas_total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="dav-empty">Nenhuma escola encontrada para os filtros atuais.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dav-card">
            <header>
                <h3>Resumo por Avaliação</h3>
                <p>Visão consolidada de status, escolas, turmas e respostas.</p>
            </header>

            <div class="dav-table-wrap">
                <table class="dav-table">
                    <thead>
                        <tr>
                            <th>Avaliação</th>
                            <th>Tipo</th>
                            <th>Período</th>
                            <th>Status</th>
                            <th class="text-right">Escolas</th>
                            <th class="text-right">Escolas resp.</th>
                            <th class="text-right">% escolas</th>
                            <th class="text-right">Turmas</th>
                            <th class="text-right">Turmas resp.</th>
                            <th class="text-right">Respostas</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($avaliacoesResumo as $item)
                            <tr>
                                <td>
                                    <strong>{{ $item['nome'] }}</strong>
                                    <small>{{ $item['data_inicio'] }} até {{ $item['data_fim'] }}</small>
                                </td>
                                <td>{{ $item['tipo'] }}</td>
                                <td>{{ $item['periodo'] }}</td>
                                <td>
                                    @php
                                        $statusClass = match ($item['status']) {
                                            'ativa' => 'dav-badge--ok',
                                            'encerrada' => 'dav-badge--warn',
                                            'cancelada' => 'dav-badge--danger',
                                            default => 'dav-badge--muted',
                                        };
                                    @endphp
                                    <span class="dav-badge {{ $statusClass }}">
                                        {{ $item['status_label'] }}
                                    </span>
                                </td>
                                <td class="text-right">{{ $item['escolas_esperadas'] }}</td>
                                <td class="text-right">{{ $item['escolas_respondidas'] }}</td>
                                <td class="text-right">{{ number_format((float) $item['percentual_escolas'], 1, ',', '.') }}%</td>
                                <td class="text-right">{{ $item['turmas_esperadas'] }}</td>
                                <td class="text-right">{{ $item['turmas_respondidas'] }}</td>
                                <td class="text-right">{{ $item['respostas_total'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="10" class="dav-empty">Nenhuma avaliação encontrada para os filtros atuais.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>

    <style>
        .dav-page {
            display: grid;
            gap: 1rem;
            font-family: "Segoe UI", "Inter", sans-serif;
        }

        .dav-hero {
            position: relative;
            overflow: hidden;
            border-radius: 1rem;
            padding: 1.4rem 1.25rem;
            background: linear-gradient(135deg, #0f2a54 0%, #113d78 52%, #16508e 100%);
            border: 1px solid rgba(255, 255, 255, 0.16);
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
        }

        .dav-hero-grid {
            position: absolute;
            inset: 0;
            opacity: 0.24;
            background-image: linear-gradient(rgba(255, 255, 255, 0.24) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255, 255, 255, 0.24) 1px, transparent 1px);
            background-size: 36px 36px;
            pointer-events: none;
        }

        .dav-hero-glow {
            position: absolute;
            right: -120px;
            top: -100px;
            width: 280px;
            height: 280px;
            border-radius: 999px;
            background: radial-gradient(circle, rgba(115, 189, 255, 0.35) 0%, transparent 72%);
            pointer-events: none;
        }

        .dav-hero-content {
            position: relative;
            z-index: 1;
            display: grid;
            gap: 0.3rem;
            color: #fff;
            max-width: 58rem;
        }

        .dav-eyebrow {
            margin: 0;
            font-size: 0.7rem;
            letter-spacing: 0.09em;
            text-transform: uppercase;
            color: rgba(214, 240, 255, 0.95);
            font-weight: 700;
        }

        .dav-hero-content h1 {
            margin: 0;
            font-size: clamp(1.3rem, 2.2vw, 1.9rem);
            font-weight: 700;
            line-height: 1.2;
        }

        .dav-hero-content p {
            margin: 0;
            color: rgba(229, 242, 255, 0.9);
            line-height: 1.5;
            font-size: 0.92rem;
        }

        .dav-hero-content small {
            margin-top: 0.15rem;
            color: rgba(220, 239, 255, 0.82);
            font-size: 0.75rem;
        }

        .dav-hero-actions {
            position: relative;
            z-index: 1;
            display: flex;
            gap: 0.55rem;
            flex-wrap: wrap;
            justify-content: flex-end;
        }

        .dav-action {
            min-height: 2.35rem;
            padding: 0.45rem 0.85rem;
            border-radius: 0.7rem;
            border: 1px solid var(--gray-300);
            background: #fff;
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.15s ease, box-shadow 0.15s ease, border-color 0.15s ease;
        }

        .dav-action:hover {
            transform: translateY(-1px);
            box-shadow: 0 6px 16px rgba(2, 6, 23, 0.12);
        }

        .dav-action--primary {
            border-color: #0f4e9b;
            background: #0f4e9b;
            color: #fff;
        }

        .dav-panel {
            padding: 0.9rem;
            border: 1px solid var(--gray-200);
            border-radius: 1rem;
            background: #fff;
            display: grid;
            gap: 0.85rem;
        }

        .dav-panel-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.6rem;
        }

        .dav-panel-head h3 {
            margin: 0;
            color: var(--gray-900);
            font-size: 0.95rem;
            font-weight: 700;
        }

        .dav-filters-grid {
            display: grid;
            gap: 0.7rem;
            grid-template-columns: repeat(4, minmax(0, 1fr));
        }

        .dav-field {
            display: grid;
            gap: 0.28rem;
        }

        .dav-field span {
            font-size: 0.73rem;
            color: var(--gray-600);
            font-weight: 600;
        }

        .dav-field select {
            min-height: 2.45rem;
            width: 100%;
            border-radius: 0.65rem;
            border: 1px solid var(--gray-300);
            background: #fff;
            color: var(--gray-900);
            padding: 0.45rem 0.55rem;
            font-size: 0.82rem;
        }

        .dav-field select[multiple] {
            min-height: 5.5rem;
        }

        .dav-filter-chips {
            display: flex;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .dav-chip {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.3rem 0.55rem;
            border-radius: 999px;
            border: 1px solid #bfdbfe;
            background: #eff6ff;
            color: #1e3a8a;
            font-size: 0.72rem;
        }

        .dav-kpi-grid {
            display: grid;
            gap: 0.7rem;
            grid-template-columns: repeat(6, minmax(0, 1fr));
        }

        .dav-kpi {
            border: 1px solid var(--gray-200);
            border-radius: 0.85rem;
            padding: 0.75rem 0.85rem;
            background: #fff;
            display: grid;
            gap: 0.25rem;
        }

        .dav-kpi-label {
            color: var(--gray-600);
            font-size: 0.72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .dav-kpi strong {
            color: var(--gray-950);
            font-size: 1.15rem;
            line-height: 1.15;
        }

        .dav-kpi small {
            color: var(--gray-600);
            font-size: 0.72rem;
        }

        .dav-kpi--blue {
            border-color: #c7e4ff;
            background: #f4faff;
        }

        .dav-kpi--green {
            border-color: #bce9d0;
            background: #f2fbf6;
        }

        .dav-chart-grid {
            display: grid;
            gap: 0.8rem;
            grid-template-columns: 1fr 1fr;
        }

        .dav-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.95rem;
            background: #fff;
            padding: 0.9rem;
            display: grid;
            gap: 0.75rem;
        }

        .dav-card header h3 {
            margin: 0;
            color: var(--gray-900);
            font-size: 0.95rem;
            font-weight: 700;
        }

        .dav-card header p {
            margin: 0.25rem 0 0;
            color: var(--gray-600);
            font-size: 0.78rem;
            line-height: 1.5;
        }

        .dav-ring-wrap {
            display: grid;
            gap: 0.75rem;
            justify-items: center;
        }

        .dav-ring {
            --size: 146px;
            width: var(--size);
            height: var(--size);
            border-radius: 999px;
            position: relative;
            display: grid;
            place-items: center;
            background: conic-gradient(#0f63b8 calc(var(--progress) * 1%), #dbe7f4 0);
        }

        .dav-ring-inner {
            width: calc(var(--size) - 26px);
            height: calc(var(--size) - 26px);
            border-radius: inherit;
            background: #fff;
            display: grid;
            place-items: center;
            box-shadow: inset 0 0 0 1px #d8e3ef;
        }

        .dav-ring-inner strong {
            font-size: 1.2rem;
            color: #0f4e9b;
        }

        .dav-ring-meta {
            width: 100%;
            display: grid;
            gap: 0.35rem;
            justify-items: center;
            color: var(--gray-700);
            font-size: 0.8rem;
        }

        .dav-ring-meta strong {
            color: var(--gray-950);
        }

        .dav-bars {
            display: grid;
            gap: 0.55rem;
        }

        .dav-bar-row {
            display: grid;
            gap: 0.25rem;
        }

        .dav-bar-top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.7rem;
            color: var(--gray-700);
            font-size: 0.78rem;
        }

        .dav-bar-top strong {
            color: var(--gray-950);
            font-size: 0.75rem;
        }

        .dav-bar-track {
            height: 0.48rem;
            border-radius: 999px;
            background: #e5edf7;
            overflow: hidden;
        }

        .dav-bar-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #175ea7 0%, #0f4e9b 100%);
        }

        .dav-bar-fill--alt {
            background: linear-gradient(90deg, #0f766e 0%, #0f9c8d 100%);
        }

        .dav-table-wrap {
            overflow: auto;
            border-radius: 0.75rem;
            border: 1px solid var(--gray-200);
        }

        .dav-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 860px;
        }

        .dav-table th {
            text-align: left;
            padding: 0.55rem 0.65rem;
            font-size: 0.72rem;
            letter-spacing: 0.04em;
            text-transform: uppercase;
            color: var(--gray-600);
            background: #f8fafc;
            border-bottom: 1px solid var(--gray-200);
        }

        .dav-table td {
            padding: 0.58rem 0.65rem;
            border-bottom: 1px solid var(--gray-100);
            font-size: 0.8rem;
            color: var(--gray-800);
            vertical-align: middle;
        }

        .dav-table td small {
            display: block;
            margin-top: 0.15rem;
            color: var(--gray-500);
            font-size: 0.7rem;
        }

        .dav-table tr:last-child td {
            border-bottom: 0;
        }

        .text-right {
            text-align: right !important;
        }

        .dav-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 1.45rem;
            padding: 0.12rem 0.48rem;
            border-radius: 999px;
            border: 1px solid transparent;
            font-size: 0.7rem;
            font-weight: 700;
        }

        .dav-badge--ok {
            background: #eaf9ef;
            color: #0f7a38;
            border-color: #ccefd8;
        }

        .dav-badge--warn {
            background: #fff7e6;
            color: #a26706;
            border-color: #f8deb0;
        }

        .dav-badge--danger {
            background: #fff0ef;
            color: #c22c1e;
            border-color: #fac8c3;
        }

        .dav-badge--muted {
            background: #f3f4f6;
            color: #5f6774;
            border-color: #d7dce2;
        }

        .dav-empty {
            margin: 0;
            color: var(--gray-500);
            font-size: 0.8rem;
            padding: 0.3rem 0;
            text-align: center;
        }

        :root.dark .dav-panel,
        :root.dark .dav-kpi,
        :root.dark .dav-card,
        :root.dark .dav-table-wrap {
            background: var(--gray-900);
            border-color: var(--gray-800);
        }

        :root.dark .dav-panel-head h3,
        :root.dark .dav-card header h3,
        :root.dark .dav-kpi strong,
        :root.dark .dav-bar-top strong {
            color: #fff;
        }

        :root.dark .dav-field span,
        :root.dark .dav-kpi-label,
        :root.dark .dav-card header p,
        :root.dark .dav-bar-top,
        :root.dark .dav-ring-meta,
        :root.dark .dav-empty {
            color: var(--gray-300);
        }

        :root.dark .dav-field select {
            background: var(--gray-900);
            border-color: var(--gray-700);
            color: var(--gray-100);
        }

        :root.dark .dav-chip {
            background: color-mix(in oklab, #1e3a8a 24%, #030712);
            border-color: color-mix(in oklab, #1e3a8a 38%, #1f2937);
            color: #bfdbfe;
        }

        :root.dark .dav-ring {
            background: conic-gradient(#60a5fa calc(var(--progress) * 1%), #31465a 0);
        }

        :root.dark .dav-ring-inner {
            background: #0b1323;
            box-shadow: inset 0 0 0 1px #243247;
        }

        :root.dark .dav-ring-inner strong {
            color: #93c5fd;
        }

        :root.dark .dav-bar-track {
            background: #223244;
        }

        :root.dark .dav-table th {
            background: #111b2c;
            border-bottom-color: #273446;
            color: var(--gray-300);
        }

        :root.dark .dav-table td {
            border-bottom-color: #1b2738;
            color: var(--gray-200);
        }

        :root.dark .dav-table td small {
            color: var(--gray-400);
        }

        @media (max-width: 1080px) {
            .dav-filters-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dav-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }

        @media (max-width: 760px) {
            .dav-hero {
                padding: 1rem;
                display: grid;
            }

            .dav-hero-actions {
                justify-content: start;
            }

            .dav-filters-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dav-kpi-grid {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .dav-chart-grid {
                grid-template-columns: 1fr;
            }
        }

        @media (max-width: 520px) {
            .dav-filters-grid {
                grid-template-columns: 1fr;
            }

            .dav-kpi-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</x-filament-panels::page>
