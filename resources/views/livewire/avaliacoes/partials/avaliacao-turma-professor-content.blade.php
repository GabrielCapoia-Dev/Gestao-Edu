@php($avaliacaoAtual = $this->avaliacaoAtual)

@if (! $avaliacaoAtual)
    <section class="av-professor-list-heading">
        <div>
            <span class="av-professor-eyebrow">Avaliações disponíveis</span>
            <h2>Escolha uma avaliação para começar</h2>
            <p>As avaliações abertas e vinculadas às suas turmas aparecem abaixo.</p>
        </div>
        <span class="av-workspace-count">{{ $this->avaliacoesDisponiveis->count() }} {{ $this->avaliacoesDisponiveis->count() === 1 ? 'avaliação' : 'avaliações' }}</span>
    </section>

    @if ($this->avaliacoesDisponiveis->isEmpty())
        <section class="av-professor-empty">
            <strong>Nenhuma avaliação disponível</strong>
            <p>Não há avaliações abertas para seus componentes neste momento.</p>
        </section>
    @else
        <div class="av-professor-evaluation-list">
            @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                @php($avaliacaoUrl = \App\Filament\Admin\Pages\AvaliacoesProfessor::getUrl(['avaliacao' => $avaliacaoItem->id]))
                @php($percentualAvaliacao = $this->progressoAvaliacoesProfessor[$avaliacaoItem->id] ?? 0)
                <article class="av-professor-evaluation-card" wire:key="avaliacao-professor-{{ $avaliacaoItem->id }}">
                    <div class="av-professor-evaluation-card__body">
                        <span>{{ $avaliacaoItem->tipo?->nome ?? 'Avaliação' }}</span>
                        <h3>{{ $avaliacaoItem->nome }}</h3>
                        <p>
                            De {{ optional($avaliacaoItem->data_inicio_preenchimento ?? $avaliacaoItem->data_inicio)->format('d/m/Y') }}
                            a {{ optional($avaliacaoItem->data_fim_preenchimento ?? $avaliacaoItem->data_fim)->format('d/m/Y') }}
                        </p>
                    </div>
                    <div class="av-professor-evaluation-progress">
                        <div>
                            <span>Progresso</span>
                            <strong>{{ $percentualAvaliacao }}%</strong>
                        </div>
                        <div class="av-progress-track">
                            <div class="av-progress-bar" style="width: {{ $percentualAvaliacao }}%"></div>
                        </div>
                        <small>{{ max(100 - $percentualAvaliacao, 0) }}% pendente</small>
                    </div>
                    <a class="gi-action gi-action--primary" href="{{ $avaliacaoUrl }}" wire:navigate>
                        Avaliar
                        <span aria-hidden="true">→</span>
                    </a>
                </article>
            @endforeach
        </div>
    @endif
