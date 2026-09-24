<x-filament-panels::page>
    <div
        class="av-livewire-root"
        x-data="{
            async carregarDashboardInicialCompleto() {
                await $wire.carregarDashboardInicialCompleto()
            },
        }"
        x-init="carregarDashboardInicialCompleto()"
    >
    <div class="dav-page">
        <div class="dav-processing-overlay" wire:loading.flex wire:target="carregarDashboardInicialCompleto,carregarDashboardInicial,carregarResumoDashboard,carregarAcompanhamentoDashboard,atualizarAcompanhamentoTurmas,atualizarDadosRecentes">
            <div class="dav-processing-card">
                <div class="dav-processing-spinner"></div>
                <strong>Processando...</strong>
                <span>Aguarde enquanto a ação é concluída.</span>
            </div>
        </div>

        @if (! $this->avaliacaoSelecionada())
            <section class="dav-empty-state">
                <h3>Nenhuma avaliação selecionada.</h3>
                <p>Abra o acompanhamento a partir da avaliação desejada para carregar os indicadores e as turmas autorizadas.</p>
            </section>
        @else
            @php
                $preenchimentosEsperados = max((int) ($cards['preenchimentos_esperados'] ?? 0), 0);
                $preenchimentosRespondidos = max((int) ($cards['preenchimentos_respondidos'] ?? 0), 0);
                $percentualPreenchimentoGeral = (float) ($cards['percentual_preenchimento_geral'] ?? 0);
                $acompanhamentoUltimaPagina = max((int) ceil(($acompanhamentoTurmasTotal ?? 0) / max((int) $acompanhamentoTurmasPorPagina, 1)), 1);
                $acompanhamentoInicio = ($acompanhamentoTurmasTotal ?? 0) > 0
                    ? (((int) $acompanhamentoTurmasPagina - 1) * (int) $acompanhamentoTurmasPorPagina) + 1
                    : 0;
                $acompanhamentoFim = min((int) ($acompanhamentoTurmasTotal ?? 0), (int) $acompanhamentoTurmasPagina * (int) $acompanhamentoTurmasPorPagina);
                $avaliacaoSelecionada = $this->avaliacaoSelecionada();
            @endphp

            @if (! $dashboardCarregado)
                <section class="dav-dashboard-loading">
                    <div class="dav-dashboard-loading__pulse"></div>
                    <div>
                        <strong>Carregando indicadores...</strong>
                        <span>Aguarde enquanto os indicadores são carregados.</span>
                    </div>
                </section>
            @else

            <section class="dav-kpi-grid dav-kpi-grid--overview">
                <article class="dav-kpi dav-kpi--blue">
                    <span class="dav-kpi-label">Preenchimento geral</span>
                    <strong>{{ number_format($percentualPreenchimentoGeral, 1, ',', '.') }}%</strong>
                    <small>{{ $preenchimentosRespondidos }} de {{ $preenchimentosEsperados }} preenchimentos</small>
                    <div class="dav-mini-track"><div class="dav-mini-fill" style="width: {{ min($percentualPreenchimentoGeral, 100) }}%;"></div></div>
                </article>
                <article class="dav-kpi dav-kpi--green">
                    <span class="dav-kpi-label">Turmas completas</span>
                    <strong>{{ (int) ($cards['turmas_completas'] ?? 0) }}</strong>
                    <small>de {{ (int) ($cards['turmas_total'] ?? 0) }} turmas no escopo</small>
                    <div class="dav-mini-track"><div class="dav-mini-fill dav-mini-fill--green" style="width: {{ ($cards['turmas_total'] ?? 0) > 0 ? min(((int) ($cards['turmas_completas'] ?? 0) / (int) $cards['turmas_total']) * 100, 100) : 0 }}%;"></div></div>
                </article>
                <article class="dav-kpi">
                    <span class="dav-kpi-label">Alunos pendentes — manhã</span>
                    <strong>{{ (int) ($cards['turno_manha_alunos_pendentes'] ?? 0) }}</strong>
                    <small>{{ (int) ($cards['turno_manha_alunos_total'] ?? 0) }} alunos no turno</small>
                </article>
                <article class="dav-kpi">
                    <span class="dav-kpi-label">Alunos pendentes — tarde</span>
                    <strong>{{ (int) ($cards['turno_tarde_alunos_pendentes'] ?? 0) }}</strong>
                    <small>{{ (int) ($cards['turno_tarde_alunos_total'] ?? 0) }} alunos no turno</small>
                </article>
            </section>

            <section class="dav-card">
                <header class="dav-card-header--split">
                    <div>
                        <h3>Turmas da avaliação</h3>
                        <p>
                            Consulte o status e as ações disponíveis para cada turma.
                            @if ($ultimaAtualizacaoIncremental ?? false)
                                <span class="dav-refresh-meta">Última verificação: {{ $ultimaAtualizacaoIncremental }}</span>
                            @elseif ($ultimaAtualizacao ?? false)
                                <span class="dav-refresh-meta">Carregado em: {{ $ultimaAtualizacao }}</span>
                            @endif
                        </p>
                    </div>

                    <div class="dav-card-header-actions">
                        <label class="dav-page-size">
                            <span>Itens por página</span>
                            <select wire:model.live="acompanhamentoTurmasPorPagina" aria-label="Itens por página em acompanhamento de pareceres">
                                @foreach ($acompanhamentoTurmasPorPaginaOptions as $opcao)
                                    <option value="{{ $opcao }}">{{ $opcao }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                </header>

                <div class="dav-acompanhamento-filters">
                    {{ $this->filtrosAcompanhamentoForm }}
                </div>

                <div class="dav-table-wrap" wire:key="acompanhamento-tabela-{{ $ultimaAtualizacaoIncremental ?: $ultimaAtualizacao }}">
                    <table class="dav-table">
                        <thead>
                            <tr>
                                <th>Escola</th>
                                <th>Série</th>
                                <th>Turma</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($acompanhamentoTurmas as $item)
                                @php
                                    $statusClass = match ($item['status']) {
                                        'concluido' => 'dav-badge--ok',
                                        'preenchido' => 'dav-badge--filled',
                                        'em_andamento' => 'dav-badge--warn',
                                        default => 'dav-badge--danger',
                                    };
                                    $linhaAlterada = ! empty($item['alterado_recentemente']);
                                @endphp
                            <tr class="{{ $linhaAlterada ? 'dav-row-changed' : '' }}{{ $item['status'] === 'concluido' ? ' dav-row-completed' : '' }}" @if($linhaAlterada) title="Status atualizado nesta verificação" @endif>
                                    <td>{{ $item['escola_nome'] }}</td>
                                    <td>{{ $item['serie_nome'] }}</td>
                                    <td>
                                        <strong>{{ $item['turma_nome'] }}</strong>
                                        <small>{{ ucfirst((string) $item['turno']) }}</small>
                                    </td>
                                    <td>
                                        <div class="dav-status-actions">
                                            <span class="dav-badge {{ $statusClass }}{{ $linhaAlterada ? ' dav-badge--flash' : '' }}">
                                                {{ $item['status_label'] }}
                                            </span>
                                            <button
                                                type="button"
                                                class="dav-link-action"
                                                wire:click="abrirWorkspaceAcompanhamento({{ $item['avaliacao_id'] }}, {{ $item['turma_id'] }}, {{ $item['escola_id'] }}, {{ $item['serie_id'] }}, {{ $item['componente_id'] }}, {{ $item['professor_id'] }})"
                                                wire:loading.attr="disabled"
                                                wire:target="abrirWorkspaceAcompanhamento">
                                                Abrir avaliação
                                            </button>
                                            @if ($item['status'] === 'concluido' && $this->podeExportarParecer)
                                                <button
                                                    type="button"
                                                    class="dav-link-action"
                                                    title="Responsáveis serão validados ao solicitar a exportação"
                                                    wire:click="exportarParecerTurma({{ $item['avaliacao_id'] }}, {{ $item['turma_id'] }}, {{ $item['escola_id'] }}, {{ $item['serie_id'] }}, {{ $item['componente_id'] }}, {{ $item['professor_id'] }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="exportarParecerTurma">
                                                    Exportar parecer
                                                </button>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="dav-empty">Nenhuma turma encontrada para os filtros atuais.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>

                @if (($acompanhamentoTurmasTotal ?? 0) > 0)
                    <div class="dav-pagination">
                        <span>Mostrando {{ $acompanhamentoInicio }}-{{ $acompanhamentoFim }} de {{ $acompanhamentoTurmasTotal }} turmas</span>
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

            @if ($workspaceAcompanhamentoAberto && $workspaceAcompanhamentoLinha)
                <div class="dav-slideover-shell" x-data="{ open: true }" x-show="open" x-transition.opacity x-on:keydown.escape.window="open = false; $wire.fecharWorkspaceAcompanhamento()" role="dialog" aria-modal="true">
                    <button type="button" class="dav-slideover-backdrop" x-on:click="open = false; $wire.fecharWorkspaceAcompanhamento()" aria-label="Fechar modal de avaliação"></button>

                    <section class="dav-slideover-panel{{ ($workspaceAcompanhamentoLinha['status'] ?? null) === 'concluido' ? ' dav-slideover-panel--completed' : '' }}">
                        <header class="dav-slideover-header">
                            <div>
                                <p class="dav-slideover-eyebrow">Acompanhamento de Pareceres</p>
                                <h3>{{ $workspaceAcompanhamentoLinha['avaliacao_nome'] }}</h3>
                                <p class="dav-slideover-meta">
                                    {{ $workspaceAcompanhamentoLinha['escola_nome'] }}
                                    | {{ $workspaceAcompanhamentoLinha['serie_nome'] }}
                                    | {{ $workspaceAcompanhamentoLinha['turma_nome'] }}
                                </p>
                                @if (($workspaceAcompanhamentoLinha['status'] ?? null) === 'concluido')
                                    <span class="dav-status-badge dav-status-badge--success">Avaliação concluída — somente leitura</span>
                                @endif
                            </div>

                            <button type="button" class="dav-slideover-close" x-on:click="open = false; $wire.fecharWorkspaceAcompanhamento()" aria-label="Fechar">
                                ×
                            </button>
                        </header>

                        <div class="dav-slideover-body">
                            @livewire(
                                'avaliacoes.avaliacao-turma-workspace',
                                [
                                    'avaliacaoId' => (int) $workspaceAcompanhamentoLinha['avaliacao_id'],
                                    'turmaId' => (int) $workspaceAcompanhamentoLinha['turma_id'],
                                    'escolaId' => (int) $workspaceAcompanhamentoLinha['escola_id'],
                                    'serieId' => (int) $workspaceAcompanhamentoLinha['serie_id'],
                                    'modo' => 'acompanhamento',
                                    'canEdit' => true,
                                    'lazy' => 'on-load',
                                ],
                                key('acompanhamento-workspace-' . $workspaceAcompanhamentoKey)
                            )
                        </div>

                        <footer class="dav-slideover-footer">
                            @if ($this->podeReabrirParecerWorkspace)
                                <button type="button" class="dav-action dav-action--warning" wire:click="reabrirParecerTurma" wire:loading.attr="disabled" wire:target="reabrirParecerTurma">
                                    <span wire:loading.remove wire:target="reabrirParecerTurma">Reabrir avaliação</span>
                                    <span wire:loading wire:target="reabrirParecerTurma">Reabrindo...</span>
                                </button>
                            @endif
                            @if ($this->podeConcluirParecerWorkspace)
                                <button
                                    type="button"
                                    class="dav-action dav-action--primary"
                                    wire:click="concluirParecerTurma"
                                    wire:loading.attr="disabled"
                                    wire:target="concluirParecerTurma"
                                >
                                    <span wire:loading.remove wire:target="concluirParecerTurma">Concluir avaliação</span>
                                    <span wire:loading wire:target="concluirParecerTurma">Concluindo...</span>
                                </button>
                            @endif

                            <button type="button" class="dav-action" x-on:click="open = false; $wire.fecharWorkspaceAcompanhamento()">
                                Fechar
                            </button>
                        </footer>
                    </section>
                </div>
            @endif
            @endif
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

        .dav-panel-head p {
            margin: 0.2rem 0 0;
            color: var(--gray-600);
            font-size: 0.78rem;
            line-height: 1.5;
        }

        .dav-processing-overlay {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(15, 23, 42, 0.22);
            backdrop-filter: blur(2px);
        }

        .dav-processing-card {
            width: min(22rem, calc(100vw - 2rem));
            border-radius: 1rem;
            border: 1px solid #dbe7f4;
            background: #fff;
            box-shadow: 0 24px 70px rgba(15, 23, 42, 0.16);
            padding: 1.4rem 1.25rem;
            display: grid;
            justify-items: center;
            gap: 0.45rem;
            text-align: center;
        }

        .dav-processing-card strong {
            color: var(--gray-950);
            font-size: 1.05rem;
            font-weight: 700;
        }

        .dav-processing-card span {
            color: var(--gray-600);
            font-size: 0.82rem;
        }

        .dav-processing-spinner {
            width: 2.2rem;
            height: 2.2rem;
            border-radius: 999px;
            border: 3px solid #dbe7f4;
            border-top-color: #2f5fd0;
            animation: dav-spin 0.75s linear infinite;
        }

        .dav-dashboard-loading {
            display: flex;
            align-items: center;
            gap: 0.85rem;
            margin-bottom: 1rem;
            border: 1px solid #bfdbfe;
            border-radius: 0.95rem;
            background: linear-gradient(135deg, #eff6ff 0%, #f8fbff 100%);
            padding: 0.95rem 1rem;
            color: #1e3a8a;
            box-shadow: 0 12px 36px rgba(37, 99, 235, 0.08);
        }

        .dav-dashboard-loading strong,
        .dav-dashboard-loading span {
            display: block;
        }

        .dav-dashboard-loading strong {
            font-size: 0.92rem;
            font-weight: 800;
        }

        .dav-dashboard-loading span {
            margin-top: 0.12rem;
            font-size: 0.78rem;
            color: #315a9b;
        }

        .dav-dashboard-loading__pulse {
            width: 0.85rem;
            height: 0.85rem;
            border-radius: 999px;
            background: #2563eb;
            box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.38);
            animation: dav-pulse 1.25s ease-out infinite;
            flex: 0 0 auto;
        }

        @keyframes dav-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @keyframes dav-pulse {
            to {
                box-shadow: 0 0 0 0.55rem rgba(37, 99, 235, 0);
            }
        }

        .dav-panel--legacy,
        .dav-filters-grid--legacy {
            display: none;
        }

        .dav-acompanhamento-filters {
            border: 1px solid var(--gray-200);
            border-radius: 0.85rem;
            background: #f8fbff;
            padding: 0.85rem;
        }

        .dav-acompanhamento-filters .fi-fo {
            gap: 0.75rem;
        }

        .dav-card-header-actions {
            display: flex;
            align-items: end;
            gap: 0.75rem;
            flex-wrap: wrap;
        }

        .dav-action--refresh {
            white-space: nowrap;
            background: #eff6ff;
            border-color: #93c5fd;
            color: #1d4ed8;
            font-weight: 600;
        }

        .dav-action--refresh:hover {
            background: #dbeafe;
            border-color: #60a5fa;
        }

        .dav-refresh-meta {
            display: inline-block;
            margin-left: 0.35rem;
            color: var(--gray-500);
            font-size: 0.78rem;
        }

        .dav-row-changed {
            background: linear-gradient(90deg, rgba(34, 197, 94, 0.12), rgba(34, 197, 94, 0.03));
            animation: dav-row-highlight 1.4s ease-out;
        }

        .dav-cell-changed {
            font-weight: 700;
            color: #166534;
        }

        .dav-changed-dot {
            display: inline-block;
            width: 0.45rem;
            height: 0.45rem;
            margin-left: 0.3rem;
            border-radius: 999px;
            background: #22c55e;
            box-shadow: 0 0 0 0 rgba(34, 197, 94, 0.55);
            animation: dav-pulse 1.4s ease-out 2;
            vertical-align: middle;
        }

        .dav-badge--flash {
            box-shadow: 0 0 0 0.15rem rgba(34, 197, 94, 0.25);
        }

        @keyframes dav-row-highlight {
            from {
                background: rgba(34, 197, 94, 0.28);
            }
            to {
                background: linear-gradient(90deg, rgba(34, 197, 94, 0.12), rgba(34, 197, 94, 0.03));
            }
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
            grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        }

        .dav-kpi-grid--overview {
            grid-template-columns: repeat(4, minmax(0, 1fr));
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

        .dav-chart-board {
            border: 1px solid #dbe7f4;
            border-radius: 1.15rem;
            background: linear-gradient(135deg, #f8fbff 0%, #ffffff 52%, #f7fbff 100%);
            padding: 1rem;
            display: grid;
            gap: 1rem;
        }

        .dav-chart-board__intro {
            display: flex;
            align-items: end;
            justify-content: space-between;
            gap: 1rem;
            padding: 0.15rem 0.2rem 0;
        }

        .dav-chart-board__intro h2 {
            margin: 0.2rem 0 0;
            color: #0f1d35;
            font-size: 1.15rem;
            font-weight: 800;
            letter-spacing: -0.02em;
        }

        .dav-chart-board__intro p {
            margin: 0.25rem 0 0;
            color: var(--gray-600);
            font-size: 0.8rem;
        }

        .dav-kicker {
            color: #2563eb;
            font-size: 0.68rem;
            font-weight: 800;
            letter-spacing: 0.08em;
        }

        .dav-chart-board__legend {
            display: flex;
            flex-wrap: wrap;
            justify-content: end;
            gap: 0.75rem;
            color: var(--gray-600);
            font-size: 0.72rem;
            font-weight: 600;
        }

        .dav-chart-board__legend span {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
        }

        .dav-legend-dot {
            width: 0.52rem;
            height: 0.52rem;
            border-radius: 999px;
            display: inline-block;
        }

        .dav-legend-dot--blue { background: #2563eb; }
        .dav-legend-dot--muted { background: #d5dce9; }

        .dav-chart-card {
            min-width: 0;
            border-color: #d8e4f1;
            box-shadow: 0 0.5rem 1.5rem rgba(30, 64, 175, 0.05);
        }

        .dav-chart-card--risk {
            border-top: 3px solid #e0a11a;
        }

        .dav-analytics {
            border: 1px solid #d9e5f3;
            border-radius: 1.25rem;
            padding: 1.25rem;
            background: #f8fbff;
        }

        .dav-analytics__header,
        .dav-analytics-card__header,
        .dav-ranking__label,
        .dav-risk-list__item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
        }

        .dav-analytics__header { padding: 0.1rem 0.15rem 1.1rem; }
        .dav-analytics__header h2 { margin: 0.2rem 0 0; color: #10203b; font-size: 1.2rem; font-weight: 800; letter-spacing: -0.025em; }
        .dav-analytics__header p,
        .dav-analytics-card__header p { margin: 0.25rem 0 0; color: var(--gray-600); font-size: 0.78rem; }
        .dav-analytics__headline { min-width: 9rem; padding: 0.75rem 1rem; border: 1px solid #cfe0f5; border-radius: 0.9rem; background: #fff; text-align: right; }
        .dav-analytics__headline strong { display: block; color: #1d4ed8; font-size: 1.35rem; line-height: 1; }
        .dav-analytics__headline span { display: block; margin-top: 0.35rem; color: var(--gray-600); font-size: 0.7rem; }
        .dav-analytics__grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0.8rem; }
        .dav-analytics-card { min-width: 0; padding: 1rem; border: 1px solid #d8e4f1; border-radius: 1rem; background: #fff; }
        .dav-analytics-card--risk { grid-column: 1 / -1; border-left: 3px solid #e0a11a; }
        .dav-analytics-card__header { align-items: flex-start; margin-bottom: 0.85rem; }
        .dav-analytics-card__header h3 { margin: 0; color: #12213b; font-size: 0.95rem; font-weight: 800; }
        .dav-analytics-card__unit { color: #7b8ba4; font-size: 0.68rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }
        .dav-ranking { display: grid; gap: 0.8rem; }
        .dav-ranking--limited { max-height: 18rem; overflow-y: auto; padding-right: 0.45rem; }
        .dav-ranking--limited::-webkit-scrollbar { width: 0.35rem; }
        .dav-ranking--limited::-webkit-scrollbar-thumb { border-radius: 999px; background: #c7d5e8; }
        .dav-ranking__item { display: grid; gap: 0.35rem; }
        .dav-ranking__label { color: #1c2b43; font-size: 0.78rem; font-weight: 700; }
        .dav-ranking__label span { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dav-ranking__label strong { color: #2457d6; font-size: 0.76rem; }
        .dav-ranking__track { height: 0.55rem; overflow: hidden; border-radius: 999px; background: #e2e8f2; }
        .dav-ranking__track i { display: block; height: 100%; border-radius: inherit; background: linear-gradient(90deg, #2875df, #2746ad); }
        .dav-ranking__item small { color: #7b8ba4; font-size: 0.68rem; }
        .dav-risk-list { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 0.6rem; }
        .dav-risk-list__item { padding: 0.7rem 0.8rem; border-radius: 0.7rem; background: #fffaf0; }
        .dav-risk-list__item div { min-width: 0; }
        .dav-risk-list__item strong,
        .dav-risk-list__item small { display: block; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .dav-risk-list__item strong { color: #3a2b0b; font-size: 0.76rem; }
        .dav-risk-list__item small { margin-top: 0.2rem; color: #9a7a32; font-size: 0.68rem; }
        .dav-risk-list__item b { color: #b7791f; font-size: 0.76rem; }
        .dav-risk-icon { display: grid; width: 1.5rem; height: 1.5rem; place-items: center; border-radius: 50%; background: #fff1c7; color: #b7791f; font-weight: 800; }

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
            height: 0.62rem;
            border-radius: 999px;
            background: #d9e0ec;
            overflow: hidden;
        }

        .dav-bar-fill {
            height: 100%;
            border-radius: inherit;
            background: linear-gradient(90deg, #3b82f6 0%, #1d4ed8 100%);
        }

        .dav-bar-fill--alt {
            background: linear-gradient(90deg, #0f766e 0%, #0f9c8d 100%);
        }

        .dav-bar-fill--amber {
            background: linear-gradient(90deg, #c77700 0%, #e0a11a 100%);
        }

        .dav-badge--filled { background: #eff6ff; color: #1d4ed8; border-color: #bfdbfe; }
        .dav-row-completed { background: #f0fdf4; }
        .dav-slideover-panel--completed .av-progress-bar { background: #16a34a !important; }

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

        .dav-link-action--muted,
        .dav-link-action--muted:hover {
            border-color: #d7dce2;
            background: #f3f4f6;
            color: #5f6774;
        }

        .dav-status-actions {
            display: flex;
            align-items: center;
            gap: 0.45rem;
            flex-wrap: wrap;
        }

        .dav-slideover-shell {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            justify-content: flex-end;
        }

        .dav-slideover-backdrop {
            position: absolute;
            inset: 0;
            border: 0;
            background: rgba(15, 23, 42, 0.52);
            cursor: pointer;
        }

        .dav-slideover-panel {
            position: relative;
            z-index: 1;
            width: min(92vw, 1100px);
            height: 100%;
            background: #f8fafc;
            box-shadow: -18px 0 48px rgba(15, 23, 42, 0.24);
            display: grid;
            grid-template-rows: auto minmax(0, 1fr) auto;
        }

        .dav-slideover-header {
            display: flex;
            align-items: start;
            justify-content: space-between;
            gap: 1rem;
            padding: 1.35rem 1.5rem 1rem;
            background: #fff;
            border-bottom: 1px solid var(--gray-200);
        }

        .dav-slideover-eyebrow {
            margin: 0 0 0.35rem;
            color: #3155a5;
            font-size: 0.74rem;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .dav-slideover-header h3 {
            margin: 0;
            color: var(--gray-950);
            font-size: 1.35rem;
            font-weight: 700;
        }

        .dav-slideover-meta {
            margin: 0.45rem 0 0;
            color: var(--gray-600);
            font-size: 0.84rem;
            line-height: 1.5;
        }

        .dav-slideover-close {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 999px;
            border: 1px solid var(--gray-300);
            background: #fff;
            color: var(--gray-700);
            font-size: 1.4rem;
            line-height: 1;
            cursor: pointer;
        }

        .dav-slideover-body {
            overflow: auto;
            padding: 1rem 1.25rem 1.25rem;
        }

        .dav-slideover-body .av-livewire-root {
            padding: 0;
        }

        .dav-slideover-footer {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            padding: 1rem 1.5rem;
            background: #fff;
            border-top: 1px solid var(--gray-200);
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

        :root.dark .dav-dashboard-loading {
            border-color: #1d4ed8;
            background: linear-gradient(135deg, rgba(30, 64, 175, 0.32), rgba(15, 23, 42, 0.72));
            color: #dbeafe;
        }

        :root.dark .dav-dashboard-loading span {
            color: #bfdbfe;
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

        :root.dark .dav-slideover-panel,
        :root.dark .dav-slideover-header,
        :root.dark .dav-slideover-footer {
            background: #0b1323;
            border-color: #243247;
        }

        :root.dark .dav-slideover-header h3 {
            color: #f8fafc;
        }

        :root.dark .dav-slideover-meta {
            color: var(--gray-300);
        }

        :root.dark .dav-slideover-close {
            background: #17263a;
            border-color: #274161;
            color: #e2e8f0;
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

            .dav-kpi-grid--overview {
                grid-template-columns: repeat(2, minmax(0, 1fr));
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

            .dav-chart-board {
                padding: 0.75rem;
            }

            .dav-chart-board__intro {
                align-items: start;
                flex-direction: column;
            }

            .dav-chart-board__legend {
                justify-content: start;
            }

            .dav-analytics { padding: 0.85rem; }
            .dav-analytics__header { align-items: stretch; flex-direction: column; }
            .dav-analytics__headline { width: 100%; text-align: left; }
            .dav-analytics__grid { grid-template-columns: 1fr; }
            .dav-analytics-card--risk { grid-column: auto; }
            .dav-risk-list { grid-template-columns: 1fr; }

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

            .dav-slideover-panel {
                width: 100vw;
            }

            .dav-slideover-header,
            .dav-slideover-footer {
                padding-inline: 1rem;
            }

            .dav-slideover-body {
                padding-inline: 0.75rem;
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
