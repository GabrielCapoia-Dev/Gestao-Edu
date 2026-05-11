<div class="parecer-modal-root" wire:key="parecer-transferencia-modal-{{ $this->alunoSelecionado?->id ?? $alunoId }}">
    <div
        class="parecer-loading-overlay"
        wire:loading.flex
        wire:target="respostasParecer,observacoesParecer,informacoesComplementaresParecer,gerarParecerTransferencia,alternarAvaliacaoParecer"
    >
        <div class="parecer-loading-card" role="status" aria-live="polite">
            <span class="parecer-loading-spinner"></span>
            <span>Processando...</span>
        </div>
    </div>

    @php($aluno = $this->alunoSelecionado)

    @if (! $aluno)
        <section class="av-note av-note--warning">
            Aluno nao encontrado ou sem permissao de acesso.
        </section>
    @else
        <header class="parecer-modal-header">
            <div>
                <p class="gi-eyebrow">Parecer de Transferencia</p>
                <h3>{{ $aluno->nome }}</h3>
                <p>
                    CGM: {{ $aluno->cgm }} |
                    {{ $aluno->turma?->escola?->nome }} |
                    {{ $aluno->turma?->serie?->nome }} - {{ $aluno->turma?->nome }} |
                    {{ $aluno->statusLabel() }}
                </p>
            </div>

            @if ($this->podeGerarParecer)
                <button
                    type="button"
                    class="gi-action gi-action--primary"
                    wire:click="gerarParecerTransferencia"
                    wire:confirm="Caso deseje continuar, o aluno sera marcado como transferido e essa acao nao podera ser revertida. Deseja gerar o Parecer de Transferencia?"
                    wire:loading.attr="disabled"
                    wire:target="gerarParecerTransferencia"
                >
                    Gerar Parecer de Transferencia
                </button>
            @endif
        </header>

        @if ($this->parecerSomenteLeitura)
            <section class="av-note">
                Modo leitura: alunos historicos permanecem disponiveis para visualizacao, sem alteracao de respostas ou transferencia.
            </section>
        @endif

        <div class="parecer-modal-body">
            @forelse ($this->avaliacoesDoAluno as $avaliacao)
                @php($avaliacaoExpandida = $this->avaliacaoEstaExpandida((int) $avaliacao['id']))

                <section
                    class="gi-panel av-turma-section {{ $avaliacaoExpandida ? 'is-open' : '' }}"
                    wire:key="parecer-avaliacao-modal-{{ $avaliacao['id'] }}"
                >
                    <button
                        type="button"
                        class="av-pauta-toggle parecer-evaluation-toggle"
                        wire:click="alternarAvaliacaoParecer({{ $avaliacao['id'] }})"
                        wire:loading.attr="disabled"
                        wire:target="alternarAvaliacaoParecer"
                        aria-expanded="{{ $avaliacaoExpandida ? 'true' : 'false' }}"
                    >
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

                            <div class="av-pauta-toggle-meta">
                                <span class="av-pauta-arrow {{ $avaliacaoExpandida ? 'is-open' : '' }}">v</span>
                            </div>
                        </div>
                    </button>

                    @if ($avaliacaoExpandida)
                        <div class="av-turma-content">
                            @foreach ($avaliacao['componentes'] as $componente)
                                <section class="av-aluno-componente" wire:key="parecer-componente-modal-{{ $avaliacao['id'] }}-{{ $componente['id'] }}">
                                    <h4>{{ $componente['nome'] }}</h4>

                                    <div class="gi-table-wrap">
                                        <table class="gi-table">
                                            <thead>
                                                <tr>
                                                    <th>Pauta</th>
                                                    <th>Alternativas</th>
                                                    <th>Resposta</th>
                                                    <th>Observacao</th>
                                                </tr>
                                            </thead>

                                            <tbody>
                                                @foreach ($componente['pautas'] as $pauta)
                                                    @php($campoBloqueado = $this->parecerSomenteLeitura || ! (bool) ($pauta['editavel'] ?? true) || (bool) ($pauta['bloqueada'] ?? false))

                                                    <tr wire:key="parecer-pauta-modal-{{ $avaliacao['id'] }}-{{ $componente['id'] }}-{{ $pauta['id'] }}">
                                                        <td>{{ $pauta['texto'] }}</td>

                                                        <td>{{ collect($pauta['alternativas'])->pluck('nome')->join(', ') }}</td>

                                                        <td>
                                                            <select
                                                                class="parecer-response-select"
                                                                wire:model.live="respostasParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}"
                                                                wire:loading.attr="disabled"
                                                                wire:target="respostasParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}"
                                                                @disabled($campoBloqueado)
                                                            >
                                                                <option value="">Pendente</option>

                                                                @foreach ($pauta['alternativas'] as $alternativa)
                                                                    <option value="{{ $alternativa['id'] }}">
                                                                        {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observacao)' : '' }}
                                                                    </option>
                                                                @endforeach
                                                            </select>

                                                            @if ($pauta['bloqueada'] ?? false)
                                                                <small>Bloqueada por historico.</small>
                                                            @elseif (! (bool) ($pauta['editavel'] ?? true))
                                                                <small>Componente restrito ao professor vinculado.</small>
                                                            @endif
                                                        </td>

                                                        <td class="parecer-observation-cell">
                                                            @if ($pauta['requer_observacao'])
                                                                <textarea
                                                                    maxlength="1500"
                                                                    placeholder="Observacao obrigatoria"
                                                                    class="parecer-response-textarea"
                                                                    wire:model.live.debounce.500ms="observacoesParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}"
                                                                    wire:loading.attr="disabled"
                                                                    wire:target="observacoesParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}"
                                                                    @disabled($campoBloqueado)
                                                                ></textarea>

                                                                <small class="av-field-hint av-field-hint--danger">
                                                                    Obrigatoria para esta alternativa.
                                                                </small>
                                                            @else
                                                                <small class="av-field-hint">
                                                                    Somente alternativas com observacao habilitam este campo.
                                                                </small>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @php($informacaoBloqueada = $this->parecerSomenteLeitura || ! (bool) ($componente['editavel'] ?? true) || (bool) ($componente['informacao_bloqueada'] ?? false))

                                    <div class="parecer-complementary-section">
                                        <h4>Informacoes complementares do componente</h4>

                                        @if ($informacaoBloqueada)
                                            @if ($componente['informacao_bloqueada'] ?? false)
                                                <small class="av-field-hint">Informacoes bloqueadas por historico.</small>
                                            @elseif (! (bool) ($componente['editavel'] ?? true))
                                                <small class="av-field-hint">Componente restrito ao professor vinculado.</small>
                                            @endif
                                        @endif

                                        <textarea
                                            maxlength="1500"
                                            placeholder="Informacoes complementares (opcional)"
                                            class="parecer-response-textarea"
                                            wire:model.live.debounce.600ms="informacoesComplementaresParecer.{{ $avaliacao['id'] }}.{{ $componente['id'] }}"
                                            wire:loading.attr="disabled"
                                            wire:target="informacoesComplementaresParecer.{{ $avaliacao['id'] }}.{{ $componente['id'] }}"
                                            @disabled($informacaoBloqueada)
                                        ></textarea>
                                    </div>
                                </section>
                            @endforeach
                        </div>
                    @endif
                </section>
            @empty
                <section class="av-note av-note--warning">
                    Nenhuma avaliacao vinculada a turma atual do aluno.
                </section>
            @endforelse
        </div>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')

    <style>
        .parecer-modal-root {
            position: relative;
            display: grid;
            gap: 1rem;
            min-height: 14rem;
        }

        .parecer-modal-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--gray-200);
        }

        .parecer-modal-header h3,
        .parecer-modal-header p {
            margin: 0;
        }

        .parecer-modal-header h3 {
            color: var(--gray-950);
            font-size: var(--text-xl);
            line-height: 1.25;
            font-weight: var(--font-weight-bold);
        }

        .parecer-modal-header p:not(.gi-eyebrow) {
            margin-top: 0.35rem;
            color: var(--gray-500);
            font-size: var(--text-sm);
            line-height: 1.5;
        }

        .parecer-modal-body {
            display: grid;
            align-content: start;
            gap: 1rem;
        }

        .parecer-loading-overlay {
            position: absolute;
            inset: 0;
            z-index: 30;
            display: none;
            align-items: center;
            justify-content: center;
            border-radius: var(--radius-lg);
            background: rgba(15, 23, 42, 0.42);
            cursor: wait;
        }

        .parecer-loading-card {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.75rem;
            min-width: 12rem;
            border: 1px solid rgba(255, 255, 255, 0.45);
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--gray-900);
            font-size: var(--text-sm);
            font-weight: var(--font-weight-semibold);
            line-height: 1;
            padding: 0.9rem 1rem;
            box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
        }

        .parecer-loading-spinner {
            width: 1.25rem;
            height: 1.25rem;
            border: 3px solid var(--gray-200);
            border-top-color: var(--primary-600);
            border-radius: 999px;
            animation: parecer-spin 0.72s linear infinite;
        }

        @keyframes parecer-spin {
            to {
                transform: rotate(360deg);
            }
        }

        .parecer-evaluation-toggle {
            width: 100%;
            border: 0;
            background: transparent;
            cursor: pointer;
            text-align: left;
        }

        .parecer-evaluation-toggle:hover .av-pauta-title {
            color: var(--primary-700);
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

        .parecer-observation-cell {
            min-width: 16rem;
        }

        .parecer-response-textarea {
            width: 100%;
            min-height: 4.25rem;
            resize: vertical;
            border: 1px solid var(--gray-300);
            border-radius: var(--radius-md);
            background: #fff;
            color: var(--gray-900);
            font-size: var(--text-sm);
            line-height: 1.45;
            padding: 0.55rem 0.65rem;
        }

        .parecer-response-textarea:disabled {
            background: var(--gray-100);
            color: var(--gray-500);
            cursor: not-allowed;
        }

        .parecer-complementary-section {
            display: grid;
            gap: 0.5rem;
            margin-top: 0.75rem;
            padding: 0.85rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            background: var(--gray-50);
        }

        .parecer-complementary-section h4 {
            margin: 0;
            color: var(--gray-900);
            font-size: var(--text-sm);
            line-height: 1.25;
            font-weight: var(--font-weight-semibold);
        }

        @media (max-width: 48rem) {
            .parecer-modal-header {
                flex-direction: column;
            }

            .parecer-modal-header .gi-action {
                width: 100%;
            }
        }
    </style>
</div>