@else
    @php($turmasNavegacao = $this->turmasDaAvaliacaoProfessor)
    @php($exibirEscolas = $this->agrupaNavegacaoPorEscola() && ! $escolaNavegacaoId)
    @php($exibirSeries = ! $exibirEscolas && ! $serieNavegacaoId)
    @php($exibirTurmas = ! $exibirEscolas && ! $exibirSeries)
    <section class="av-professor-context">
        @if ($escolaNavegacaoId || $serieNavegacaoId)
            <button type="button" class="av-professor-back" wire:click="voltarNavegacao">
                <span aria-hidden="true">←</span> Voltar
            </button>
        @else
            <a class="av-professor-back" href="{{ \App\Filament\Admin\Pages\AvaliacoesProfessor::getUrl() }}" wire:navigate>
                <span aria-hidden="true">←</span> Voltar às avaliações
            </a>
        @endif
        <div class="av-professor-context__row">
            <div>
                <span class="av-professor-eyebrow">{{ $avaliacaoAtual->tipo?->nome ?? 'Avaliação' }}</span>
                <h2>{{ $avaliacaoAtual->nome }}</h2>
                <p>
                    {{ $exibirEscolas ? 'Selecione uma escola para visualizar as séries.' : ($exibirSeries ? 'Selecione uma série para visualizar as turmas.' : 'Abra uma turma para visualizar seus componentes.') }}
                </p>
            </div>
            <span class="av-workspace-count">{{ $turmasNavegacao->count() }} {{ $turmasNavegacao->count() === 1 ? 'turma' : 'turmas' }}</span>
        </div>
    </section>

    @if (! $this->podeResponder())
        <section class="av-note">Esta avaliação está disponível somente para leitura.</section>
    @endif

    @if ($exibirEscolas)
        <section x-data="{ busca: '' }">
            <div class="av-professor-filter-bar">
                <label class="av-professor-search">
                    <span class="sr-only">Buscar escola</span>
                    <input type="search" x-model.debounce.150ms="busca" placeholder="Buscar escola...">
                </label>
            </div>
            <div class="av-professor-navigation-grid">
                @foreach ($turmasNavegacao->groupBy('id_escola') as $escolaId => $turmasEscola)
                    @php($escolaNome = $turmasEscola->first()?->escola?->nome ?? 'Escola não informada')
                    <button type="button" data-search="{{ mb_strtolower($escolaNome) }}" x-show="$el.dataset.search.includes(busca.toLowerCase())" wire:click="selecionarEscolaNavegacao({{ $escolaId }})">
                        <span class="av-professor-class-icon">E</span>
                        <span><strong>{{ $escolaNome }}</strong><small>{{ $turmasEscola->count() }} turmas</small></span>
                        <span aria-hidden="true">→</span>
                    </button>
                @endforeach
            </div>
        </section>
    @elseif ($exibirSeries)
        @php($turmasDasSeries = $this->agrupaNavegacaoPorEscola() ? $turmasNavegacao->where('id_escola', (int) $escolaNavegacaoId) : $turmasNavegacao)
        <section x-data="{ busca: '' }">
            <div class="av-professor-filter-bar">
                <label class="av-professor-search">
                    <span class="sr-only">Buscar série</span>
                    <input type="search" x-model.debounce.150ms="busca" placeholder="Buscar série...">
                </label>
            </div>
            <div class="av-professor-navigation-grid">
                @foreach ($turmasDasSeries->groupBy('id_serie') as $serieId => $turmasSerie)
                    @php($serieNome = $turmasSerie->first()?->serie?->nome ?? 'Série não informada')
                    <button type="button" data-search="{{ mb_strtolower($serieNome) }}" x-show="$el.dataset.search.includes(busca.toLowerCase())" wire:click="selecionarSerieNavegacao({{ $serieId }})">
                        <span class="av-professor-class-icon">S</span>
                        <span><strong>{{ $serieNome }}</strong><small>{{ $turmasSerie->count() }} {{ $turmasSerie->count() === 1 ? 'turma' : 'turmas' }}</small></span>
                        <span aria-hidden="true">→</span>
                    </button>
                @endforeach
            </div>
        </section>
    @else
        @php($turmasFiltradas = $turmasNavegacao->where('id_serie', (int) $serieNavegacaoId)->when($this->agrupaNavegacaoPorEscola(), fn ($itens) => $itens->where('id_escola', (int) $escolaNavegacaoId)))
        <section x-data="{ busca: '', turno: '' }">
            <div class="av-professor-filter-bar">
                <label class="av-professor-search">
                    <span class="sr-only">Buscar turma</span>
                    <input type="search" x-model.debounce.150ms="busca" placeholder="Buscar turma...">
                </label>
                <label>
                    <span class="sr-only">Filtrar por turno</span>
                    <select x-model="turno">
                        <option value="">Todos os turnos</option>
                        @foreach ($turmasFiltradas->pluck('turno')->filter()->unique()->sort() as $turnoOpcao)
                            <option value="{{ mb_strtolower($turnoOpcao) }}">{{ ucfirst($turnoOpcao) }}</option>
                        @endforeach
                    </select>
                </label>
            </div>

            <div class="av-professor-class-list">
                @forelse ($turmasFiltradas as $turmaItem)
                    @php($turmaIdAtual = (int) $turmaItem->id)
                    @php($turmaExpandida = $this->turmaEstaExpandida($turmaIdAtual))
                    <section class="av-professor-class-card {{ $turmaExpandida ? 'is-open' : '' }}" data-search="{{ mb_strtolower($this->rotuloTurma($turmaItem).' '.($turmaItem->escola?->nome ?? '')) }}" data-turno="{{ mb_strtolower((string) $turmaItem->turno) }}" x-show="$el.dataset.search.includes(busca.toLowerCase()) && (!turno || $el.dataset.turno === turno)" wire:key="turma-professor-{{ $turmaIdAtual }}">
                        <button type="button" class="av-professor-class-toggle" wire:click="abrirTurma({{ $turmaIdAtual }})">
                            <div class="av-professor-class-toggle__title">
                                <span class="av-professor-class-icon" aria-hidden="true">T</span>
                                <div>
                                    <h3>{{ $this->rotuloTurma($turmaItem) }}</h3>
                                    <p>{{ $turmaItem->escola?->nome }} · {{ ucfirst((string) $turmaItem->turno) }}</p>
                                </div>
                            </div>
                            <div class="av-professor-class-toggle__side">
                                <span class="av-professor-load-hint">{{ $turmaExpandida ? 'Ocultar componentes' : 'Ver componentes' }}</span>
                                <span class="av-pauta-arrow {{ $turmaExpandida ? 'is-open' : '' }}" aria-hidden="true">⌄</span>
                            </div>
                        </button>

                        @if ($turmaExpandida)
                            @php($componentesTurma = $this->componentesNavegacaoDaTurma)
                            <div class="av-professor-components" wire:loading.class="is-loading" wire:target="abrirTurma({{ $turmaIdAtual }})">
                                <div class="av-professor-components__heading">
                                    <div><strong>Seus componentes</strong><span>Selecione um componente para abrir a avaliação.</span></div>
                                    <span>{{ $componentesTurma->count() }} {{ $componentesTurma->count() === 1 ? 'componente' : 'componentes' }}</span>
                                </div>
                                <div class="av-professor-component-grid">
                                    @forelse ($componentesTurma as $grupo)
                                        <button type="button" class="av-professor-component-card" wire:click="abrirComponente({{ $turmaIdAtual }}, {{ $grupo['componente_id'] }})">
                                            <span class="av-professor-component-card__name">{{ $grupo['componente_nome'] }}</span>
                                            <span class="av-professor-component-card__teacher">{{ $grupo['professor_nome'] }}</span>
                                            <span class="av-professor-component-card__meta"><span>{{ $grupo['pautas_total'] }} {{ $grupo['pautas_total'] === 1 ? 'pauta' : 'pautas' }}</span></span>
                                            <span class="av-professor-component-card__action">Abrir avaliação <span aria-hidden="true">→</span></span>
                                        </button>
                                    @empty
                                        <section class="av-note av-note--warning">Nenhum componente disponível para esta turma.</section>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </section>
                @empty
                    <section class="av-professor-empty"><strong>Nenhuma turma disponível</strong><p>Não há turmas neste recorte.</p></section>
                @endforelse
            </div>
        </section>
    @endif

    @if ($this->grupoComponenteModal && $this->turmaModal)
        @php($turmaIdAtual = (int) $turma)
        @php($grupoModal = $this->grupoComponenteModal)
        @php($alunosModal = $this->alunosDaTurma($turmaIdAtual))
        <div class="av-professor-modal-backdrop" role="presentation" wire:click.self="fecharComponente">
            <section class="av-professor-modal" role="dialog" aria-modal="true" aria-labelledby="av-professor-modal-title" wire:key="modal-componente-{{ $turmaIdAtual }}-{{ $componenteModalId }}">
                <header class="av-professor-modal__header">
                    <div>
                        <span class="av-professor-eyebrow">{{ $this->rotuloTurma($this->turmaModal) }}</span>
                        <h2 id="av-professor-modal-title">{{ $grupoModal['componente_nome'] }}</h2>
                        <p>{{ $grupoModal['professor_nome'] }} · {{ $this->pautasDoComponenteModal->count() }} pautas · {{ $alunosModal->count() }} alunos</p>
                    </div>
                    <button type="button" class="av-professor-modal__close" wire:click="fecharComponente" aria-label="Fechar avaliação">×</button>
                </header>

                <div class="av-professor-modal__tabs" role="tablist" aria-label="Forma de preenchimento">
                    <button type="button" class="{{ $visualizacao === 'pautas' ? 'is-active' : '' }}" wire:click="definirVisualizacao('pautas')">Por pautas</button>
                    <button type="button" class="{{ $visualizacao === 'alunos' ? 'is-active' : '' }}" wire:click="definirVisualizacao('alunos')">Por alunos</button>
                </div>

                <div class="av-professor-modal__body">
                    @if ($visualizacao === 'pautas')
                        <aside class="av-professor-modal__navigation" aria-label="Pautas do componente">
                            <strong>Selecione uma pauta</strong>
                            <div class="av-professor-navigation-list">
                                @foreach ($this->pautasDoComponenteModal as $indice => $pautaItem)
                                    @php($progressoPauta = $this->progressoPorPauta[$turmaIdAtual][$pautaItem->id] ?? ['preenchidas' => 0, 'total' => 0, 'percentual' => 0])
                                    <button type="button" class="{{ (int) $pautaModalId === (int) $pautaItem->id ? 'is-active' : '' }}" wire:click="selecionarPautaModal({{ $pautaItem->id }})">
                                        <span class="av-professor-navigation-index">{{ $indice + 1 }}</span>
                                        <span>
                                            <strong>{{ $pautaItem->texto }}</strong>
                                            <small>{{ $progressoPauta['preenchidas'] }}/{{ $progressoPauta['total'] }} respondidas</small>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </aside>

                        <main class="av-professor-modal__workspace">
                            @if ($this->pautaModal)
                                <div class="av-professor-workspace-heading">
                                    <div>
                                        <span class="av-professor-eyebrow">Pauta selecionada</span>
                                        <h3>{{ $this->pautaModal->texto }}</h3>
                                    </div>
                                    <span>{{ $alunosModal->count() }} {{ $alunosModal->count() === 1 ? 'aluno' : 'alunos' }}</span>
                                </div>
                                <div class="gi-table-wrap av-professor-student-table">
                                    <table class="gi-table">
                                        <thead><tr><th>Aluno</th><th>Alternativa</th><th>Observação</th></tr></thead>
                                        <tbody>
                                            @forelse ($alunosModal as $aluno)
                                                @include('livewire.avaliacoes.partials.avaliacao-resposta-professor-linha', [
                                                    'pauta' => $this->pautaModal,
                                                    'aluno' => $aluno,
                                                    'turmaIdAtual' => $turmaIdAtual,
                                                ])
                                            @empty
                                                <tr><td colspan="3">Nenhum aluno disponível nesta turma.</td></tr>
                                            @endforelse
                                        </tbody>
                                    </table>
                                </div>
                            @else
                                <div class="av-professor-selection-empty">
                                    <span aria-hidden="true">☰</span>
                                    <strong>Selecione uma pauta</strong>
                                    <p>A lista de alunos e os campos de resposta aparecerão aqui.</p>
                                </div>
                            @endif
                        </main>
                    @else
                        <aside class="av-professor-modal__navigation" aria-label="Alunos da turma">
                            <strong>Selecione um aluno</strong>
                            <div class="av-professor-navigation-list">
                                @foreach ($alunosModal as $alunoItem)
                                    @php($progressoAluno = $this->progressoPorAluno[$alunoItem->id] ?? ['preenchidas' => 0, 'total' => 0])
                                    <button type="button" class="{{ (int) $alunoEmFoco === (int) $alunoItem->id ? 'is-active' : '' }}" wire:click="selecionarAlunoModal({{ $alunoItem->id }})">
                                        <span class="av-professor-navigation-avatar">{{ mb_strtoupper(mb_substr($alunoItem->nome, 0, 1)) }}</span>
                                        <span>
                                            <strong>{{ $alunoItem->nome }}</strong>
                                            <small>{{ $progressoAluno['preenchidas'] }}/{{ $progressoAluno['total'] }} pautas respondidas</small>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </aside>

                        <main class="av-professor-modal__workspace">
                            @php($alunoSelecionado = $alunosModal->firstWhere('id', (int) $alunoEmFoco))
                            @if ($alunoSelecionado)
                                <div class="av-professor-workspace-heading">
                                    <div>
                                        <span class="av-professor-eyebrow">Aluno selecionado</span>
                                        <h3>{{ $alunoSelecionado->nome }}</h3>
                                    </div>
                                    <span>{{ $alunoSelecionado->cgm ? 'CGM '.$alunoSelecionado->cgm : 'Sem CGM' }}</span>
                                </div>
                                <div class="av-professor-answer-list">
                                    @foreach ($this->pautasDoComponenteModal as $pautaItem)
                                        @include('livewire.avaliacoes.partials.avaliacao-resposta-professor', [
                                            'pauta' => $pautaItem,
                                            'aluno' => $alunoSelecionado,
                                            'turmaIdAtual' => $turmaIdAtual,
                                            'tituloResposta' => $pautaItem->texto,
                                            'subtituloResposta' => null,
                                        ])
                                    @endforeach
                                    @include('livewire.avaliacoes.partials.avaliacao-informacao-complementar-professor', [
                                        'componenteId' => $componenteModalId,
                                        'aluno' => $alunoSelecionado,
                                        'turmaIdAtual' => $turmaIdAtual,
                                    ])
                                </div>
                            @else
                                <div class="av-professor-selection-empty">
                                    <span aria-hidden="true">◎</span>
                                    <strong>Selecione um aluno</strong>
                                    <p>As pautas e os campos de resposta aparecerão aqui.</p>
                                </div>
                            @endif
                        </main>
                    @endif
                </div>

                <footer class="av-professor-modal__footer">
                    <span x-show="autosaveConfirmado">Alterações salvas automaticamente.</span>
                    <span x-show="autosaveFalhou" class="is-error">Não foi possível salvar automaticamente.</span>
                    <div>
                        <button type="button" class="gi-action" wire:click="fecharComponente">Fechar</button>
                        @if ($this->podeResponder())
                            <button type="button" class="gi-action gi-action--primary" wire:click="salvarAlteracoes" wire:loading.attr="disabled" wire:target="salvarAlteracoes">
                                <span wire:loading.remove wire:target="salvarAlteracoes">Salvar alterações</span>
                                <span wire:loading wire:target="salvarAlteracoes">Salvando...</span>
                            </button>
                        @endif
                    </div>
                </footer>
            </section>
        </div>
    @endif
@endif

@include('livewire.avaliacoes.partials.avaliacao-turma-professor-styles')
