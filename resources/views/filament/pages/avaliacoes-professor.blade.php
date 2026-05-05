<x-filament-panels::page>
    <div class="av-livewire-root">
        <div class="gi-page av-page">
            <section class="gi-hero">
                <div>
                    <p class="gi-eyebrow">Professor</p>
                    <h1>Minhas Avaliacoes</h1>
                    <p>Selecione avaliacao e serie para registrar respostas por aluno. Cada alteracao e salva automaticamente.</p>
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
                        <span>Serie</span>
                        <select wire:model.live="serieEscola" @disabled(! $avaliacao)>
                            <option value="">Selecione uma serie</option>
                            @foreach ($this->seriesPorEscolaDisponiveis->groupBy('escola_nome') as $escolaNome => $seriesDaEscola)
                            <optgroup label="{{ $escolaNome }}">
                                @foreach ($seriesDaEscola as $serieItem)
                                <option value="{{ $serieItem['value'] }}">{{ $serieItem['serie_nome'] }}</option>
                                @endforeach
                            </optgroup>
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

            @if (! $this->podeResponder())
            <section class="av-note">
                Modo leitura: para alterar/responder avaliacoes, e necessario ter a permissao "Responder Avaliacoes".
            </section>
            @endif

            @if ($this->avaliacoesDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Nao existem avaliacoes pendentes para seus componentes neste momento.
            </section>
            @elseif (! $avaliacao)
            <section class="av-note">
                Selecione uma avaliacao para comecar.
            </section>
            @elseif ($this->seriesPorEscolaDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Esta avaliacao nao possui series com turmas vinculadas aos componentes que voce leciona.
            </section>
            @elseif (! $serieEscola)
            <section class="av-note">
                Selecione a serie para visualizar as turmas, pautas e alunos.
            </section>
            @elseif ($this->turmasDaSerieDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Esta serie nao possui turmas disponiveis para esta avaliacao.
            </section>
            @elseif ($this->pautasDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Nenhuma pauta desta avaliacao esta disponivel para os seus componentes nesta serie.
            </section>
            @else
            @php($turmasDaSerie = $this->turmasDaSerieDisponiveis)

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div>
                        <h3 class="av-pauta-title">Modo de visualizacao</h3>
                        <p class="av-pauta-meta">Escolha como deseja preencher esta avaliacao.</p>
                    </div>

                    <div class="av-mode-actions">
                        <div class="av-bulk-control">
                            <label class="gi-field av-bulk-turma-select">
                                <span>Turma</span>
                                <select wire:model.live="turmaEmMassaGlobal" @disabled(! $this->podeResponder())>
                                    <option value="">Todas as turmas</option>
                                    @foreach ($turmasDaSerie as $turmaItem)
                                    <option value="{{ $turmaItem->id }}">{{ $this->rotuloTurma($turmaItem) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="gi-field av-bulk-select">
                                <span>Avaliacao em massa</span>
                                <select wire:model.live="avaliacaoEmMassaGlobal" @disabled(! $this->podeResponder())>
                                    <option value="">Selecione uma alternativa</option>
                                    @foreach ($this->alternativasEmMassaDisponiveis as $alternativa)
                                    <option value="{{ $alternativa['id'] }}">
                                        {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observacao)' : '' }}
                                    </option>
                                    @endforeach
                                </select>
                            </label>

                            <button
                                type="button"
                                class="gi-action"
                                wire:click="aplicarEmMassaNaSerie"
                                wire:confirm="Tem certeza que deseja aplicar a mesma resposta para todos os alunos do escopo selecionado? Campos com observacao ja preenchida nao serao alterados."
                                wire:loading.attr="disabled"
                                wire:target="aplicarEmMassaNaSerie"
                                @disabled(! $this->podeResponder())>
                                Aplicar
                            </button>
                        </div>

                        <div class="av-segmented-control" role="tablist">
                            <button type="button" class="{{ $visualizacao === 'pautas' ? 'is-active' : '' }}" wire:click="definirVisualizacao('pautas')">
                                Por pautas
                            </button>
                            <button type="button" class="{{ $visualizacao === 'alunos' ? 'is-active' : '' }}" wire:click="definirVisualizacao('alunos')">
                                Por alunos
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            @if ($visualizacao === 'pautas')
            <div class="av-stack">
                @foreach ($turmasDaSerie as $turmaItem)
                @php($turmaId = (int) $turmaItem->id)
                @php($turmaExpandida = $this->turmaEstaExpandida($turmaId))
                @php($progressoTurma = $this->progressoPorTurma[$turmaId] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'concluida' => false])
                @php($alunosDaTurma = $this->alunosDaTurma($turmaId))
                @php($pautasDaTurma = $this->pautasDaTurma($turmaId))

                <section wire:key="turma-pautas-{{ $turmaId }}" class="gi-panel av-turma-section {{ $turmaExpandida ? 'is-open' : '' }}">
                    <button type="button" class="av-pauta-toggle" wire:click="alternarTurma({{ $turmaId }})">
                        <div class="av-pauta-toggle-main">
                            <h3 class="av-pauta-title">{{ $this->rotuloTurma($turmaItem) }}</h3>
                            <p class="av-pauta-meta">{{ $turmaItem->escola?->nome }} - {{ $turmaItem->serie?->nome }}</p>
                        </div>

                        <div class="av-pauta-toggle-side">
                            <div class="av-pauta-progress-head">
                                <span>{{ $progressoTurma['preenchidas'] }}/{{ $progressoTurma['total'] }}</span>
                                <span>{{ $progressoTurma['percentual'] }}%</span>
                            </div>
                            <div class="av-progress-track av-progress-track--compact">
                                <div class="av-progress-bar" style="width: {{ $progressoTurma['percentual'] }}%"></div>
                            </div>
                            <div class="av-pauta-toggle-meta">
                                @if ($progressoTurma['concluida'])
                                <span class="av-pauta-check">Concluida</span>
                                @else
                                <span class="av-pauta-check av-pauta-check--pending">Em andamento</span>
                                @endif

                                <span class="av-pauta-arrow {{ $turmaExpandida ? 'is-open' : '' }}">v</span>
                            </div>
                        </div>
                    </button>

                    @if ($turmaExpandida)
                    <div class="av-turma-content">
                        @forelse ($pautasDaTurma as $pauta)
                        @php($progressoPauta = $this->progressoPorPauta[$turmaId][$pauta->id] ?? ['preenchidas' => 0, 'total' => $alunosDaTurma->count(), 'percentual' => 0, 'concluida' => false])
                        @php($pautaExpandida = $this->pautaEstaExpandida($turmaId, (int) $pauta->id))
                        @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))

                        <section wire:key="turma-{{ $turmaId }}-pauta-{{ $pauta->id }}" class="av-pauta-section av-pauta-section--nested {{ $pautaExpandida ? 'is-open' : '' }}">
                            <button type="button" class="av-pauta-toggle" wire:click="alternarPauta({{ $turmaId }}, {{ $pauta->id }})">
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

                                        <span class="av-pauta-arrow {{ $pautaExpandida ? 'is-open' : '' }}">v</span>
                                    </div>
                                </div>
                            </button>

                            @if ($pautaExpandida)
                            <div class="av-pauta-content">
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
                                            @foreach ($alunosDaTurma as $aluno)
                                            <tr wire:key="turma-{{ $turmaId }}-pauta-{{ $pauta->id }}-aluno-{{ $aluno->id }}">
                                                <td>
                                                    <strong>{{ $aluno->nome }}</strong>
                                                    <small>CGM: {{ $aluno->cgm }}</small>
                                                </td>
                                                <td>
                                                    <div class="av-input-wrap">
                                                        <select class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id" @disabled(! $this->podeResponder())>
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
                                                    @php($observacaoAtual = (string) ($respostas[$pauta->id][$aluno->id]['observacao'] ?? ''))
                                                    <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                        <textarea
                                                            x-ref="field"
                                                            x-on:input="count = $event.target.value.length"
                                                            maxlength="1500"
                                                            placeholder="Observacao obrigatoria"
                                                            class="av-table-input av-textarea-input"
                                                            wire:model.live.debounce.500ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                            @disabled(! $this->podeResponder())></textarea>

                                                        <span class="av-saving-indicator" wire:loading.flex wire:target="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao">
                                                            <span class="av-spinner"></span>
                                                            Salvando...
                                                        </span>
                                                        <div class="av-field-meta">
                                                            <small class="av-field-hint av-field-hint--danger">Obrigatoria para esta alternativa.</small>
                                                            <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                        </div>
                                                    </div>
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
                        @empty
                        <section class="av-note av-note--warning">
                            Esta turma nao possui pautas disponiveis nesta serie.
                        </section>
                        @endforelse

                       
                    </div>
                    @endif
                </section>
                @endforeach
            </div>
            @else
            <div class="av-stack">
                @foreach ($turmasDaSerie as $turmaItem)
                @php($turmaId = (int) $turmaItem->id)
                @php($turmaExpandida = $this->turmaEstaExpandida($turmaId))
                @php($progressoTurma = $this->progressoPorTurma[$turmaId] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'concluida' => false])
                @php($alunosDaTurma = $this->alunosDaTurma($turmaId))
                @php($pautasDaTurma = $this->pautasDaTurma($turmaId))

                <section wire:key="turma-alunos-{{ $turmaId }}" class="gi-panel av-turma-section {{ $turmaExpandida ? 'is-open' : '' }}">
                    <button type="button" class="av-pauta-toggle" wire:click="alternarTurma({{ $turmaId }})">
                        <div class="av-pauta-toggle-main">
                            <h3 class="av-pauta-title">{{ $this->rotuloTurma($turmaItem) }}</h3>
                            <p class="av-pauta-meta">{{ $turmaItem->escola?->nome }} - {{ $turmaItem->serie?->nome }}</p>
                        </div>

                        <div class="av-pauta-toggle-side">
                            <div class="av-pauta-progress-head">
                                <span>{{ $progressoTurma['preenchidas'] }}/{{ $progressoTurma['total'] }}</span>
                                <span>{{ $progressoTurma['percentual'] }}%</span>
                            </div>
                            <div class="av-progress-track av-progress-track--compact">
                                <div class="av-progress-bar" style="width: {{ $progressoTurma['percentual'] }}%"></div>
                            </div>
                            <div class="av-pauta-toggle-meta">
                                @if ($progressoTurma['concluida'])
                                <span class="av-pauta-check">Concluida</span>
                                @else
                                <span class="av-pauta-check av-pauta-check--pending">Em andamento</span>
                                @endif

                                <span class="av-pauta-arrow {{ $turmaExpandida ? 'is-open' : '' }}">v</span>
                            </div>
                        </div>
                    </button>

                    @if ($turmaExpandida)
                    <div class="av-turma-content">
                        @forelse ($alunosDaTurma as $aluno)
                        @php($progressoAluno = $this->progressoPorAluno[$aluno->id] ?? ['preenchidas' => 0, 'total' => $pautasDaTurma->count(), 'percentual' => 0, 'concluida' => false])
                        @php($alunoExpandido = $this->alunoEstaExpandido($turmaId, (int) $aluno->id))

                        <section wire:key="turma-{{ $turmaId }}-aluno-{{ $aluno->id }}" class="av-pauta-section av-pauta-section--nested {{ $alunoExpandido ? 'is-open' : '' }}">
                            <button type="button" class="av-pauta-toggle" wire:click="alternarAluno({{ $turmaId }}, {{ $aluno->id }})">
                                <div class="av-pauta-toggle-main">
                                    <h3 class="av-pauta-title">{{ $aluno->nome }}</h3>
                                    <p class="av-pauta-meta">CGM: {{ $aluno->cgm }}</p>
                                </div>

                                <div class="av-pauta-toggle-side">
                                    <div class="av-pauta-progress-head">
                                        <span>{{ $progressoAluno['preenchidas'] }}/{{ $progressoAluno['total'] }}</span>
                                        <span>{{ $progressoAluno['percentual'] }}%</span>
                                    </div>
                                    <div class="av-progress-track av-progress-track--compact">
                                        <div class="av-progress-bar" style="width: {{ $progressoAluno['percentual'] }}%"></div>
                                    </div>
                                    <div class="av-pauta-toggle-meta">
                                        @if ($progressoAluno['concluida'])
                                        <span class="av-pauta-check">Concluida</span>
                                        @else
                                        <span class="av-pauta-check av-pauta-check--pending">Em andamento</span>
                                        @endif

                                        <span class="av-pauta-arrow {{ $alunoExpandido ? 'is-open' : '' }}">v</span>
                                    </div>
                                </div>
                            </button>

                            @if ($alunoExpandido)
                            <div class="av-pauta-content">
                                @foreach ($this->pautasAgrupadasPorComponenteDaTurma($turmaId) as $componenteNome => $pautasDoComponente)
                                <section class="av-aluno-componente">
                                    @php($componenteId = (int) ($pautasDoComponente->first()?->componente_curricular_id ?? 0))
                                    <h4>{{ $componenteNome }}</h4>

                                    <div class="gi-table-wrap">
                                        <table class="gi-table">
                                            <thead>
                                                <tr>
                                                    <th>Pauta</th>
                                                    <th>Alternativa</th>
                                                    <th>Observacao da pauta</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                @foreach ($pautasDoComponente as $pauta)
                                                @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))
                                                <tr wire:key="turma-{{ $turmaId }}-aluno-{{ $aluno->id }}-pauta-{{ $pauta->id }}">
                                                    <td>
                                                        <strong>{{ $pauta->texto }}</strong>
                                                    </td>
                                                    <td>
                                                        <div class="av-input-wrap">
                                                            <select class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id" @disabled(! $this->podeResponder())>
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
                                                        @php($observacaoAtual = (string) ($respostas[$pauta->id][$aluno->id]['observacao'] ?? ''))
                                                        <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                            <textarea
                                                                x-ref="field"
                                                                x-on:input="count = $event.target.value.length"
                                                                maxlength="1500"
                                                                placeholder="Observacao obrigatoria"
                                                                class="av-table-input av-textarea-input"
                                                                wire:model.live.debounce.500ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                                @disabled(! $this->podeResponder())></textarea>

                                                            <span class="av-saving-indicator" wire:loading.flex wire:target="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao">
                                                                <span class="av-spinner"></span>
                                                                Salvando...
                                                            </span>
                                                            <div class="av-field-meta">
                                                                <small class="av-field-hint av-field-hint--danger">Obrigatoria para esta alternativa.</small>
                                                                <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                            </div>
                                                        </div>
                                                        @else
                                                        <small class="av-field-hint">Somente alternativas com observacao habilitam este campo.</small>
                                                        @endif
                                                    </td>
                                                </tr>
                                                @endforeach
                                            </tbody>
                                        </table>
                                    </div>

                                    @php($informacoesAtuais = (string) ($informacoesComplementares[$componenteId][$aluno->id] ?? ''))
                                    <div class="av-complementary-section">
                                        <h4>Informacoes complementares do componente</h4>
                                        <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($informacoesAtuais)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                            <textarea
                                                x-ref="field"
                                                x-on:input="count = $event.target.value.length"
                                                maxlength="1500"
                                                placeholder="Informacoes complementares (opcional)"
                                                class="av-table-input av-textarea-input"
                                                wire:model.live.debounce.600ms="informacoesComplementares.{{ $componenteId }}.{{ $aluno->id }}"
                                                @disabled(! $this->podeResponder())></textarea>

                                            <span class="av-saving-indicator" wire:loading.flex wire:target="informacoesComplementares.{{ $componenteId }}.{{ $aluno->id }}">
                                                <span class="av-spinner"></span>
                                                Salvando...
                                            </span>
                                            <div class="av-field-meta">
                                                <span></span>
                                                <small class="av-char-count" x-text="`${count}/1500`"></small>
                                            </div>
                                        </div>
                                    </div>
                                </section>
                                @endforeach
                            </div>
                            @endif
                        </section>
                        @empty
                        <section class="av-note av-note--warning">
                            Esta turma nao possui alunos cadastrados.
                        </section>
                        @endforelse

                    </div>
                    @endif
                </section>
                @endforeach
            </div>
            @endif

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div></div>
                    <div class="gi-toolbar-right">
                        <button type="button" class="gi-action gi-action--primary" wire:click="salvarRespostas" @disabled(! $this->podeResponder())>
                            Validar pendencias da serie
                        </button>
                    </div>
                </div>
            </section>
            @endif
        </div>

        @include('filament.pages.partials.avaliacoes-page-styles')
    </div>
</x-filament-panels::page>