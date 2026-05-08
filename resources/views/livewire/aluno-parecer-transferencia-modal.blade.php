<div class="parecer-modal-root">
    @php
        $aluno = $this->alunoSelecionado;
        $somenteLeitura = $this->parecerSomenteLeitura;
    @endphp

    @if ($aluno)
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
        </header>

        <div class="parecer-modal-actions">
            @if ($somenteLeitura)
                <section class="av-note">
                    Este registro e historico. As respostas ficam disponiveis apenas para consulta.
                </section>
            @elseif ($this->podeGerarParecer)
                <button
                    type="button"
                    class="gi-action gi-action--primary"
                    wire:click="gerarParecerTransferencia"
                    wire:confirm="Caso deseje continuar, o aluno sera marcado como transferido e essa acao nao podera ser revertida. Deseja gerar o Parecer de Transferencia?"
                    wire:loading.attr="disabled"
                    wire:target="gerarParecerTransferencia">
                    Gerar Parecer de Transferencia
                </button>
            @endif
        </div>

        <div class="parecer-modal-body">
            @forelse ($this->avaliacoesDoAluno as $avaliacao)
                @php($avaliacaoExpandida = $this->avaliacaoEstaExpandida((int) $avaliacao['id']))
                <section class="gi-panel av-turma-section {{ $avaliacaoExpandida ? 'is-open' : '' }}" wire:key="aluno-parecer-avaliacao-{{ $aluno->id }}-{{ $avaliacao['id'] }}">
                    <button
                        type="button"
                        class="av-pauta-toggle parecer-evaluation-toggle"
                        wire:click="alternarAvaliacaoParecer({{ $avaliacao['id'] }})"
                        aria-expanded="{{ $avaliacaoExpandida ? 'true' : 'false' }}">
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
                                <section class="av-aluno-componente">
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
                                                    @php($campoBloqueado = $somenteLeitura || ! (bool) ($pauta['editavel'] ?? true) || (bool) $pauta['bloqueada'])
                                                    <tr>
                                                        <td>{{ $pauta['texto'] }}</td>
                                                        <td>{{ collect($pauta['alternativas'])->pluck('nome')->join(', ') }}</td>
                                                        <td>
                                                            @if ($campoBloqueado)
                                                                <span class="parecer-readonly-value">{{ $pauta['resposta'] !== '' ? $pauta['resposta'] : 'Não Avaliado' }}</span>
                                                                @if ($pauta['bloqueada'])
                                                                    <small>Bloqueada por historico</small>
                                                                @elseif (! (bool) ($pauta['editavel'] ?? true))
                                                                    <small>Componente restrito ao professor vinculado.</small>
                                                                @endif
                                                            @else
                                                                <select
                                                                    class="parecer-response-select"
                                                                    wire:model.live="respostasParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}">
                                                                    <option value="">Pendente</option>
                                                                    @foreach ($pauta['alternativas'] as $alternativa)
                                                                        <option value="{{ $alternativa['id'] }}">
                                                                            {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observacao)' : '' }}
                                                                        </option>
                                                                    @endforeach
                                                                </select>
                                                            @endif
                                                        </td>
                                                        <td class="parecer-observation-cell">
                                                            @if ($campoBloqueado)
                                                                <span class="parecer-readonly-value">{{ $pauta['observacao'] !== '' ? $pauta['observacao'] : '-' }}</span>
                                                            @elseif ($pauta['requer_observacao'])
                                                                <textarea
                                                                    maxlength="1500"
                                                                    placeholder="Observacao obrigatoria"
                                                                    class="parecer-response-textarea"
                                                                    wire:model.live.debounce.500ms="observacoesParecer.{{ $avaliacao['id'] }}.{{ $pauta['id'] }}"></textarea>
                                                                <small class="av-field-hint av-field-hint--danger">Obrigatoria para esta alternativa.</small>
                                                            @else
                                                                <small class="av-field-hint">Somente alternativas com observacao habilitam este campo.</small>
                                                            @endif
                                                        </td>
                                                    </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @php($informacaoBloqueada = $somenteLeitura || ! (bool) ($componente['editavel'] ?? true) || (bool) ($componente['informacao_bloqueada'] ?? false))
                                    <div class="parecer-complementary-section">
                                        <h4>Informacoes complementares do componente</h4>
                                        @if ($informacaoBloqueada)
                                            <div class="parecer-readonly-box">{{ $componente['informacoes_complementares'] !== '' ? $componente['informacoes_complementares'] : '-' }}</div>
                                            @if ($componente['informacao_bloqueada'] ?? false)
                                                <small class="av-field-hint">Informacoes bloqueadas por historico.</small>
                                            @elseif (! (bool) ($componente['editavel'] ?? true))
                                                <small class="av-field-hint">Componente restrito ao professor vinculado.</small>
                                            @endif
                                        @else
                                            <textarea
                                                maxlength="1500"
                                                placeholder="Informacoes complementares (opcional)"
                                                class="parecer-response-textarea"
                                                wire:model.live.debounce.600ms="informacoesComplementaresParecer.{{ $avaliacao['id'] }}.{{ $componente['id'] }}"></textarea>
                                        @endif
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
    @else
        <section class="av-note av-note--warning">
            Aluno nao encontrado no escopo permitido.
        </section>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')

    <style>
        .parecer-modal-root {
            display: grid;
            gap: 1rem;
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

        .parecer-modal-actions {
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
        }

        .parecer-modal-actions .av-note {
            width: 100%;
        }

        .parecer-modal-body {
            display: grid;
            align-content: start;
            gap: 1rem;
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

        .parecer-readonly-value,
        .parecer-readonly-box {
            display: block;
            color: var(--gray-800);
            font-size: var(--text-sm);
            line-height: 1.45;
        }

        .parecer-readonly-box {
            min-height: 2.75rem;
            padding: 0.55rem 0.65rem;
            border: 1px solid var(--gray-200);
            border-radius: var(--radius-md);
            background: #fff;
            white-space: pre-wrap;
        }
    </style>
</div>
