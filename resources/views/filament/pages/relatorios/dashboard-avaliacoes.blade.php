<x-filament-panels::page>
    <div class="av-livewire-root">
    <div class="dav-page">

        <section class="dav-panel">
            <div class="dav-panel-head">
                <h3>Filtros Analíticos</h3>
                <button type="button" class="dav-action" wire:click="limparFiltros">
                    Limpar filtros
                </button>
            </div>
            {{ $this->filtrosForm }}

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

        @if (! $this->avaliacaoSelecionada())
            <section class="dav-empty-state">
                <h3>Selecione uma avaliação para carregar os indicadores.</h3>
                <p>Os filtros de série, turno, componente, escola, professor, pauta e alternativa serão liberados a partir da avaliação escolhida.</p>
            </section>
        @else
            @php
                $preenchimentosEsperados = max((int) ($cards['preenchimentos_esperados'] ?? 0), 0);
                $preenchimentosRespondidos = max((int) ($cards['preenchimentos_respondidos'] ?? 0), 0);
                $preenchimentosPendentes = max((int) ($cards['preenchimentos_pendentes'] ?? 0), 0);
                $percentualPreenchimentoGeral = (float) ($cards['percentual_preenchimento_geral'] ?? 0);
                $percentualTurmas = (float) ($cards['percentual_turmas_preenchidas'] ?? 0);
                $alunosPendentesManha = (int) ($cards['turno_manha_alunos_pendentes'] ?? 0);
                $alunosTotalManha = (int) ($cards['turno_manha_alunos_total'] ?? 0);
                $alunosPendentesTarde = (int) ($cards['turno_tarde_alunos_pendentes'] ?? 0);
                $alunosTotalTarde = (int) ($cards['turno_tarde_alunos_total'] ?? 0);
                $porPaginaOptions = $listagensPorPaginaOptions ?? [5, 10, 25, 50, 100];
                $montarPagina = function (array $items, string $key) use ($listagensPaginas, $listagensPorPagina, $porPaginaOptions): array {
                    $colecao = collect($items);
                    $total = $colecao->count();
                    $porPagina = (int) ($listagensPorPagina[$key] ?? 5);
                    $porPagina = in_array($porPagina, $porPaginaOptions, true) ? $porPagina : 5;
                    $ultimaPagina = max((int) ceil($total / max($porPagina, 1)), 1);
                    $pagina = min(max((int) ($listagensPaginas[$key] ?? 1), 1), $ultimaPagina);
                    $inicio = $total > 0 ? (($pagina - 1) * $porPagina) + 1 : 0;
                    $fim = min($total, $pagina * $porPagina);

                    return [
                        'items' => $colecao->forPage($pagina, $porPagina)->values(),
                        'total' => $total,
                        'porPagina' => $porPagina,
                        'pagina' => $pagina,
                        'ultimaPagina' => $ultimaPagina,
                        'inicio' => $inicio,
                        'fim' => $fim,
                    ];
                };
                $paginaTabelaEscolas = $montarPagina($tabelaEscolas, 'tabelaEscolas');
                $acompanhamentoUltimaPagina = max((int) ceil(($acompanhamentoTurmasTotal ?? 0) / max((int) $acompanhamentoTurmasPorPagina, 1)), 1);
                $acompanhamentoInicio = ($acompanhamentoTurmasTotal ?? 0) > 0
                    ? (((int) $acompanhamentoTurmasPagina - 1) * (int) $acompanhamentoTurmasPorPagina) + 1
                    : 0;
                $acompanhamentoFim = min((int) ($acompanhamentoTurmasTotal ?? 0), (int) $acompanhamentoTurmasPagina * (int) $acompanhamentoTurmasPorPagina);
            @endphp

            <section class="dav-kpi-grid">
                <article class="dav-kpi dav-kpi--blue">
                    <span class="dav-kpi-label">Preenchimento geral</span>
                    <strong>{{ number_format($percentualPreenchimentoGeral, 1, ',', '.') }}%</strong>
                    <small>{{ $preenchimentosRespondidos }} de {{ $preenchimentosEsperados }} preenchimentos</small>
                    <div class="dav-mini-track"><div class="dav-mini-fill" style="width: {{ min($percentualPreenchimentoGeral, 100) }}%;"></div></div>
                </article>
                <article class="dav-kpi dav-kpi--amber">
                    <span class="dav-kpi-label">Turmas incompletas</span>
                    <strong>{{ $cards['turmas_incompletas'] ?? 0 }}</strong>
                    <small>{{ $cards['turmas_preenchidas'] ?? 0 }} de {{ $cards['turmas_esperadas'] ?? 0 }} turmas concluídas</small>
                    <div class="dav-mini-track"><div class="dav-mini-fill dav-mini-fill--amber" style="width: {{ 100 - min($percentualTurmas, 100) }}%;"></div></div>
                </article>
                <article class="dav-kpi">
                    <span class="dav-kpi-label">Alunos pendentes - manhã</span>
                    <strong>{{ $alunosPendentesManha }}</strong>
                    <small>{{ $alunosTotalManha }} alunos no turno</small>
                    <div class="dav-mini-track"><div class="dav-mini-fill" style="width: {{ $alunosTotalManha > 0 ? min(($alunosPendentesManha / $alunosTotalManha) * 100, 100) : 0 }}%;"></div></div>
                </article>
                <article class="dav-kpi">
                    <span class="dav-kpi-label">Alunos pendentes - tarde</span>
                    <strong>{{ $alunosPendentesTarde }}</strong>
                    <small>{{ $alunosTotalTarde }} alunos no turno</small>
                    <div class="dav-mini-track"><div class="dav-mini-fill" style="width: {{ $alunosTotalTarde > 0 ? min(($alunosPendentesTarde / $alunosTotalTarde) * 100, 100) : 0 }}%;"></div></div>
                </article>
            </section>

            <section class="dav-chart-grid dav-chart-grid--three">
                <article class="dav-card dav-card--chart">
                    <header>
                        <h3>Pendência por escola</h3>
                        <p>Escolas com mais turmas de preenchimento incompleto.</p>
                    </header>

                    <div class="dav-bars dav-bars--risk">
                        @forelse ($turmasIncompletasPorEscola as $item)
                            <div class="dav-bar-row">
                                <div class="dav-bar-top">
                                    <span>{{ $item['nome'] }}</span>
                                    <strong>{{ $item['total'] }} turmas incompletas · {{ number_format((float) $item['percentual'], 1, ',', '.') }}%</strong>
                                </div>
                                <div class="dav-bar-track">
                                    <div class="dav-bar-fill dav-bar-fill--amber" style="width: {{ min((float) $item['percentual_barra'], 100) }}%;"></div>
                                </div>
                            </div>
                        @empty
                            <p class="dav-empty">Nenhuma turma incompleta no recorte atual.</p>
                        @endforelse
                    </div>
                </article>

                <article class="dav-card dav-card--chart">
                    <header>
                        <h3>Preenchimento por componente</h3>
                        <p>Percentual de preenchimento por componente curricular.</p>
                    </header>

                    <div class="dav-bars">
                        @forelse ($preenchimentoPorComponentes as $item)
                            <div class="dav-bar-row">
                                <div class="dav-bar-top">
                                    <span>{{ $item['nome'] }}</span>
                                    <strong>{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</strong>
                                </div>
                                <div class="dav-bar-track">
                                    <div class="dav-bar-fill" style="width: {{ min((float) $item['percentual_preenchimento'], 100) }}%;"></div>
                                </div>
                                <span class="dav-bar-note">{{ $item['preenchimentos_respondidos'] }} de {{ $item['preenchimentos_esperados'] }} preenchimentos</span>
                            </div>
                        @empty
                            <p class="dav-empty">Nenhum componente encontrado para o recorte atual.</p>
                        @endforelse
                    </div>
                </article>

                <article class="dav-card dav-card--chart">
                    <header>
                        <h3>Preenchimento por série</h3>
                        <p>Percentual de preenchimento por série no escopo atual.</p>
                    </header>

                    <div class="dav-bars">
                        @forelse ($preenchimentoPorSeries as $item)
                            <div class="dav-bar-row">
                                <div class="dav-bar-top">
                                    <span>{{ $item['nome'] }}</span>
                                    <strong>{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</strong>
                                </div>
                                <div class="dav-bar-track">
                                    <div class="dav-bar-fill" style="width: {{ min((float) $item['percentual_preenchimento'], 100) }}%;"></div>
                                </div>
                                <span class="dav-bar-note">{{ $item['preenchimentos_pendentes'] }} preenchimentos pendentes</span>
                            </div>
                        @empty
                            <p class="dav-empty">Nenhuma série encontrada para o recorte atual.</p>
                        @endforelse
                    </div>
                </article>
            </section>

            <section class="dav-card">
                <header class="dav-card-header--split">
                    <div>
                        <h3>Progresso por escola</h3>
                        <p>Turmas concluídas e incompletas por escola.</p>
                    </div>

                    <label class="dav-page-size">
                        <span>Itens por página</span>
                        <select wire:model.live="listagensPorPagina.tabelaEscolas" aria-label="Itens por página em progresso por escola">
                            @foreach ($porPaginaOptions as $opcao)
                                <option value="{{ $opcao }}">{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </label>
                </header>

                <div class="dav-table-wrap">
                    <table class="dav-table">
                        <thead>
                            <tr>
                                <th>Escola</th>
                                <th class="text-right">Turmas incompletas</th>
                                <th class="text-right">Concluídas</th>
                                <th class="text-right">No escopo</th>
                                <th class="text-right">% incompletas</th>
                                <th class="text-right">% preenchimento</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($paginaTabelaEscolas['items'] as $item)
                                <tr>
                                    <td>{{ $item['nome'] }}</td>
                                    <td class="text-right">{{ $item['turmas_incompletas'] }}</td>
                                    <td class="text-right">{{ $item['turmas_preenchidas'] }}</td>
                                    <td class="text-right">{{ $item['turmas_esperadas'] }}</td>
                                    <td class="text-right">
                                        <span class="dav-badge {{ $item['turmas_incompletas'] > 0 ? 'dav-badge--warn' : 'dav-badge--ok' }}">
                                            {{ number_format((float) $item['percentual_turmas_incompletas'], 1, ',', '.') }}%
                                        </span>
                                    </td>
                                    <td class="text-right">{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" class="dav-empty">Nenhuma escola encontrada para os filtros atuais.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if ($paginaTabelaEscolas['total'] > 0)
                    <div class="dav-pagination">
                        <span>Mostrando {{ $paginaTabelaEscolas['inicio'] }}-{{ $paginaTabelaEscolas['fim'] }} de {{ $paginaTabelaEscolas['total'] }} escolas</span>
                        <div class="dav-pagination-actions">
                            <button type="button" class="dav-page-button" wire:click="paginaAnteriorListagem('tabelaEscolas')" @disabled($paginaTabelaEscolas['pagina'] <= 1)>
                                Anterior
                            </button>
                            <span>Página {{ $paginaTabelaEscolas['pagina'] }} de {{ $paginaTabelaEscolas['ultimaPagina'] }}</span>
                            <button type="button" class="dav-page-button" wire:click="proximaPaginaListagem('tabelaEscolas')" @disabled($paginaTabelaEscolas['pagina'] >= $paginaTabelaEscolas['ultimaPagina'])>
                                Próxima
                            </button>
                        </div>
                    </div>
                @endif
            </section>

            <section class="dav-card">
                <header class="dav-card-header--split">
                    <div>
                        <h3>Acompanhamento de Pareceres</h3>
                        <p>Andamento por escola, série, turma, componente e professor.</p>
                    </div>

                    <label class="dav-page-size">
                        <span>Itens por página</span>
                        <select wire:model.live="acompanhamentoTurmasPorPagina" aria-label="Itens por página em acompanhamento de pareceres">
                            @foreach ($acompanhamentoTurmasPorPaginaOptions as $opcao)
                                <option value="{{ $opcao }}">{{ $opcao }}</option>
                            @endforeach
                        </select>
                    </label>
                </header>

                <div class="dav-table-wrap">
                    <table class="dav-table">
                        <thead>
                            <tr>
                                <th>Escola</th>
                                <th>Série</th>
                                <th>Turma</th>
                                <th>Componente</th>
                                <th>Professor</th>
                                <th class="text-right">Esperados</th>
                                <th class="text-right">Respondidos</th>
                                <th class="text-right">Pendentes</th>
                                <th class="text-right">% preenchimento</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($acompanhamentoTurmas as $item)
                                @php
                                    $statusClass = match ($item['status']) {
                                        'concluido' => 'dav-badge--ok',
                                        'em_andamento' => 'dav-badge--warn',
                                        default => 'dav-badge--danger',
                                    };
                                @endphp
                                <tr>
                                    <td>{{ $item['escola_nome'] }}</td>
                                    <td>{{ $item['serie_nome'] }}</td>
                                    <td>
                                        <strong>{{ $item['turma_nome'] }}</strong>
                                        <small>{{ ucfirst((string) $item['turno']) }}</small>
                                    </td>
                                    <td>{{ $item['componente_nome'] }}</td>
                                    <td>{{ $item['professor_nome'] }}</td>
                                    <td class="text-right">{{ $item['preenchimentos_esperados'] }}</td>
                                    <td class="text-right">{{ $item['preenchimentos_respondidos'] }}</td>
                                    <td class="text-right">{{ $item['preenchimentos_pendentes'] }}</td>
                                    <td class="text-right">{{ number_format((float) $item['percentual_preenchimento'], 1, ',', '.') }}%</td>
                                    <td>
                                        <span class="dav-badge {{ $statusClass }}">
                                            {{ $item['status_label'] }}
                                        </span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="10" class="dav-empty">Nenhuma turma encontrada para os filtros atuais.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (($acompanhamentoTurmasTotal ?? 0) > 0)
                    <div class="dav-pagination">
                        <span>Mostrando {{ $acompanhamentoInicio }}-{{ $acompanhamentoFim }} de {{ $acompanhamentoTurmasTotal }} registros</span>
                        <div class="dav-pagination-actions">
                            <button type="button" class="dav-page-button" wire:click="paginaAnteriorAcompanhamentoTurmas" @disabled($acompanhamentoTurmasPagina <= 1)>
                                Anterior
                            </button>
                            <span>Página {{ $acompanhamentoTurmasPagina }} de {{ $acompanhamentoUltimaPagina }}</span>
                            <button type="button" class="dav-page-button" wire:click="proximaPaginaAcompanhamentoTurmas" @disabled($acompanhamentoTurmasPagina >= $acompanhamentoUltimaPagina)>
                                Próxima
                            </button>
                        </div>
                    </div>
                @endif
            </section>
        @endif
    </div>
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

        .dav-mini-track {
            height: 0.38rem;
            overflow: hidden;
            border-radius: 999px;
            background: #e5edf7;
        }

        .dav-mini-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #175ea7 0%, #0f4e9b 100%);
        }

        .dav-mini-fill--amber {
            background: linear-gradient(90deg, #c77700 0%, #e0a11a 100%);
        }

        .dav-mini-fill--green {
            background: linear-gradient(90deg, #0f8f58 0%, #10b981 100%);
        }

        .dav-kpi--blue {
            border-color: #c7e4ff;
            background: #f4faff;
        }

        .dav-kpi--green {
            border-color: #bce9d0;
            background: #f2fbf6;
        }

        .dav-kpi--amber {
            border-color: #f4d68f;
            background: #fff9eb;
        }

        .dav-empty-state {
            border: 1px dashed var(--gray-300);
            border-radius: 0.95rem;
            background: #fff;
            padding: 1.4rem;
            display: grid;
            gap: 0.35rem;
            justify-items: center;
            text-align: center;
        }

        .dav-empty-state h3 {
            margin: 0;
            color: var(--gray-900);
            font-size: 1rem;
            font-weight: 700;
        }

        .dav-empty-state p {
            margin: 0;
            max-width: 48rem;
            color: var(--gray-600);
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .dav-chart-grid {
            display: grid;
            gap: 0.8rem;
            grid-template-columns: 1fr 1fr;
        }

        .dav-chart-grid--three {
            grid-template-columns: minmax(0, 1.25fr) minmax(0, 0.9fr) minmax(0, 0.95fr);
        }

        .dav-card {
            border: 1px solid var(--gray-200);
            border-radius: 0.95rem;
            background: #fff;
            padding: 0.9rem;
            display: grid;
            gap: 0.75rem;
        }

        .dav-card--chart {
            align-content: start;
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

        .dav-card-header--split {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 0.85rem;
        }

        .dav-page-size {
            display: grid;
            gap: 0.25rem;
            min-width: 9rem;
            color: var(--gray-600);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .dav-page-size select {
            min-height: 2.25rem;
            border-radius: 0.55rem;
            border: 1px solid var(--gray-300);
            background: #fff;
            color: var(--gray-900);
            padding: 0.35rem 0.5rem;
            font-size: 0.8rem;
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

        .dav-ring--amber {
            background: conic-gradient(#d99012 calc(var(--progress) * 1%), #f3e8cf 0);
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

        .dav-bar-fill--amber {
            background: linear-gradient(90deg, #c77700 0%, #e0a11a 100%);
        }

        .dav-bar-note {
            color: var(--gray-500);
            font-size: 0.7rem;
            line-height: 1.35;
        }

        .dav-status-grid {
            display: grid;
            gap: 0.6rem;
            grid-template-columns: repeat(3, minmax(0, 1fr));
        }

        .dav-status-item {
            min-height: 6rem;
            border-radius: 0.85rem;
            border: 1px solid var(--gray-200);
            background: #f8fafc;
            padding: 0.75rem;
            display: grid;
            align-content: center;
            gap: 0.25rem;
        }

        .dav-status-item span {
            color: var(--gray-600);
            font-size: 0.72rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
        }

        .dav-status-item strong {
            color: var(--gray-950);
            font-size: 1.45rem;
            line-height: 1;
        }

        .dav-status-item small {
            color: var(--gray-600);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .dav-status-item--ok {
            border-color: #bce9d0;
            background: #f2fbf6;
        }

        .dav-status-item--warn {
            border-color: #f4d68f;
            background: #fff9eb;
        }

        .dav-status-item--danger {
            border-color: #fac8c3;
            background: #fff7f5;
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

        .dav-link-action {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 1.7rem;
            padding: 0.2rem 0.6rem;
            border-radius: 0.45rem;
            border: 1px solid #bfd8fb;
            background: #eff6ff;
            color: #0f4e9b;
            font-size: 0.72rem;
            font-weight: 700;
            text-decoration: none;
            transition: all 0.15s ease;
        }

        .dav-link-action:hover {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }

        .dav-pagination {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 0.75rem;
            color: var(--gray-600);
            font-size: 0.78rem;
        }

        .dav-pagination-actions {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .dav-page-button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 2rem;
            padding: 0.25rem 0.7rem;
            border-radius: 0.5rem;
            border: 1px solid #bfd8fb;
            background: #eff6ff;
            color: #0f4e9b;
            font-size: 0.75rem;
            font-weight: 700;
            transition: all 0.15s ease;
        }

        .dav-page-button:hover:not(:disabled) {
            background: #dbeafe;
            border-color: #93c5fd;
            color: #1e40af;
        }

        .dav-page-button:disabled {
            cursor: not-allowed;
            opacity: 0.45;
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
        :root.dark .dav-empty-state,
        :root.dark .dav-card,
        :root.dark .dav-table-wrap {
            background: var(--gray-900);
            border-color: var(--gray-800);
        }

        :root.dark .dav-panel-head h3,
        :root.dark .dav-empty-state h3,
        :root.dark .dav-card header h3,
        :root.dark .dav-kpi strong,
        :root.dark .dav-bar-top strong {
            color: #fff;
        }

        :root.dark .dav-field span,
        :root.dark .dav-kpi-label,
        :root.dark .dav-card header p,
        :root.dark .dav-empty-state p,
        :root.dark .dav-bar-top,
        :root.dark .dav-bar-note,
        :root.dark .dav-ring-meta,
        :root.dark .dav-empty {
            color: var(--gray-300);
        }

        :root.dark .dav-field select {
            background: var(--gray-900);
            border-color: var(--gray-700);
            color: var(--gray-100);
        }

        :root.dark .dav-page-size select {
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

        :root.dark .dav-ring--amber {
            background: conic-gradient(#fbbf24 calc(var(--progress) * 1%), #3f3421 0);
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

        :root.dark .dav-mini-track {
            background: #223244;
        }

        :root.dark .dav-status-item {
            background: #111b2c;
            border-color: #273446;
        }

        :root.dark .dav-status-item strong {
            color: #fff;
        }

        :root.dark .dav-status-item span,
        :root.dark .dav-status-item small {
            color: var(--gray-300);
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

        :root.dark .dav-link-action {
            background: #17263a;
            border-color: #274161;
            color: #93c5fd;
        }

        :root.dark .dav-link-action:hover {
            background: #1f3550;
            border-color: #356091;
            color: #bfdbfe;
        }

        :root.dark .dav-page-button {
            background: #17263a;
            border-color: #274161;
            color: #93c5fd;
        }

        :root.dark .dav-page-button:hover:not(:disabled) {
            background: #1f3550;
            border-color: #356091;
            color: #bfdbfe;
        }

        @media (max-width: 1080px) {
            .dav-filters-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dav-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

            .dav-chart-grid--three {
                grid-template-columns: 1fr;
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

            .dav-status-grid {
                grid-template-columns: 1fr;
            }

            .dav-card-header--split,
            .dav-pagination {
                align-items: stretch;
                flex-direction: column;
            }

            .dav-pagination-actions {
                justify-content: space-between;
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
    </div>
</x-filament-panels::page>
