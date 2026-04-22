<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Professor</p>
                <h1>Minhas Avaliacoes</h1>
                <p>Selecione avaliacao e turma para registrar respostas por aluno. Cada alteracao e salva automaticamente.</p>
            </div>
        </section>

        <section class="gi-panel">
            <div class="av-form-grid av-form-grid--two">
                <label class="gi-field">
                    <span>Avaliacao</span>
                    <select wire:model.live="avaliacao">
                        <option value="">Selecione uma avaliacao</option>
                        @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                            <option value="{{ $avaliacaoItem->id }}">
                                {{ $avaliacaoItem->nome }} |
                                {{ optional($avaliacaoItem->data_inicio)->format('d/m/Y') }}
                                ate
                                {{ optional($avaliacaoItem->data_fim)->format('d/m/Y') }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="gi-field">
                    <span>Turma</span>
                    <select wire:model.live="turma" @disabled(! $avaliacao)>
                        <option value="">Selecione uma turma</option>
                        @foreach ($this->turmasDisponiveis as $turmaItem)
                            <option value="{{ $turmaItem->id }}">
                                {{ $turmaItem->escola?->nome }} - {{ $turmaItem->serie?->nome }} - Turma {{ $turmaItem->nome }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>

            @php($progresso = $this->progresso)
            @php($percentual = $progresso['total'] > 0 ? min(100, (int) round(($progresso['preenchidas'] / $progresso['total']) * 100)) : 0)
            <div class="av-progress">
                <div class="av-progress-head">
                    <span>Progresso do preenchimento</span>
                    <span>{{ $progresso['preenchidas'] }}/{{ $progresso['total'] }}</span>
                </div>
                <div class="av-progress-track">
                    <div class="av-progress-bar" style="width: {{ $percentual }}%"></div>
                </div>
            </div>
        </section>

        @if ($this->avaliacoesDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Nao existem avaliacoes pendentes para seus componentes neste momento.
            </section>
        @elseif (! $avaliacao)
            <section class="av-note">
                Selecione uma avaliacao para comecar.
            </section>
        @elseif ($this->turmasDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Esta avaliacao nao possui turmas com pautas vinculadas aos componentes que voce leciona.
            </section>
        @elseif (! $turma)
            <section class="av-note">
                Selecione a turma para visualizar as pautas e os alunos.
            </section>
        @elseif ($this->pautasDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Nenhuma pauta desta avaliacao esta disponivel para os seus componentes nesta turma.
            </section>
        @else
            <div class="av-stack">
                @foreach ($this->pautasDisponiveis as $pauta)
                    @php($progressoPauta = $this->progressoPorPauta[$pauta->id] ?? ['preenchidas' => 0, 'total' => $this->alunosDaTurma->count(), 'percentual' => 0, 'concluida' => false])
                    @php($pautaExpandida = $this->pautaEstaExpandida((int) $pauta->id))
                    @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))

                    <section wire:key="pauta-{{ $pauta->id }}" class="gi-panel av-pauta-section {{ $pautaExpandida ? 'is-open' : '' }}">
                        <button type="button" class="av-pauta-toggle" wire:click="alternarPauta({{ $pauta->id }})">
                            <div class="av-pauta-toggle-main">
                                <h3 class="av-pauta-title">{{ $pauta->texto }}</h3>
                                <p class="av-pauta-meta">
                                    Componente: {{ $pauta->componente?->nome ?? 'Geral (sem componente especifico)' }}
                                </p>
                            </div>

                            <div class="av-pauta-toggle-side">
                                <div class="av-pauta-progress-head">
                                    <span>{{ $progressoPauta['preenchidas'] }}/{{ $progressoPauta['total'] }}</span>
                                    <span>{{ $progressoPauta['percentual'] }}%</span>
                                </div>
                                <div class="av-progress-track av-progress-track--compact">
                                    <div class="av-progress-bar" style="width: {{ $progressoPauta['percentual'] }}%"></div>
                                </div>
                                <div class="av-pauta-toggle-meta">
                                    @if ($progressoPauta['concluida'])
                                        <span class="av-pauta-check">Concluida</span>
                                    @else
                                        <span class="av-pauta-check av-pauta-check--pending">Em andamento</span>
                                    @endif

                                    <span class="av-pauta-arrow {{ $pautaExpandida ? 'is-open' : '' }}">▾</span>
                                </div>
                            </div>
                        </button>

                        @if ($pautaExpandida)
                            <div class="av-pauta-content">
                                <div class="gi-toolbar">
                                    <div class="gi-toolbar-left">
                                        <label class="gi-field">
                                            <span>Avaliacao em massa</span>
                                            <select wire:model="avaliacaoEmMassa.{{ $pauta->id }}">
                                                <option value="">Selecione uma alternativa</option>
                                                @foreach ($alternativasPauta as $alternativa)
                                                    <option value="{{ $alternativa['id'] }}">
                                                        {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observacao)' : '' }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>

                                    <div class="gi-toolbar-right">
                                        <button type="button" class="gi-action" wire:click="aplicarEmMassa({{ $pauta->id }})" wire:loading.attr="disabled" wire:target="aplicarEmMassa">
                                            Aplicar para todos os alunos
                                        </button>
                                    </div>
                                </div>

                                <div class="gi-table-wrap">
                                    <table class="gi-table">
                                        <thead>
                                            <tr>
                                                <th>Aluno</th>
                                                <th>Alternativa</th>
                                                <th>Observacao da pauta</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            @foreach ($this->alunosDaTurma as $aluno)
                                                <tr wire:key="pauta-{{ $pauta->id }}-aluno-{{ $aluno->id }}">
                                                    <td>
                                                        <strong>{{ $aluno->nome }}</strong>
                                                        <small>CGM: {{ $aluno->cgm }}</small>
                                                    </td>
                                                    <td>
                                                        <div class="av-input-wrap">
                                                            <select class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id">
                                                                <option value="">Selecione</option>
                                                                @foreach ($alternativasPauta as $alternativa)
                                                                    <option value="{{ $alternativa['id'] }}">
                                                                        {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observacao)' : '' }}
                                                                    </option>
                                                                @endforeach
                                                            </select>

                                                            <span class="av-saving-indicator" wire:loading.flex wire:target="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id">
                                                                <span class="av-spinner"></span>
                                                                Salvando...
                                                            </span>
                                                        </div>
                                                    </td>
                                                    <td>
                                                        @php($alternativaSelecionadaId = (int) ($respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0))
                                                        @php($requerObservacao = $this->alternativaRequerObservacao((int) $pauta->id, $alternativaSelecionadaId))

                                                        @if ($requerObservacao)
                                                            <div class="av-input-wrap">
                                                                <input
                                                                    type="text"
                                                                    maxlength="1000"
                                                                    placeholder="Observacao obrigatoria"
                                                                    class="av-table-input"
                                                                    wire:model.live.debounce.500ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao" />

                                                                <span class="av-saving-indicator" wire:loading.flex wire:target="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao">
                                                                    <span class="av-spinner"></span>
                                                                    Salvando...
                                                                </span>
                                                            </div>
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
                            </div>
                        @endif
                    </section>
                @endforeach
            </div>

            <details class="gi-panel av-pauta-section" open>
                <summary class="av-pauta-toggle">
                    <div class="av-pauta-toggle-main">
                        <h3 class="av-pauta-title">Informacoes complementares por aluno</h3>
                        <p class="av-pauta-meta">Campo opcional da avaliacao, independente das pautas.</p>
                    </div>
                </summary>

                <div class="av-pauta-content" style="padding-top: .75rem;">
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>Informacoes complementares</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($this->alunosDaTurma as $aluno)
                                    <tr wire:key="complemento-aluno-{{ $aluno->id }}">
                                        <td>
                                            <strong>{{ $aluno->nome }}</strong>
                                            <small>CGM: {{ $aluno->cgm }}</small>
                                        </td>
                                        <td>
                                            <div class="av-input-wrap">
                                                <input
                                                    type="text"
                                                    maxlength="2000"
                                                    placeholder="Informacoes complementares (opcional)"
                                                    class="av-table-input"
                                                    wire:model.live.debounce.600ms="informacoesComplementares.{{ $aluno->id }}" />

                                                <span class="av-saving-indicator" wire:loading.flex wire:target="informacoesComplementares.{{ $aluno->id }}">
                                                    <span class="av-spinner"></span>
                                                    Salvando...
                                                </span>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </details>

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div></div>
                    <div class="gi-toolbar-right">
                        <button type="button" class="gi-action gi-action--primary" wire:click="salvarRespostas">
                            Validar pendencias da turma
                        </button>
                    </div>
                </div>
            </section>
        @endif
    </div>

    @include('filament.pages.partials.avaliacoes-page-styles')
</x-filament-panels::page>
