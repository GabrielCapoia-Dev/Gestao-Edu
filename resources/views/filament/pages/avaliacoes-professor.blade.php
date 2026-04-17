<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Professor</p>
                <h1>Minhas Avaliacoes</h1>
                <p>Selecione avaliacao e turma para registrar respostas por aluno com apoio de avaliacao em massa.</p>
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
                    <section wire:key="pauta-{{ $pauta->id }}" class="gi-panel">
                        <div class="av-pauta-head">
                            <h3 class="av-pauta-title">{{ $pauta->texto }}</h3>
                            <p class="av-pauta-meta">
                                Componente: {{ $pauta->componente?->nome ?? 'Geral (sem componente especifico)' }}
                            </p>
                        </div>

                        <div class="gi-toolbar">
                            <div class="gi-toolbar-left">
                                <label class="gi-field">
                                    <span>Avaliacao em massa</span>
                                    <select wire:model="avaliacaoEmMassa.{{ $pauta->id }}">
                                        <option value="">Selecione uma alternativa</option>
                                        @foreach ($pauta->alternativas as $alternativa)
                                            <option value="{{ $alternativa->id }}">
                                                {{ $alternativa->nome }}{{ $alternativa->tem_observacao ? ' (exige observacao)' : '' }}
                                            </option>
                                        @endforeach
                                    </select>
                                </label>
                            </div>

                            <div class="gi-toolbar-right">
                                <button type="button" class="gi-action" wire:click="aplicarEmMassa({{ $pauta->id }})">
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
                                                <select class="av-table-input" wire:model="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id">
                                                    <option value="">Selecione</option>
                                                    @foreach ($pauta->alternativas as $alternativa)
                                                        <option value="{{ $alternativa->id }}">
                                                            {{ $alternativa->nome }}{{ $alternativa->tem_observacao ? ' (exige observacao)' : '' }}
                                                        </option>
                                                    @endforeach
                                                </select>
                                            </td>
                                            <td>
                                                @php($alternativaSelecionadaId = (int) ($respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0))
                                                @php($requerObservacao = $this->alternativaRequerObservacao((int) $pauta->id, $alternativaSelecionadaId))

                                                @if ($requerObservacao)
                                                    <input
                                                        type="text"
                                                        maxlength="1000"
                                                        placeholder="Observacao obrigatoria"
                                                        class="av-table-input"
                                                        wire:model.blur="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao" />
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
                    </section>
                @endforeach
            </div>

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div></div>
                    <div class="gi-toolbar-right">
                        <button type="button" class="gi-action gi-action--primary" wire:click="salvarRespostas">
                            Salvar avaliacao da turma
                        </button>
                    </div>
                </div>
            </section>
        @endif
    </div>

    @include('filament.pages.partials.avaliacoes-page-styles')
</x-filament-panels::page>
