<div
    class="av-livewire-root"
    data-av-autosave-root
    data-av-autosave-url="{{ route('avaliacoes.respostas.autosave') }}"
    x-data="{
        alteracoesPendentes: false,
        autosaveEmAndamento: false,
        autosaveConfirmado: false,
        autosaveFalhou: false,
        confirmacaoTimer: null,
    }"
    x-on:input.capture="if ($event.target.matches('[data-av-editavel]')) {
        clearTimeout(confirmacaoTimer);
        alteracoesPendentes = true;
        autosaveEmAndamento = false;
        autosaveConfirmado = false;
        autosaveFalhou = false;
    }"
    x-on:change.capture="if ($event.target.matches('[data-av-editavel]')) {
        clearTimeout(confirmacaoTimer);
        alteracoesPendentes = true;
        autosaveEmAndamento = false;
        autosaveConfirmado = false;
        autosaveFalhou = false;
    }"
    x-on:avaliacao-alterada="
        clearTimeout(confirmacaoTimer);
        alteracoesPendentes = true;
        autosaveEmAndamento = true;
        autosaveConfirmado = false;
        autosaveFalhou = false;
    "
    x-on:avaliacao-salva.window="
        alteracoesPendentes = false;
        autosaveEmAndamento = false;
        autosaveConfirmado = false;
        autosaveFalhou = false;
    "
    x-on:avaliacao-autosave-confirmado.window="
        alteracoesPendentes = false;
        autosaveEmAndamento = false;
        autosaveConfirmado = true;
        autosaveFalhou = false;
        clearTimeout(confirmacaoTimer);
        confirmacaoTimer = setTimeout(() => autosaveConfirmado = false, 1800);
    "
    x-on:avaliacao-autosave-falhou.window="
        alteracoesPendentes = true;
        autosaveEmAndamento = false;
        autosaveConfirmado = false;
        autosaveFalhou = true;
    ">
    <div class="gi-page av-page av-professor-page">
        @if ($this->interfaceProfessorEmLista())
            @include('livewire.avaliacoes.partials.avaliacao-turma-professor-content')
        @else
        @if ($this->modoAcompanhamento())
            <section class="gi-panel av-professor-control-panel">
                @php($progresso = $this->progresso)
                @php($percentual = $progresso['total'] > 0 ? min(100, (int) round(($progresso['preenchidas'] / $progresso['total']) * 100)) : 0)
                <div class="av-progress" data-av-progress-type="global" data-av-progress-filled="{{ $progresso['preenchidas'] }}" data-av-progress-total="{{ $progresso['total'] }}">
                    <div class="av-progress-head">
                        <span>Progresso do preenchimento</span>
                        <span>{{ $progresso['preenchidas'] }}/{{ $progresso['total'] }}</span>
                    </div>
                    <div class="av-progress-track">
                        <div class="av-progress-bar" style="width: {{ $percentual }}%"></div>
                    </div>
                </div>
            </section>
        @else
            <section class="gi-panel av-professor-control-panel">
                <div class="av-form-grid av-form-grid--two">
                    <label class="gi-field">
                        <span>Avaliação</span>
                        <select wire:model.live="avaliacao">
                            <option value="">Selecione uma avaliação</option>
                            @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                                <option value="{{ $avaliacaoItem->id }}">
                                    {{ $avaliacaoItem->nome }} |
                                    {{ optional($avaliacaoItem->data_inicio)->format('d/m/Y') }}
                                    até
                                    {{ optional($avaliacaoItem->data_fim)->format('d/m/Y') }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label class="gi-field">
                        <span>Série</span>
                        <select wire:model.live="serieEscola" @disabled(! $avaliacao)>
                            <option value="">Selecione uma série</option>
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
                <div class="av-progress" data-av-progress-type="global" data-av-progress-filled="{{ $progresso['preenchidas'] }}" data-av-progress-total="{{ $progresso['total'] }}">
                    <div class="av-progress-head">
                        <span>Progresso do preenchimento</span>
                        <span>{{ $progresso['preenchidas'] }}/{{ $progresso['total'] }}</span>
                    </div>
                    <div class="av-progress-track">
                        <div class="av-progress-bar" style="width: {{ $percentual }}%"></div>
                    </div>
                </div>
            </section>
        @endif

        @if (! $this->podeResponder())
            <section class="av-note">
                Modo leitura: este perfil pode acompanhar, mas não editar as avaliações neste contexto.
            </section>
        @endif

        @if ($modo === 'professor' && $this->avaliacoesDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Não existem avaliações pendentes para seus componentes neste momento.
            </section>
        @elseif (! $avaliacao)
            <section class="av-note">
                Selecione uma avaliação para começar.
            </section>
        @elseif ($modo === 'professor' && $this->seriesPorEscolaDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Esta avaliação não possui séries com turmas vinculadas aos componentes disponíveis para este usuário.
            </section>
        @elseif ($modo === 'professor' && ! $serieEscola)
            <section class="av-note">
                Selecione a série para visualizar as turmas, pautas e alunos.
            </section>
        @elseif ($this->turmasDaSerieDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Não há turmas disponíveis para esta avaliação no escopo atual.
            </section>
        @elseif ($this->pautasDisponiveis->isEmpty())
            <section class="av-note av-note--warning">
                Nenhuma pauta desta avaliação está disponível para o recorte atual.
            </section>
        @else
            @php($turmasDaSerie = $this->turmasDaSerieDisponiveis)

            <section class="gi-panel av-professor-control-panel">
                <div class="gi-toolbar">
                    @if ($this->podePreencherEmMassa())
                    <div>
                        <h3 class="av-pauta-title">Ações da Avaliação</h3>
                        <p class="av-pauta-meta">Escolha como deseja preencher esta avaliação.</p>
                    </div>
                    @endif

                    <div class="av-mode-actions">
                        @if ($this->podePreencherEmMassa())
                        <div class="av-bulk-control {{ $this->modoAcompanhamento() ? 'av-bulk-control--acompanhamento' : '' }}">
                            @if (! $this->modoAcompanhamento())
                                <label class="gi-field av-bulk-turma-select">
                                    <span>Turma</span>
                                    <select wire:model.live="turmaEmMassaGlobal" @disabled(! $this->podePreencherEmMassa())>
                                        <option value="">Todas as turmas</option>
                                        @foreach ($turmasDaSerie as $turmaItem)
                                            <option value="{{ $turmaItem->id }}">{{ $this->rotuloTurma($turmaItem) }}</option>
                                        @endforeach
                                    </select>
                                </label>
                            @endif

                            <label class="gi-field av-bulk-select">
                                <span>Aluno</span>
                                <select wire:model.live="alunoEmMassaGlobal" @disabled(! $this->podePreencherEmMassa())>
                                    <option value="">Todos os alunos</option>
                                    @foreach ($this->alunosEmMassaDisponiveis as $alunoItem)
                                        <option value="{{ $alunoItem->id }}">{{ $this->rotuloAlunoEmMassa($alunoItem) }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="gi-field av-bulk-select">
                                <span>Componente</span>
                                <select wire:model.live="componenteEmMassaGlobal" @disabled(! $this->podePreencherEmMassa())>
                                    <option value="">Todos os componentes</option>
                                    @foreach ($this->componentesEmMassaDisponiveis as $componenteId => $componenteNome)
                                        <option value="{{ $componenteId }}">{{ $componenteNome }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <label class="gi-field av-bulk-select">
                                <span>Avaliação em massa</span>
                                <select wire:model.live="avaliacaoEmMassaGlobal" @disabled(! $this->podePreencherEmMassa())>
                                    <option value="">Selecione uma alternativa</option>
                                    @foreach ($this->alternativasEmMassaDisponiveis as $alternativa)
                                        <option value="{{ $alternativa['id'] }}">
                                            {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                                        </option>
                                    @endforeach
                                </select>
                            </label>

                            <button
                                type="button"
                                class="gi-action"
                                wire:click="aplicarEmMassaNaSerie"
                                wire:confirm="Tem certeza que deseja aplicar a mesma resposta para todos os alunos do escopo selecionado? Campos com observação já preenchida não serão alterados."
                                wire:loading.attr="disabled"
                                wire:target="aplicarEmMassaNaSerie"
                                @disabled(! $this->podePreencherEmMassa())>
                                Aplicar
                            </button>
                        </div>
                        @endif

                        @if ($this->podeAlternarVisualizacao())
                        <div class="av-segmented-control" role="tablist">
                            <button type="button" class="{{ $visualizacao === 'pautas' ? 'is-active' : '' }}" wire:click="definirVisualizacao('pautas')">Por pautas</button>
                            <button type="button" class="{{ $visualizacao === 'alunos' ? 'is-active' : '' }}" wire:click="definirVisualizacao('alunos')">Por alunos</button>
                        </div>
                        @endif
                    </div>
                </div>
            </section>

            @if ($visualizacao === 'pautas')
                @if ($this->modoAcompanhamento())
                    @php($turmaAtual = $turmasDaSerie->first())
                    @php($turmaIdAtual = (int) ($turmaAtual?->id ?? 0))
                    @php($alunosDaTurma = $turmaIdAtual > 0 ? $this->alunosDaTurma($turmaIdAtual) : collect())
                    <div class="av-stack">
                        @forelse ($turmaIdAtual > 0 ? $this->gruposPorComponenteDaTurma($turmaIdAtual) : collect() as $grupo)
                            @php($componenteExpandido = $this->componenteEstaExpandido($turmaIdAtual, (int) $grupo['componente_id']))
                            @php($progressoComponentePreenchidas = collect($grupo['pautas'])->sum(fn ($pauta) => (int) ($this->progressoPorPauta[$turmaIdAtual][$pauta->id]['preenchidas'] ?? 0)))
                            @php($progressoComponenteTotal = collect($grupo['pautas'])->sum(fn ($pauta) => (int) ($this->progressoPorPauta[$turmaIdAtual][$pauta->id]['total'] ?? $alunosDaTurma->count())))
                            @php($progressoComponentePercentual = $progressoComponenteTotal > 0 ? min(100, (int) round(($progressoComponentePreenchidas / $progressoComponenteTotal) * 100)) : 0)
                            <section wire:key="workspace-acompanhamento-componente-pautas-{{ $turmaIdAtual }}-{{ $grupo['componente_id'] }}" class="gi-panel av-turma-section {{ $componenteExpandido ? 'is-open' : '' }}">
                                <button type="button" class="av-pauta-toggle" wire:click="alternarComponente({{ $turmaIdAtual }}, {{ $grupo['componente_id'] }})">
                                    <div class="av-pauta-toggle-main">
                                        <h3 class="av-pauta-title">{{ $grupo['titulo'] }}</h3>
                                        <p class="av-pauta-meta">{{ $turmaAtual?->escola?->nome }} - {{ $turmaAtual?->serie?->nome }} - {{ $this->rotuloTurma($turmaAtual) }}</p>
                                    </div>
                                    <div class="av-pauta-toggle-side">
                                        <div class="av-pauta-progress-head" data-av-progress-type="componente" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-pautas="{{ collect($grupo['pautas'])->pluck('id')->implode(',') }}" data-av-progress-filled="{{ $progressoComponentePreenchidas }}" data-av-progress-total="{{ $progressoComponenteTotal }}">
                                            <span>{{ $progressoComponentePreenchidas }}/{{ $progressoComponenteTotal }}</span>
                                            <span>{{ $progressoComponentePercentual }}%</span>
                                        </div>
                                        <div class="av-progress-track av-progress-track--compact">
                                            <div class="av-progress-bar" style="width: {{ $progressoComponentePercentual }}%"></div>
                                        </div>
                                        <span class="av-pauta-arrow {{ $componenteExpandido ? 'is-open' : '' }}">v</span>
                                    </div>
                                </button>

                                @if ($componenteExpandido)
                                <div class="av-turma-content">
                                    @foreach ($grupo['pautas'] as $pauta)
                                        @php($progressoPauta = $this->progressoPorPauta[$turmaIdAtual][$pauta->id] ?? ['preenchidas' => 0, 'total' => $alunosDaTurma->count(), 'percentual' => 0, 'concluida' => false])
                                        @php($pautaExpandida = $this->pautaEstaExpandida($turmaIdAtual, (int) $pauta->id))
                                        @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))

                                        <section wire:key="workspace-acompanhamento-componente-{{ $grupo['componente_id'] }}-pauta-{{ $pauta->id }}" class="av-pauta-section av-pauta-section--nested {{ $pautaExpandida ? 'is-open' : '' }}">
                                            <button type="button" class="av-pauta-toggle" wire:click="alternarPauta({{ $turmaIdAtual }}, {{ $pauta->id }})">
                                                <div class="av-pauta-toggle-main">
                                                    <h3 class="av-pauta-title">{{ $pauta->texto }}</h3>
                                                    <p class="av-pauta-meta">{{ $grupo['titulo'] }}</p>
                                                </div>

                                                <div class="av-pauta-toggle-side">
                                                    <div class="av-pauta-progress-head" data-av-progress-type="pauta" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-pauta="{{ $pauta->id }}" data-av-progress-filled="{{ $progressoPauta['preenchidas'] }}" data-av-progress-total="{{ $progressoPauta['total'] }}">
                                                        <span>{{ $progressoPauta['preenchidas'] }}/{{ $progressoPauta['total'] }}</span>
                                                        <span>{{ $progressoPauta['percentual'] }}%</span>
                                                    </div>
                                                    <div class="av-progress-track av-progress-track--compact">
                                                        <div class="av-progress-bar" style="width: {{ $progressoPauta['percentual'] }}%"></div>
                                                    </div>
                                                    <div class="av-pauta-toggle-meta">
                                                        <span class="av-pauta-check {{ $progressoPauta['concluida'] ? '' : 'av-pauta-check--pending' }}">
                                                            {{ $progressoPauta['concluida'] ? 'Concluída' : 'Em andamento' }}
                                                        </span>
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
                                                                    <th>Observação da pauta</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($alunosDaTurma as $aluno)
                                                                    @php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))
                                                                    @php($respostaBloqueada = $this->respostaEstaBloqueada((int) $pauta->id, (int) $aluno->id))
                                                                    @php($referenciaOrigem = $respostas[$pauta->id][$aluno->id]['origem_referencia'] ?? null)
                                                                    <tr wire:key="workspace-acompanhamento-componente-{{ $grupo['componente_id'] }}-pauta-{{ $pauta->id }}-aluno-{{ $aluno->id }}">
                                                                        <td data-label="Aluno">
                                                                            <strong>{{ $aluno->nome }}</strong>
                                                                            <small>CGM: {{ $aluno->cgm }}</small>
                                                                            @if ($alunoBloqueadoTransferencia)
                                                                                <small>Aluno pendente de transferência. Avaliação bloqueada até o parecer da escola de origem.</small>
                                                                            @endif
                                                                            @if ($respostaBloqueada)
                                                                                <small>Resposta bloqueada por histórico.</small>
                                                                            @endif
                                                                            @if ($referenciaOrigem)
                                                                                <small>Origem: {{ $referenciaOrigem['alternativa'] !== '' ? $referenciaOrigem['alternativa'] : 'Não avaliado' }}{{ $referenciaOrigem['observacao'] !== '' ? ' | '.$referenciaOrigem['observacao'] : '' }}</small>
                                                                            @endif
                                                                        </td>
                                                                        <td data-label="Alternativa">
                                                                            <div class="av-input-wrap">
                                                                                <select data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}" class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id" @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)>
                                                                                    <option value="">Selecione</option>
                                                                                    @foreach ($alternativasPauta as $alternativa)
                                                                                        <option value="{{ $alternativa['id'] }}" data-requires-observation="{{ ($alternativa['tem_observacao'] ?? false) ? 1 : 0 }}">
                                                                                            {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>

                                                                            </div>
                                                                        </td>
                                                                        <td data-label="Observação da pauta">
                                                                            @php($alternativaSelecionadaId = (int) ($respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0))
                                                                            @php($requerObservacao = $this->alternativaRequerObservacao((int) $pauta->id, $alternativaSelecionadaId))

                                                                            @if ($requerObservacao)
                                                                                @php($observacaoAtual = (string) ($respostas[$pauta->id][$aluno->id]['observacao'] ?? ''))
                                                                                <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                                                    <textarea
                                                                                        x-ref="field"
                                                                                        x-on:input="count = $event.target.value.length"
                                                                                        maxlength="1500"
                                                                                        placeholder="{{ $this->placeholderObservacaoAlternativa((int) $pauta->id, $alternativaSelecionadaId) }}"
                                                                                        class="av-table-input av-textarea-input"
                                                                                    data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}"
                                                                                    wire:model.live.debounce.700ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                                                        @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)></textarea>

                                                                                    <div class="av-field-meta">
                                                                                        <small class="av-field-hint av-field-hint--danger">Obrigatória para esta alternativa.</small>
                                                                                        <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                                                    </div>
                                                                                </div>
                                                                            @else
                                                                                <small class="av-field-hint">Somente alternativas com observação habilitam este campo.</small>
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
                                @endif
                            </section>
                        @empty
                            <section class="av-note av-note--warning">
                                Nenhuma pauta desta avaliação está disponível para o recorte atual.
                            </section>
                        @endforelse
                    </div>
                @else
                <div class="av-stack {{ $turma ? '' : 'av-turma-grid' }}">
                    @foreach ($turmasDaSerie as $turmaItem)
                        @php($turmaIdAtual = (int) $turmaItem->id)
                        @php($progressoTurma = $this->progressoPorTurma[$turmaIdAtual] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'concluida' => false])
                        @php($alunosDaTurma = $this->alunosDaTurma($turmaIdAtual))
                        @php($gruposDaTurma = $this->gruposPorComponenteDaTurma($turmaIdAtual))
                        <section wire:key="workspace-turma-pautas-{{ $turmaIdAtual }}" x-data="{ aberto: false }" :class="{ 'is-open': aberto }" class="gi-panel av-turma-section">
                            <button type="button" class="av-pauta-toggle" x-on:click="aberto = ! aberto">
                                <div class="av-pauta-toggle-main">
                                    <h3 class="av-pauta-title">{{ $this->rotuloTurma($turmaItem) }}</h3>
                                    <p class="av-pauta-meta">{{ $turmaItem->escola?->nome }} - {{ $turmaItem->serie?->nome }}</p>
                                </div>

                                <div class="av-pauta-toggle-side">
                                    <div class="av-pauta-progress-head" data-av-progress-type="turma" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-filled="{{ $progressoTurma['preenchidas'] }}" data-av-progress-total="{{ $progressoTurma['total'] }}">
                                        <span>{{ $progressoTurma['preenchidas'] }}/{{ $progressoTurma['total'] }}</span>
                                        <span>{{ $progressoTurma['percentual'] }}%</span>
                                    </div>
                                    <div class="av-progress-track av-progress-track--compact">
                                        <div class="av-progress-bar" style="width: {{ $progressoTurma['percentual'] }}%"></div>
                                    </div>
                                    <div class="av-pauta-toggle-meta">
                                        <span class="av-pauta-check {{ $progressoTurma['concluida'] ? '' : 'av-pauta-check--pending' }}">
                                            {{ $progressoTurma['concluida'] ? 'Concluída' : 'Em andamento' }}
                                        </span>
                                        <span class="av-pauta-arrow" :class="{ 'is-open': aberto }">v</span>
                                    </div>
                                </div>
                            </button>

                                <div class="av-turma-content" x-show="aberto" x-cloak>
                                    @forelse ($gruposDaTurma as $grupo)
                                        @php($componenteExpandido = $this->componenteEstaExpandido($turmaIdAtual, (int) $grupo['componente_id']))
                                        @php($progressoComponentePreenchidas = collect($grupo['pautas'])->sum(fn ($pauta) => (int) ($this->progressoPorPauta[$turmaIdAtual][$pauta->id]['preenchidas'] ?? 0)))
                                        @php($progressoComponenteTotal = collect($grupo['pautas'])->sum(fn ($pauta) => (int) ($this->progressoPorPauta[$turmaIdAtual][$pauta->id]['total'] ?? $alunosDaTurma->count())))
                                        @php($progressoComponentePercentual = $progressoComponenteTotal > 0 ? min(100, (int) round(($progressoComponentePreenchidas / $progressoComponenteTotal) * 100)) : 0)
                                        <section wire:key="workspace-turma-{{ $turmaIdAtual }}-componente-{{ $grupo['componente_id'] }}" class="av-pauta-section av-pauta-section--nested {{ $componenteExpandido ? 'is-open' : '' }}">
                                            <button type="button" class="av-pauta-toggle" wire:click="alternarComponente({{ $turmaIdAtual }}, {{ $grupo['componente_id'] }})">
                                                <div class="av-pauta-toggle-main">
                                                    <h3 class="av-pauta-title">{{ $grupo['titulo'] }}</h3>
                                                    <p class="av-pauta-meta">Agrupado por componente</p>
                                                </div>

                                                <div class="av-pauta-toggle-side">
                                                    <div class="av-pauta-progress-head" data-av-progress-type="componente" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-pautas="{{ collect($grupo['pautas'])->pluck('id')->implode(',') }}" data-av-progress-filled="{{ $progressoComponentePreenchidas }}" data-av-progress-total="{{ $progressoComponenteTotal }}">
                                                        <span>{{ $progressoComponentePreenchidas }}/{{ $progressoComponenteTotal }}</span>
                                                        <span>{{ $progressoComponentePercentual }}%</span>
                                                    </div>
                                                    <div class="av-progress-track av-progress-track--compact">
                                                        <div class="av-progress-bar" style="width: {{ $progressoComponentePercentual }}%"></div>
                                                    </div>
                                                    <span class="av-pauta-arrow {{ $componenteExpandido ? 'is-open' : '' }}">v</span>
                                                </div>
                                            </button>

                                            @if ($componenteExpandido)
                                                <div class="av-pauta-content">
                                    @foreach ($grupo['pautas'] as $pauta)
                                        @php($progressoPauta = $this->progressoPorPauta[$turmaIdAtual][$pauta->id] ?? ['preenchidas' => 0, 'total' => $alunosDaTurma->count(), 'percentual' => 0, 'concluida' => false])
                                        @php($pautaExpandida = $this->pautaEstaExpandida($turmaIdAtual, (int) $pauta->id))
                                        @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))

                                        <section wire:key="workspace-turma-{{ $turmaIdAtual }}-pauta-{{ $pauta->id }}" class="av-pauta-section av-pauta-section--nested {{ $pautaExpandida ? 'is-open' : '' }}">
                                            <button type="button" class="av-pauta-toggle" wire:click="alternarPauta({{ $turmaIdAtual }}, {{ $pauta->id }})">
                                                <div class="av-pauta-toggle-main">
                                                    <h3 class="av-pauta-title">{{ $pauta->texto }}</h3>
                                                    <p class="av-pauta-meta">
                                                        Componente: {{ $pauta->componente?->nome ?? 'Geral (sem componente especifico)' }}
                                                    </p>
                                                </div>

                                                <div class="av-pauta-toggle-side">
                                                    <div class="av-pauta-progress-head" data-av-progress-type="pauta" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-pauta="{{ $pauta->id }}" data-av-progress-filled="{{ $progressoPauta['preenchidas'] }}" data-av-progress-total="{{ $progressoPauta['total'] }}">
                                                        <span>{{ $progressoPauta['preenchidas'] }}/{{ $progressoPauta['total'] }}</span>
                                                        <span>{{ $progressoPauta['percentual'] }}%</span>
                                                    </div>
                                                    <div class="av-progress-track av-progress-track--compact">
                                                        <div class="av-progress-bar" style="width: {{ $progressoPauta['percentual'] }}%"></div>
                                                    </div>
                                                    <div class="av-pauta-toggle-meta">
                                                        <span class="av-pauta-check {{ $progressoPauta['concluida'] ? '' : 'av-pauta-check--pending' }}">
                                                            {{ $progressoPauta['concluida'] ? 'Concluída' : 'Em andamento' }}
                                                        </span>
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
                                                                    <th>Observação da pauta</th>
                                                                </tr>
                                                            </thead>
                                                            <tbody>
                                                                @foreach ($alunosDaTurma as $aluno)
                                                                    @php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))
                                                                    @php($respostaBloqueada = $this->respostaEstaBloqueada((int) $pauta->id, (int) $aluno->id))
                                                                    @php($referenciaOrigem = $respostas[$pauta->id][$aluno->id]['origem_referencia'] ?? null)
                                                                    <tr wire:key="workspace-turma-{{ $turmaIdAtual }}-pauta-{{ $pauta->id }}-aluno-{{ $aluno->id }}">
                                                                        <td data-label="Aluno">
                                                                            <strong>{{ $aluno->nome }}</strong>
                                                                            <small>CGM: {{ $aluno->cgm }}</small>
                                                                            @if ($alunoBloqueadoTransferencia)
                                                                                <small>Aluno pendente de transferência. Avaliação bloqueada até o parecer da escola de origem.</small>
                                                                            @endif
                                                                            @if ($respostaBloqueada)
                                                                                <small>Resposta bloqueada por histórico.</small>
                                                                            @endif
                                                                            @if ($referenciaOrigem)
                                                                                <small>Origem: {{ $referenciaOrigem['alternativa'] !== '' ? $referenciaOrigem['alternativa'] : 'Não avaliado' }}{{ $referenciaOrigem['observacao'] !== '' ? ' | '.$referenciaOrigem['observacao'] : '' }}</small>
                                                                            @endif
                                                                        </td>
                                                                        <td data-label="Alternativa">
                                                                            <div class="av-input-wrap">
                                                                                <select data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}" class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id" @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)>
                                                                                    <option value="">Selecione</option>
                                                                                    @foreach ($alternativasPauta as $alternativa)
                                                                                        <option value="{{ $alternativa['id'] }}" data-requires-observation="{{ ($alternativa['tem_observacao'] ?? false) ? 1 : 0 }}">
                                                                                            {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                                                                                        </option>
                                                                                    @endforeach
                                                                                </select>

                                                                            </div>
                                                                        </td>
                                                                        <td data-label="Observação da pauta">
                                                                            @php($alternativaSelecionadaId = (int) ($respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0))
                                                                            @php($requerObservacao = $this->alternativaRequerObservacao((int) $pauta->id, $alternativaSelecionadaId))

                                                                            @if ($requerObservacao)
                                                                                @php($observacaoAtual = (string) ($respostas[$pauta->id][$aluno->id]['observacao'] ?? ''))
                                                                                <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                                                    <textarea
                                                                                        x-ref="field"
                                                                                        x-on:input="count = $event.target.value.length"
                                                                                        maxlength="1500"
                                                                                        placeholder="{{ $this->placeholderObservacaoAlternativa((int) $pauta->id, $alternativaSelecionadaId) }}"
                                                                                        class="av-table-input av-textarea-input"
                                                                                        data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}"
                                                                                        wire:model.live.debounce.700ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                                                        @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)></textarea>

                                                                                    <div class="av-field-meta">
                                                                                        <small class="av-field-hint av-field-hint--danger">Obrigatória para esta alternativa.</small>
                                                                                        <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                                                    </div>
                                                                                </div>
                                                                            @else
                                                                                <small class="av-field-hint">Somente alternativas com observação habilitam este campo.</small>
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
                                            @endif
                                        </section>
                                    @empty
                                        <section class="av-note av-note--warning">
                                            Esta turma não possui pautas disponíveis neste recorte.
                                        </section>
                                    @endforelse
                                </div>
                        </section>
                    @endforeach
                </div>
                @endif
            @else
                @if ($this->modoAcompanhamento())
                    @php($turmaAtual = $turmasDaSerie->first())
                    @php($turmaIdAtual = (int) ($turmaAtual?->id ?? 0))
                    @php($alunosDaTurma = $turmaIdAtual > 0 ? $this->alunosDaTurma($turmaIdAtual) : collect())
                    <div class="av-stack">
                        @forelse ($turmaIdAtual > 0 ? $this->gruposPorComponenteDaTurma($turmaIdAtual) : collect() as $grupo)
                            <section wire:key="workspace-acompanhamento-componente-alunos-{{ $turmaIdAtual }}-{{ $grupo['componente_id'] }}" class="gi-panel av-turma-section is-open">
                                <div class="av-pauta-toggle">
                                    <div class="av-pauta-toggle-main">
                                        <h3 class="av-pauta-title">{{ $grupo['titulo'] }}</h3>
                                        <p class="av-pauta-meta">{{ $turmaAtual?->escola?->nome }} - {{ $turmaAtual?->serie?->nome }} - {{ $this->rotuloTurma($turmaAtual) }}</p>
                                    </div>
                                </div>

                                <div class="av-turma-content">
                                    @forelse ($alunosDaTurma as $aluno)
                                        @php($progressoAluno = $this->progressoPorAluno[$aluno->id] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'concluida' => false])
                                        @php($alunoExpandido = $this->alunoEstaExpandido($turmaIdAtual, (int) $aluno->id))
                                        @php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))

                                        <section wire:key="workspace-acompanhamento-componente-{{ $grupo['componente_id'] }}-aluno-{{ $aluno->id }}" class="av-pauta-section av-pauta-section--nested {{ $alunoExpandido ? 'is-open' : '' }}">
                                            <button type="button" class="av-pauta-toggle" wire:click="alternarAluno({{ $turmaIdAtual }}, {{ $aluno->id }})">
                                                <div class="av-pauta-toggle-main">
                                                    <h3 class="av-pauta-title">{{ $aluno->nome }}</h3>
                                                    <p class="av-pauta-meta">CGM: {{ $aluno->cgm }}</p>
                                                    @if ($alunoBloqueadoTransferencia)
                                                        <p class="av-pauta-meta">Aluno pendente de transferência. Avaliação bloqueada até o parecer da escola de origem.</p>
                                                    @endif
                                                </div>

                                                <div class="av-pauta-toggle-side">
                                                    <div class="av-pauta-progress-head" data-av-progress-type="aluno" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-aluno="{{ $aluno->id }}" data-av-progress-filled="{{ $progressoAluno['preenchidas'] }}" data-av-progress-total="{{ $progressoAluno['total'] }}">
                                                        <span>{{ $progressoAluno['preenchidas'] }}/{{ $progressoAluno['total'] }}</span>
                                                        <span>{{ $progressoAluno['percentual'] }}%</span>
                                                    </div>
                                                    <div class="av-progress-track av-progress-track--compact">
                                                        <div class="av-progress-bar" style="width: {{ $progressoAluno['percentual'] }}%"></div>
                                                    </div>
                                                    <div class="av-pauta-toggle-meta">
                                                        <span class="av-pauta-check {{ $progressoAluno['concluida'] ? '' : 'av-pauta-check--pending' }}">
                                                            {{ $progressoAluno['concluida'] ? 'Concluída' : 'Em andamento' }}
                                                        </span>
                                                        <span class="av-pauta-arrow {{ $alunoExpandido ? 'is-open' : '' }}">v</span>
                                                    </div>
                                                </div>
                                            </button>

                                            @if ($alunoExpandido)
                                                <div class="av-pauta-content">
                                                    <section class="av-aluno-componente">
                                                        <h4>{{ $grupo['titulo'] }}</h4>

                                                        <div class="gi-table-wrap">
                                                            <table class="gi-table">
                                                                <thead>
                                                                    <tr>
                                                                        <th>Pauta</th>
                                                                        <th>Alternativa</th>
                                                                        <th>Observação da pauta</th>
                                                                    </tr>
                                                                </thead>
                                                                <tbody>
                                                                    @foreach ($grupo['pautas'] as $pauta)
                                                                        @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))
                                                                        @php($respostaBloqueada = $this->respostaEstaBloqueada((int) $pauta->id, (int) $aluno->id))
                                                                        @php($referenciaOrigem = $respostas[$pauta->id][$aluno->id]['origem_referencia'] ?? null)
                                                                        <tr wire:key="workspace-acompanhamento-componente-{{ $grupo['componente_id'] }}-aluno-{{ $aluno->id }}-pauta-{{ $pauta->id }}">
                                                                            <td data-label="Pauta">
                                                                                <strong>{{ $pauta->texto }}</strong>
                                                                                @if ($respostaBloqueada)
                                                                                    <small>Resposta bloqueada por histórico.</small>
                                                                                @endif
                                                                                @if ($referenciaOrigem)
                                                                                    <small>Origem: {{ $referenciaOrigem['alternativa'] !== '' ? $referenciaOrigem['alternativa'] : 'Não avaliado' }}{{ $referenciaOrigem['observacao'] !== '' ? ' | '.$referenciaOrigem['observacao'] : '' }}</small>
                                                                                @endif
                                                                            </td>
                                                                            <td data-label="Alternativa">
                                                                                <div class="av-input-wrap">
                                                                                    <select data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}" class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id" @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)>
                                                                                        <option value="">Selecione</option>
                                                                                        @foreach ($alternativasPauta as $alternativa)
                                                                                            <option value="{{ $alternativa['id'] }}" data-requires-observation="{{ ($alternativa['tem_observacao'] ?? false) ? 1 : 0 }}">
                                                                                                {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                                                                                            </option>
                                                                                        @endforeach
                                                                                    </select>

                                                                                </div>
                                                                            </td>
                                                                            <td data-label="Observação da pauta">
                                                                                @php($alternativaSelecionadaId = (int) ($respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0))
                                                                                @php($requerObservacao = $this->alternativaRequerObservacao((int) $pauta->id, $alternativaSelecionadaId))

                                                                                @if ($requerObservacao)
                                                                                    @php($observacaoAtual = (string) ($respostas[$pauta->id][$aluno->id]['observacao'] ?? ''))
                                                                                    <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                                                        <textarea
                                                                                            x-ref="field"
                                                                                            x-on:input="count = $event.target.value.length"
                                                                                            maxlength="1500"
                                                                                            placeholder="{{ $this->placeholderObservacaoAlternativa((int) $pauta->id, $alternativaSelecionadaId) }}"
                                                                                            class="av-table-input av-textarea-input"
                                                                                            data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}"
                                                                                            wire:model.live.debounce.700ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                                                            @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)></textarea>

                                                                                        <div class="av-field-meta">
                                                                                            <small class="av-field-hint av-field-hint--danger">Obrigatória para esta alternativa.</small>
                                                                                            <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                                                        </div>
                                                                                    </div>
                                                                                @else
                                                                                    <small class="av-field-hint">Somente alternativas com observação habilitam este campo.</small>
                                                                                @endif
                                                                            </td>
                                                                        </tr>
                                                                    @endforeach
                                                                </tbody>
                                                            </table>
                                                        </div>

                                                        @php($componenteId = (int) $grupo['componente_id'])
                                                        @php($informacoesAtuais = (string) ($informacoesComplementares[$componenteId][$aluno->id] ?? ''))
                                                        @php($informacaoBloqueada = $this->informacaoComplementarEstaBloqueada($componenteId, (int) $aluno->id))
                                                        <div class="av-complementary-section">
                                                            <h4>Informações complementares do componente</h4>
                                                            @if ($alunoBloqueadoTransferencia)
                                                                <small class="av-field-hint">Informações bloqueadas enquanto a transferência estiver pendente.</small>
                                                            @endif
                                                            @if ($informacaoBloqueada)
                                                                <small class="av-field-hint">Informações bloqueadas por histórico.</small>
                                                            @endif
                                                            <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($informacoesAtuais)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                                <textarea
                                                                    x-ref="field"
                                                                    x-on:input="count = $event.target.value.length"
                                                                    maxlength="1500"
                                                                    placeholder="Informações complementares (opcional)"
                                                                    class="av-table-input av-textarea-input"
                                                                    data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $informacaoVersoes[$componenteId][$aluno->id] ?? 0 }}"
                                                                    wire:model.live.debounce.900ms="informacoesComplementares.{{ $componenteId }}.{{ $aluno->id }}"
                                                                    @disabled(! $this->podeResponder() || $informacaoBloqueada || $alunoBloqueadoTransferencia)></textarea>

                                                                <div class="av-field-meta">
                                                                    <span></span>
                                                                    <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </section>
                                                </div>
                                            @endif
                                        </section>
                                    @empty
                                        <section class="av-note av-note--warning">
                                            Esta turma não possui alunos cadastrados.
                                        </section>
                                    @endforelse
                                </div>
                            </section>
                        @empty
                            <section class="av-note av-note--warning">
                                Nenhuma pauta desta avaliação está disponível para o recorte atual.
                            </section>
                        @endforelse
                    </div>
                @else
                <div class="av-stack {{ $turma ? '' : 'av-turma-grid' }}">
                    @foreach ($turmasDaSerie as $turmaItem)
                        @php($turmaIdAtual = (int) $turmaItem->id)
                        @php($progressoTurma = $this->progressoPorTurma[$turmaIdAtual] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'concluida' => false])
                        @php($alunosDaTurma = $this->alunosDaTurma($turmaIdAtual))
                        <section wire:key="workspace-turma-alunos-{{ $turmaIdAtual }}" x-data="{ aberto: false }" :class="{ 'is-open': aberto }" class="gi-panel av-turma-section">
                            <button type="button" class="av-pauta-toggle" x-on:click="aberto = ! aberto">
                                <div class="av-pauta-toggle-main">
                                    <h3 class="av-pauta-title">{{ $this->rotuloTurma($turmaItem) }}</h3>
                                    <p class="av-pauta-meta">{{ $turmaItem->escola?->nome }} - {{ $turmaItem->serie?->nome }}</p>
                                </div>

                                <div class="av-pauta-toggle-side">
                                    <div class="av-pauta-progress-head" data-av-progress-type="turma" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-filled="{{ $progressoTurma['preenchidas'] }}" data-av-progress-total="{{ $progressoTurma['total'] }}">
                                        <span>{{ $progressoTurma['preenchidas'] }}/{{ $progressoTurma['total'] }}</span>
                                        <span>{{ $progressoTurma['percentual'] }}%</span>
                                    </div>
                                    <div class="av-progress-track av-progress-track--compact">
                                        <div class="av-progress-bar" style="width: {{ $progressoTurma['percentual'] }}%"></div>
                                    </div>
                                    <div class="av-pauta-toggle-meta">
                                        <span class="av-pauta-check {{ $progressoTurma['concluida'] ? '' : 'av-pauta-check--pending' }}">
                                            {{ $progressoTurma['concluida'] ? 'Concluída' : 'Em andamento' }}
                                        </span>
                                        <span class="av-pauta-arrow" :class="{ 'is-open': aberto }">v</span>
                                    </div>
                                </div>
                            </button>

                                <div class="av-turma-content" x-show="aberto" x-cloak>
                                    <div class="av-student-picker">
                                        <div>
                                            <span class="av-focus-kicker">Preenchimento por aluno</span>
                                            <strong>Selecione um aluno para avaliar</strong>
                                            <small>Abra somente um registro por vez para preencher com mais segurança.</small>
                                        </div>
                                        <label class="gi-field">
                                            <span>Aluno</span>
                                            <select x-on:change="if ($event.target.value) Livewire.navigate($event.target.value)">
                                                <option value="">Selecione um aluno</option>
                                                @foreach ($alunosDaTurma as $alunoOpcao)
                                                    @php($alunoOpcaoUrl = \App\Filament\Admin\Pages\AvaliacoesProfessor::getUrl(['avaliacao' => $avaliacao, 'escola' => $escola, 'serie' => $serie, 'turma' => $turmaIdAtual, 'visualizacao' => 'alunos', 'aluno' => $alunoOpcao->id]))
                                                    <option value="{{ $alunoOpcaoUrl }}" @selected((int) ($this->alunoEmFoco ?? 0) === (int) $alunoOpcao->id)>
                                                        {{ $alunoOpcao->nome }} · CGM {{ $alunoOpcao->cgm }}
                                                    </option>
                                                @endforeach
                                            </select>
                                        </label>
                                    </div>
                                    @forelse ($alunosDaTurma as $aluno)
                                        @php($progressoAluno = $this->progressoPorAluno[$aluno->id] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0, 'concluida' => false])
                                        @php($alunoBloqueadoTransferencia = $this->alunoEstaBloqueadoParaAvaliacao($aluno))
                                        @php($alunoSelecionado = (int) ($this->alunoEmFoco ?? 0) === (int) $aluno->id)
                                        @php($alunoUrl = \App\Filament\Admin\Pages\AvaliacoesProfessor::getUrl(['avaliacao' => $avaliacao, 'escola' => $escola, 'serie' => $serie, 'turma' => $turmaIdAtual, 'visualizacao' => 'alunos', 'aluno' => $aluno->id]))

                                        <section wire:key="workspace-turma-{{ $turmaIdAtual }}-aluno-{{ $aluno->id }}" class="av-pauta-section av-pauta-section--nested {{ $alunoSelecionado ? 'is-open' : '' }}">
                                            <a class="av-pauta-toggle" href="{{ $alunoUrl }}" wire:navigate>
                                                <div class="av-pauta-toggle-main">
                                                    <h3 class="av-pauta-title">{{ $aluno->nome }}</h3>
                                                    <p class="av-pauta-meta">CGM: {{ $aluno->cgm }}</p>
                                                    @if ($alunoBloqueadoTransferencia)
                                                        <p class="av-pauta-meta">Aluno pendente de transferência. Avaliação bloqueada até o parecer da escola de origem.</p>
                                                    @endif
                                                </div>

                                                <div class="av-pauta-toggle-side">
                                                    <div class="av-pauta-progress-head" data-av-progress-type="aluno" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-aluno="{{ $aluno->id }}" data-av-progress-filled="{{ $progressoAluno['preenchidas'] }}" data-av-progress-total="{{ $progressoAluno['total'] }}">
                                                        <span>{{ $progressoAluno['preenchidas'] }}/{{ $progressoAluno['total'] }}</span>
                                                        <span>{{ $progressoAluno['percentual'] }}%</span>
                                                    </div>
                                                    <div class="av-progress-track av-progress-track--compact">
                                                        <div class="av-progress-bar" style="width: {{ $progressoAluno['percentual'] }}%"></div>
                                                    </div>
                                                    <div class="av-pauta-toggle-meta">
                                                        <span class="av-pauta-check {{ $progressoAluno['concluida'] ? '' : 'av-pauta-check--pending' }}">
                                                            {{ $progressoAluno['concluida'] ? 'Concluída' : 'Em andamento' }}
                                                        </span>
                                                        <span class="av-pauta-arrow {{ $alunoSelecionado ? 'is-open' : '' }}">{{ $alunoSelecionado ? '−' : '›' }}</span>
                                                    </div>
                                                </div>
                                            </a>

                                            @if ($alunoSelecionado)
                                                <div class="av-pauta-content">
                                                    @foreach ($this->pautasAgrupadasPorComponenteDaTurma($turmaIdAtual) as $componenteNome => $pautasDoComponente)
                                                        <section class="av-aluno-componente">
                                                            @php($componenteId = (int) ($pautasDoComponente->first()?->componente_curricular_id ?? 0))
                                                            <h4>{{ $componenteNome }}</h4>

                                                            <div class="gi-table-wrap">
                                                                <table class="gi-table">
                                                                    <thead>
                                                                        <tr>
                                                                            <th>Pauta</th>
                                                                            <th>Alternativa</th>
                                                                            <th>Observação da pauta</th>
                                                                        </tr>
                                                                    </thead>
                                                                    <tbody>
                                                                        @foreach ($pautasDoComponente as $pauta)
                                                                            @php($alternativasPauta = $this->alternativasDaPauta((int) $pauta->id))
                                                                            @php($respostaBloqueada = $this->respostaEstaBloqueada((int) $pauta->id, (int) $aluno->id))
                                                                            @php($referenciaOrigem = $respostas[$pauta->id][$aluno->id]['origem_referencia'] ?? null)
                                                                            <tr wire:key="workspace-turma-{{ $turmaIdAtual }}-aluno-{{ $aluno->id }}-pauta-{{ $pauta->id }}">
                                                                                <td data-label="Pauta">
                                                                                    <strong>{{ $pauta->texto }}</strong>
                                                                                    @if ($respostaBloqueada)
                                                                                        <small>Resposta bloqueada por histórico.</small>
                                                                                    @endif
                                                                                    @if ($referenciaOrigem)
                                                                                        <small>Origem: {{ $referenciaOrigem['alternativa'] !== '' ? $referenciaOrigem['alternativa'] : 'Não avaliado' }}{{ $referenciaOrigem['observacao'] !== '' ? ' | '.$referenciaOrigem['observacao'] : '' }}</small>
                                                                                    @endif
                                                                                </td>
                                                                                <td data-label="Alternativa">
                                                                                    <div class="av-input-wrap">
                                                                                        <select data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}" class="av-table-input" wire:model.live="respostas.{{ $pauta->id }}.{{ $aluno->id }}.alternativa_id" @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)>
                                                                                            <option value="">Selecione</option>
                                                                                            @foreach ($alternativasPauta as $alternativa)
                                                                                                <option value="{{ $alternativa['id'] }}" data-requires-observation="{{ ($alternativa['tem_observacao'] ?? false) ? 1 : 0 }}">
                                                                                                    {{ $alternativa['nome'] }}{{ ($alternativa['tem_observacao'] ?? false) ? ' (exige observação)' : '' }}
                                                                                                </option>
                                                                                            @endforeach
                                                                                        </select>

                                                                                    </div>
                                                                                </td>
                                                                                <td data-label="Observação da pauta">
                                                                                    @php($alternativaSelecionadaId = (int) ($respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? 0))
                                                                                    @php($requerObservacao = $this->alternativaRequerObservacao((int) $pauta->id, $alternativaSelecionadaId))

                                                                                    @if ($requerObservacao)
                                                                                        @php($observacaoAtual = (string) ($respostas[$pauta->id][$aluno->id]['observacao'] ?? ''))
                                                                                        <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($observacaoAtual)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                                                            <textarea
                                                                                                x-ref="field"
                                                                                                x-on:input="count = $event.target.value.length"
                                                                                                maxlength="1500"
                                                                                                placeholder="{{ $this->placeholderObservacaoAlternativa((int) $pauta->id, $alternativaSelecionadaId) }}"
                                                                                                class="av-table-input av-textarea-input"
                                                                                                data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $respostaVersoes[$pauta->id][$aluno->id] ?? 0 }}" data-av-autosave-expected-alternativa="{{ $respostas[$pauta->id][$aluno->id]['alternativa_id'] ?? '' }}" data-av-autosave-expected-observacao="{{ $respostas[$pauta->id][$aluno->id]['observacao'] ?? '' }}"
                                                                                                wire:model.live.debounce.700ms="respostas.{{ $pauta->id }}.{{ $aluno->id }}.observacao"
                                                                                                @disabled(! $this->podeResponder() || $respostaBloqueada || $alunoBloqueadoTransferencia)></textarea>

                                                                                            <div class="av-field-meta">
                                                                                                <small class="av-field-hint av-field-hint--danger">Obrigatória para esta alternativa.</small>
                                                                                                <small class="av-char-count" x-text="`${count}/1500`"></small>
                                                                                            </div>
                                                                                        </div>
                                                                                    @else
                                                                                        <small class="av-field-hint">Somente alternativas com observação habilitam este campo.</small>
                                                                                    @endif
                                                                                </td>
                                                                            </tr>
                                                                        @endforeach
                                                                    </tbody>
                                                                </table>
                                                            </div>

                                                            @php($informacoesAtuais = (string) ($informacoesComplementares[$componenteId][$aluno->id] ?? ''))
                                                            @php($informacaoBloqueada = $this->informacaoComplementarEstaBloqueada($componenteId, (int) $aluno->id))
                                                            <div class="av-complementary-section">
                                                                <h4>Informações complementares do componente</h4>
                                                                @if ($alunoBloqueadoTransferencia)
                                                                    <small class="av-field-hint">Informações bloqueadas enquanto a transferência estiver pendente.</small>
                                                                @endif
                                                                @if ($informacaoBloqueada)
                                                                    <small class="av-field-hint">Informações bloqueadas por histórico.</small>
                                                                @endif
                                                                <div class="av-input-wrap" x-data="{ count: @js(mb_strlen($informacoesAtuais)) }" x-init="$nextTick(() => count = $refs.field.value.length)">
                                                                    <textarea
                                                                        x-ref="field"
                                                                        x-on:input="count = $event.target.value.length"
                                                                        maxlength="1500"
                                                                        placeholder="Informações complementares (opcional)"
                                                                        class="av-table-input av-textarea-input"
                                                                        data-av-editavel data-av-autosave-turma-id="{{ $turmaIdAtual }}" data-av-autosave-version="{{ $informacaoVersoes[$componenteId][$aluno->id] ?? 0 }}"
                                                                        wire:model.live.debounce.900ms="informacoesComplementares.{{ $componenteId }}.{{ $aluno->id }}"
                                                                        @disabled(! $this->podeResponder() || $informacaoBloqueada || $alunoBloqueadoTransferencia)></textarea>

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
                                            Esta turma não possui alunos cadastrados.
                                        </section>
                                    @endforelse
                                </div>
                        </section>
                    @endforeach
                </div>
                @endif
            @endif

        @endif
        @endif
    </div>

    @if ($this->podeResponder() && ! $this->interfaceProfessorEmLista())
        <button
            x-cloak
            x-show="alteracoesPendentes || autosaveConfirmado"
            x-transition.opacity
            type="button"
            class="av-floating-save"
            x-bind:class="{ 'is-confirmed': autosaveConfirmado, 'is-error': autosaveFalhou }"
            x-bind:disabled="autosaveEmAndamento || autosaveConfirmado"
            wire:click="salvarAlteracoes"
            wire:loading.attr="disabled"
            wire:target="salvarAlteracoes">
            <span x-show="autosaveEmAndamento" wire:loading.remove wire:target="salvarAlteracoes">Salvando...</span>
            <span x-show="autosaveConfirmado" wire:loading.remove wire:target="salvarAlteracoes">Salvo automaticamente</span>
            <span x-show="! autosaveEmAndamento && ! autosaveConfirmado && ! autosaveFalhou" wire:loading.remove wire:target="salvarAlteracoes">Salvar</span>
            <span x-show="autosaveFalhou" wire:loading.remove wire:target="salvarAlteracoes">Tentar salvar</span>
            <span wire:loading wire:target="salvarAlteracoes">Salvando...</span>
        </button>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')

    <style>
        .av-static-field {
            min-height: 2.75rem;
            display: flex;
            align-items: center;
            padding: 0.65rem 0.85rem;
            border: 1px solid rgba(148, 163, 184, 0.35);
            border-radius: 0.75rem;
            background: rgba(248, 250, 252, 0.8);
            color: #0f172a;
            font-weight: 600;
        }
    </style>

    <script>
        (() => {
            if (window.__gestaoEduAvaliacaoAutosaveInitialized) {
                return;
            }

            window.__gestaoEduAvaliacaoAutosaveInitialized = true;

            const timers = new Map();
            const queues = new Map();
            const versions = new Map();

            const respostaEstaCompleta = (alternativaId, observacao, requerObservacao) =>
                Number(alternativaId || 0) > 0
                && (!requerObservacao || String(observacao || '').trim() !== '');

            const atualizarProgressoLocal = (root, detail) => {
                if (!root || detail.tipo !== 'resposta' || detail.delta === 0) {
                    return;
                }

                root.querySelectorAll('[data-av-progress-type]').forEach((scope) => {
                    const type = scope.dataset.avProgressType;
                    const matchesTurma = String(scope.dataset.avProgressTurma || '') === String(detail.turmaId);
                    const matches = type === 'global'
                        || (matchesTurma && type === 'turma')
                        || (matchesTurma && type === 'pauta'
                            && String(scope.dataset.avProgressPauta || '') === String(detail.pautaId))
                        || (matchesTurma && type === 'aluno'
                            && String(scope.dataset.avProgressAluno || '') === String(detail.alunoId))
                        || (matchesTurma && type === 'componente'
                            && String(scope.dataset.avProgressPautas || '')
                                .split(',')
                                .includes(String(detail.pautaId)));

                    if (!matches) {
                        return;
                    }

                    const total = Math.max(0, Number(scope.dataset.avProgressTotal || 0));
                    const preenchidas = Math.max(0, Math.min(
                        total,
                        Number(scope.dataset.avProgressFilled || 0) + Number(detail.delta),
                    ));
                    const percentual = total > 0
                        ? Math.min(100, Math.round((preenchidas / total) * 100))
                        : 0;
                    const cabecalho = type === 'global'
                        ? scope.querySelector('.av-progress-head')
                        : scope;
                    const spans = Array.from(cabecalho?.children || [])
                        .filter((child) => child.tagName === 'SPAN');
                    const contador = type === 'global' ? spans[spans.length - 1] : spans[0];
                    const percentualNode = type === 'global' ? null : spans[1];
                    const container = type === 'global' ? scope : scope.parentElement;
                    const barra = container?.querySelector('.av-progress-bar');
                    const status = container?.querySelector('.av-pauta-check');
                    const concluida = total > 0 && preenchidas === total;

                    scope.dataset.avProgressFilled = String(preenchidas);
                    if (contador) contador.textContent = `${preenchidas}/${total}`;
                    if (percentualNode) percentualNode.textContent = `${percentual}%`;
                    if (barra) barra.style.width = `${percentual}%`;
                    if (status) {
                        status.textContent = concluida ? 'Concluída' : 'Em andamento';
                        status.classList.toggle('av-pauta-check--pending', !concluida);
                    }
                });
            };

            const wirePath = (element) => {
                const attribute = element.getAttributeNames().find((name) => name.startsWith('wire:model'));

                return attribute ? element.getAttribute(attribute) : null;
            };

            const componentFor = (element) => {
                let node = element;

                while (node && !node.hasAttribute('wire:id')) {
                    node = node.parentElement;
                }

                const id = node?.getAttribute('wire:id');

                const wire = id && window.Livewire ? window.Livewire.find(id) : null;

                // No Livewire 4, find() devolve o proxy $wire, e não a
                // instância interna do componente.
                return wire ? { id, $wire: wire } : null;
            };

            const rowFor = (element) => element.closest('[data-av-response-row]')
                || element.closest('tr')
                || element.closest('[data-av-autosave-root]');

            const fieldInRow = (row, suffix) => Array.from(row?.querySelectorAll('[data-av-editavel]') || [])
                .find((field) => String(wirePath(field) || '').endsWith(suffix));

            const directAutosaveAllowed = (element, path) => {
                if (element.tagName !== 'SELECT' || !path.startsWith('respostas.')) {
                    return true;
                }

                const row = rowFor(element);
                const requiresObservation = element.selectedOptions[0]?.dataset.requiresObservation === '1';
                const hasObservationField = Boolean(fieldInRow(row, '.observacao'));

                if (row?.querySelector('[data-av-observation-field]')) {
                    return true;
                }

                // A normal alternative can be saved without rebuilding the matrix.
                // Transitions that show/hide the required textarea still use the
                // existing Livewire render once to preserve the current UX.
                return !requiresObservation && !hasObservationField;
            };

            const stateFor = (element, path) => {
                const row = rowFor(element);
                const response = path.match(/^respostas\.(\d+)\.(\d+)\.(alternativa_id|observacao)$/);

                if (response) {
                    const alternativeField = fieldInRow(row, '.alternativa_id');
                    const observationField = fieldInRow(row, '.observacao');
                    const alternativaId = alternativeField?.value ? Number(alternativeField.value) : null;
                    const requerObservacao = alternativeField?.selectedOptions[0]?.dataset.requiresObservation === '1';
                    const observacao = requerObservacao ? (observationField?.value?.trim() || null) : null;
                    const alternativaPersistida = alternativeField?.dataset.avAutosaveExpectedAlternativa || null;
                    const observacaoPersistida = alternativeField?.dataset.avAutosaveExpectedObservacao || null;

                    return {
                        tipo: 'resposta',
                        pauta_id: Number(response[1]),
                        aluno_id: Number(response[2]),
                        campo: response[3],
                        valor: element.tagName === 'SELECT'
                            ? (element.value ? Number(element.value) : null)
                            : element.value,
                        alternativa_id: alternativaId,
                        observacao,
                        requer_observacao: requerObservacao,
                        completaAntes: respostaEstaCompleta(
                            alternativaPersistida,
                            observacaoPersistida,
                            requerObservacao,
                        ),
                        completaDepois: respostaEstaCompleta(alternativaId, observacao, requerObservacao),
                        expected_values: {
                            alternativa_id: alternativaPersistida,
                            observacao: observacaoPersistida,
                        },
                    };
                }

                const info = path.match(/^informacoesComplementares\.(\d+)\.(\d+)$/);
                if (info) {
                    return {
                        tipo: 'informacao',
                        componente_id: Number(info[1]),
                        aluno_id: Number(info[2]),
                        campo: 'observacao',
                        valor: element.value,
                    };
                }

                return null;
            };

            const syncLocalState = (component, path, state, version) => {
                if (!component?.$wire?.set) {
                    return;
                }

                component.$wire.set(path, state.valor === '' ? null : state.valor, false);

                if (state.tipo === 'resposta') {
                    const prefix = `respostas.${state.pauta_id}.${state.aluno_id}`;
                    component.$wire.set(`respostaVersoes.${state.pauta_id}.${state.aluno_id}`, version, false);
                    component.$wire.set(`${prefix}.alternativa_id`, state.alternativa_id, false);
                    component.$wire.set(`${prefix}.observacao`, state.observacao, false);

                    const persisted = version > 0
                        ? { alternativa_id: state.alternativa_id, observacao: state.observacao }
                        : { alternativa_id: null, observacao: null };
                    component.$wire.set(`respostasPersistidas.${state.pauta_id}.${state.aluno_id}`, persisted, false);
                } else {
                    component.$wire.set(`informacaoVersoes.${state.componente_id}.${state.aluno_id}`, version, false);
                    component.$wire.set(`informacoesComplementaresPersistidas.${state.componente_id}.${state.aluno_id}`, state.valor, false);
                }
            };

            const syncFieldMetadata = (element, state, version) => {
                element.dataset.avAutosaveVersion = String(version);

                if (state.tipo !== 'resposta') {
                    return;
                }

                const row = rowFor(element);
                Array.from(row?.querySelectorAll('[data-av-editavel]') || [])
                    .filter((field) => String(wirePath(field) || '').startsWith(`respostas.${state.pauta_id}.${state.aluno_id}.`))
                    .forEach((field) => {
                        field.dataset.avAutosaveVersion = String(version);
                        field.dataset.avAutosaveExpectedAlternativa = state.alternativa_id || '';
                        field.dataset.avAutosaveExpectedObservacao = state.observacao || '';
                    });
            };

            const save = async (element, path) => {
                const root = element.closest('[data-av-autosave-root]');
                const component = componentFor(element);
                const state = stateFor(element, path);

                if (!root || !component || !state) {
                    return;
                }

                const key = `${component.id}:${path}`;
                const previous = queues.get(key) || Promise.resolve();
                const next = previous.catch(() => {}).then(async () => {
                    const expectedVersion = Number(element.dataset.avAutosaveVersion || versions.get(key) || 0);
                    const payload = {
                        avaliacao_id: component.$wire.get('avaliacao'),
                        turma_id: Number(element.dataset.avAutosaveTurmaId || 0),
                        aluno_id: state.aluno_id,
                        tipo: state.tipo,
                        pauta_id: state.pauta_id,
                        componente_id: state.componente_id,
                        campo: state.campo,
                        valor: state.valor,
                        alternativa_id: state.alternativa_id,
                        observacao: state.observacao,
                        expected_version: expectedVersion,
                        expected_values: state.expected_values,
                    };

                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';
                    const response = await fetch(root.dataset.avAutosaveUrl, {
                        method: 'POST',
                        credentials: 'same-origin',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': csrf,
                        },
                        body: JSON.stringify(payload),
                    });
                    const body = await response.json().catch(() => ({}));

                    if (!response.ok || body.saved !== true) {
                        const error = new Error(body.message || 'Não foi possível salvar a avaliação.');
                        error.status = response.status;
                        throw error;
                    }

                    const version = Number(body.version || 0);
                    versions.set(key, version);
                    syncFieldMetadata(element, state, version);
                    syncLocalState(component, path, state, version);
                    element.removeAttribute('data-av-autosave-error');
                    element.removeAttribute('title');
                    atualizarProgressoLocal(root, {
                        tipo: state.tipo,
                        turmaId: Number(element.dataset.avAutosaveTurmaId || 0),
                        pautaId: state.pauta_id,
                        alunoId: state.aluno_id,
                        delta: state.tipo === 'resposta'
                            ? Number(state.completaDepois) - Number(state.completaAntes)
                            : 0,
                    });
                    window.dispatchEvent(new CustomEvent('avaliacao-autosave-confirmado'));
                }).catch((error) => {
                    element.dataset.avAutosaveError = '1';
                    element.title = error.message;
                    window.dispatchEvent(new CustomEvent('avaliacao-autosave-falhou'));
                });

                queues.set(key, next);
                await next;
            };

            const schedule = (element, path, delay) => {
                const key = `${componentFor(element)?.id || 'unknown'}:${path}`;
                clearTimeout(timers.get(key));
                timers.set(key, setTimeout(() => save(element, path), delay));
            };

            const handle = (event) => {
                const element = event.target;
                if (!(element instanceof HTMLElement) || !element.matches('[data-av-editavel]')) {
                    return;
                }

                const path = wirePath(element);

                if (element.tagName === 'SELECT' && path?.startsWith('respostas.')) {
                    const row = rowFor(element);
                    const observationField = row?.querySelector('[data-av-observation-field]');

                    if (observationField) {
                        const selected = element.selectedOptions[0];
                        const requiresObservation = selected?.dataset.requiresObservation === '1';
                        const locked = observationField.dataset.avObservationLocked === '1';
                        observationField.disabled = !requiresObservation || locked;
                        observationField.placeholder = requiresObservation
                            ? (selected?.dataset.observationPlaceholder || 'Observação obrigatória')
                            : 'Selecione uma alternativa que exija observação.';

                        if (!requiresObservation) {
                            observationField.value = '';
                            const count = observationField.closest('.av-input-wrap')?.querySelector('.av-char-count');
                            if (count) count.textContent = '0/1500';
                        }
                    }
                }

                if (!path || !directAutosaveAllowed(element, path)) {
                    return;
                }

                const component = componentFor(element);
                if (!component) {
                    return;
                }

                element.closest('[data-av-autosave-root]')
                    ?.dispatchEvent(new CustomEvent('avaliacao-alterada'));
                event.stopImmediatePropagation();
                if (element.tagName === 'TEXTAREA') {
                    const count = element.closest('.av-input-wrap')?.querySelector('.av-char-count');
                    if (count) {
                        count.textContent = `${element.value.length}/1500`;
                    }
                }

                if (component.$wire?.set) {
                    component.$wire.set(path, element.tagName === 'SELECT'
                        ? (element.value ? Number(element.value) : null)
                        : element.value, false);
                }

                const state = stateFor(element, path);
                if (state?.tipo === 'resposta'
                    && state.campo === 'alternativa_id'
                    && state.requer_observacao
                    && !state.observacao) {
                    return;
                }

                schedule(element, path, element.tagName === 'TEXTAREA' ? 700 : 0);
            };

            document.addEventListener('change', handle, true);
            document.addEventListener('input', handle, true);
        })();
    </script>
</div>
