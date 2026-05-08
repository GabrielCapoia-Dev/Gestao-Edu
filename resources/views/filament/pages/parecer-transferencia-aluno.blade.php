<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Alunos</p>
                <h1>Parecer de Transferencia</h1>
                <p>Localize o aluno matriculado, revise as avaliacoes registradas e gere os documentos para transferencia.</p>
            </div>
        </section>

        <section class="gi-panel av-professor-control-panel">
            <label class="gi-field">
                <span>Buscar aluno</span>
                <input type="search" wire:model.live.debounce.400ms="busca" placeholder="Nome, CGM, escola ou serie">
            </label>
        </section>

        <div class="av-stack">
            <section class="gi-panel">
                @php
                    $alunos = $this->alunos;
                @endphp
                <div class="gi-toolbar">
                    <div>
                        <h3 class="av-pauta-title">Alunos matriculados</h3>
                        <p class="av-pauta-meta">{{ $alunos->total() }} resultado(s)</p>
                    </div>

                    <label class="gi-field gi-field--small">
                        <span>Por pagina</span>
                        <select wire:model.live="porPagina">
                            @foreach ($this->opcoesPorPagina() as $valor => $label)
                            <option value="{{ $valor }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>
                </div>

                <div class="gi-toolbar">
                    <div>
                        <p class="av-pauta-meta">
                            Mostrando {{ $alunos->firstItem() ?? 0 }}-{{ $alunos->lastItem() ?? 0 }}
                            de {{ $alunos->total() }}
                        </p>
                    </div>
                </div>

                <div class="parecer-table-shell">
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>Escola</th>
                                    <th>Turma</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($alunos as $aluno)
                                <tr wire:key="parecer-aluno-{{ $aluno->id }}">
                                    <td>
                                        <strong>{{ $aluno->nome }}</strong>
                                        <small>CGM: {{ $aluno->cgm }}</small>
                                    </td>
                                    <td>{{ $aluno->turma?->escola?->nome }}</td>
                                    <td>{{ $aluno->turma?->serie?->nome }} - {{ $aluno->turma?->nome }}</td>
                                    <td>{{ $aluno->statusLabel() }}</td>
                                    <td>
                                        <button
                                            type="button"
                                            class="gi-action parecer-open-action"
                                            wire:click="selecionarAluno({{ $aluno->id }})">
                                            Abrir
                                        </button>
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="5">Nenhum aluno matriculado encontrado.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

                @if ($alunos->hasPages())
                @php
                    $paginaAtual = $alunos->currentPage();
                    $ultimaPagina = $alunos->lastPage();
                    $inicioJanela = max(1, $paginaAtual - 2);
                    $fimJanela = min($ultimaPagina, $paginaAtual + 2);
                    $paginas = $inicioJanela <= $fimJanela ? range($inicioJanela, $fimJanela) : [];
                @endphp

                <nav class="parecer-pagination" aria-label="Paginacao de alunos">
                    <button
                        type="button"
                        class="gi-action"
                        wire:click="previousPage"
                        @disabled($alunos->onFirstPage())>
                        Anterior
                    </button>

                    <div class="parecer-page-list">
                        @if (! in_array(1, $paginas, true))
                        <button type="button" class="parecer-page-button" wire:click="gotoPage(1)">1</button>
                        @if ($inicioJanela > 2)
                        <span class="parecer-page-ellipsis">...</span>
                        @endif
                        @endif

                        @foreach ($paginas as $pagina)
                        <button
                            type="button"
                            class="parecer-page-button {{ $pagina === $paginaAtual ? 'is-active' : '' }}"
                            wire:click="gotoPage({{ $pagina }})">
                            {{ $pagina }}
                        </button>
                        @endforeach

                        @if (! in_array($ultimaPagina, $paginas, true))
                        @if ($fimJanela < $ultimaPagina - 1)
                        <span class="parecer-page-ellipsis">...</span>
                        @endif
                        <button type="button" class="parecer-page-button" wire:click="gotoPage({{ $ultimaPagina }})">{{ $ultimaPagina }}</button>
                        @endif
                    </div>

                    <button
                        type="button"
                        class="gi-action"
                        wire:click="nextPage"
                        @disabled(! $alunos->hasMorePages())>
                        Proxima
                    </button>
                </nav>
                @endif
            </section>

            @if ($this->alunoSelecionado && $this->slideoverAberto)
            @php
                $aluno = $this->alunoSelecionado;
            @endphp
            <div class="parecer-slideover-backdrop" wire:click="fecharSlideover"></div>
            <aside class="parecer-slideover" role="dialog" aria-modal="true" aria-label="Parecer de Transferencia">
                <header class="parecer-slideover-header">
                    <div>
                        <p class="gi-eyebrow">Parecer de Transferencia</p>
                        <h3>{{ $aluno->nome }}</h3>
                        <p>
                            CGM: {{ $aluno->cgm }} |
                            {{ $aluno->turma?->escola?->nome }} |
                            {{ $aluno->turma?->serie?->nome }} - {{ $aluno->turma?->nome }}
                        </p>
                    </div>

                    <button type="button" class="gi-action" wire:click="fecharSlideover">Fechar</button>
                </header>

                <div class="parecer-slideover-actions">
                    <button
                        type="button"
                        class="gi-action gi-action--primary"
                        wire:click="gerarParecerTransferencia"
                        wire:confirm="Caso deseje continuar, o aluno sera marcado como transferido e essa acao nao podera ser revertida. Deseja gerar o Parecer de Transferencia?"
                        wire:loading.attr="disabled"
                        wire:target="gerarParecerTransferencia">
                        Gerar Parecer de Transferencia
                    </button>
                </div>

                <div class="parecer-slideover-body">
                    @forelse ($this->avaliacoesDoAluno as $avaliacao)
                    <section class="gi-panel av-turma-section" wire:key="parecer-avaliacao-{{ $avaliacao['id'] }}">
                        <div class="av-pauta-toggle">
                            <div class="av-pauta-toggle-main">
                                <h3 class="av-pauta-title">{{ $avaliacao['nome'] }}</h3>
                                <p class="av-pauta-meta">
                                    {{ $avaliacao['tipo'] }} |
                                    {{ $avaliacao['periodo'] }} |
                                    {{ $avaliacao['periodo_datas'] }}
                                </p>
                            </div>

                            <div class="av-pauta-toggle-side">
                                <div class="av-pauta-progress-head">
                                    <span>{{ $avaliacao['preenchidas'] }}/{{ $avaliacao['total'] }}</span>
                                    <span>{{ $avaliacao['percentual'] }}%</span>
                                </div>
                                <div class="av-progress-track av-progress-track--compact">
                                    <div class="av-progress-bar" style="width: {{ $avaliacao['percentual'] }}%"></div>
                                </div>
                            </div>
                        </div>

                        <div class="av-turma-content">
                            @foreach ($avaliacao['componentes'] as $componente)
                            <section class="av-aluno-componente">
                                <h4>{{ $componente['nome'] }}</h4>

                                <div class="gi-table-wrap">
                                    <table class="gi-table">
                                        <thead>
                                            <tr>
                                                <th>Pauta</th>
                                                <th>Alternativas</th>
                                                <th>Resposta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($componente['pautas'] as $pauta)
                                            <tr>
                                                <td>{{ $pauta['texto'] }}</td>
                                                <td>{{ collect($pauta['alternativas'])->pluck('nome')->join(', ') }}</td>
                                                <td>
                                                    <select
                                                        class="parecer-response-select"
                                                        wire:model.live="respostasParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}"
                                                        @disabled($pauta['bloqueada'])>
                                                        <option value="">Pendente</option>
                                                        @foreach ($pauta['alternativas'] as $alternativa)
                                                        <option value="{{ $alternativa['id'] }}">{{ $alternativa['nome'] }}</option>
                                                        @endforeach
                                                    </select>
                                                    @if ($pauta['bloqueada'])
                                                    <small>Bloqueada por historico</small>
                                                    @endif
                                                </td>
                                            </tr>
                                            @endforeach
                                        </tbody>
                                    </table>
                                </div>
                            </section>
                            @endforeach
                        </div>
                    </section>
                    @empty
                    <section class="av-note av-note--warning">
                        Nenhuma avaliacao vinculada a turma atual do aluno.
                    </section>
                    @endforelse
                </div>
            </aside>
            @else
            <section class="av-note">
                Clique em Abrir para visualizar as avaliacoes em painel lateral.
            </section>
            @endif
        </div>

        @include('filament.pages.partials.avaliacoes-page-styles')

        <style>
            .parecer-table-shell {
                position: relative;
                min-height: 12rem;
            }

            .parecer-pagination {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: space-between;
                gap: 0.75rem;
                padding-top: 0.25rem;
            }

            .parecer-page-list {
                display: flex;
                flex-wrap: wrap;
                align-items: center;
                justify-content: center;
                gap: 0.35rem;
            }

            .parecer-page-button,
            .parecer-page-ellipsis {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 2.25rem;
                height: 2.25rem;
                padding: 0 0.65rem;
                border-radius: var(--radius-md);
                color: var(--gray-700);
                font-size: var(--text-sm);
                line-height: 1;
                font-weight: var(--font-weight-medium);
            }

            .parecer-page-button {
                border: 1px solid var(--gray-200);
                background: #fff;
                cursor: pointer;
            }

            .parecer-page-button.is-active {
                border-color: var(--primary-600);
                background: var(--primary-600);
                color: #fff;
            }

            .parecer-page-ellipsis {
                color: var(--gray-400);
            }

            .parecer-open-action {
                min-width: 5.25rem;
            }

            .parecer-slideover-backdrop {
                position: fixed;
                inset: 0;
                z-index: 40;
                background: rgba(15, 23, 42, 0.38);
            }

            .parecer-slideover {
                position: fixed;
                inset: 0 0 0 auto;
                z-index: 41;
                display: grid;
                grid-template-rows: auto auto minmax(0, 1fr);
                width: min(72rem, 100vw);
                background: #fff;
                box-shadow: -24px 0 60px rgba(15, 23, 42, 0.22);
            }

            .parecer-slideover-header,
            .parecer-slideover-actions {
                padding: 1.15rem 1.25rem;
                border-bottom: 1px solid var(--gray-200);
            }

            .parecer-slideover-header {
                display: flex;
                justify-content: space-between;
                align-items: flex-start;
                gap: 1rem;
            }

            .parecer-slideover-header h3,
            .parecer-slideover-header p {
                margin: 0;
            }

            .parecer-slideover-header h3 {
                color: var(--gray-950);
                font-size: var(--text-xl);
                line-height: 1.25;
                font-weight: var(--font-weight-bold);
            }

            .parecer-slideover-header p:not(.gi-eyebrow) {
                margin-top: 0.35rem;
                color: var(--gray-500);
                font-size: var(--text-sm);
                line-height: 1.5;
            }

            .parecer-slideover-actions {
                display: flex;
                justify-content: flex-end;
                gap: 0.75rem;
            }

            .parecer-slideover-body {
                display: grid;
                align-content: start;
                gap: 1rem;
                overflow-y: auto;
                padding: 1rem 1.25rem 1.25rem;
                background: var(--gray-50);
            }

            .parecer-response-select {
                width: min(12.5rem, 100%);
                min-height: 2.25rem;
                border: 1px solid var(--gray-300);
                border-radius: var(--radius-md);
                background: #fff;
                color: var(--gray-900);
                font-size: var(--text-sm);
                line-height: 1.25;
                padding: 0.4rem 0.65rem;
            }

            .parecer-response-select:disabled {
                background: var(--gray-100);
                color: var(--gray-500);
                cursor: not-allowed;
            }

            @media (max-width: 48rem) {
                .parecer-slideover {
                    width: 100vw;
                }

                .parecer-slideover-header {
                    flex-direction: column;
                }

                .parecer-slideover-header .gi-action,
                .parecer-slideover-actions .gi-action {
                    width: 100%;
                }
            }
        </style>
    </div>
</x-filament-panels::page>
