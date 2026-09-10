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
        <div class="av-professor-evaluation-grid">
            @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                @php($avaliacaoUrl = \App\Filament\Admin\Pages\AvaliacoesProfessor::getUrl(['avaliacao' => $avaliacaoItem->id]))
                <article class="av-professor-evaluation-card" wire:key="avaliacao-professor-{{ $avaliacaoItem->id }}">
                    <div class="av-professor-evaluation-card__top">
                        <span class="av-professor-status">Disponível</span>
                        <span>{{ $avaliacaoItem->turmas->count() }} {{ $avaliacaoItem->turmas->count() === 1 ? 'turma' : 'turmas' }}</span>
                    </div>
                    <div class="av-professor-evaluation-card__body">
                        <span>{{ $avaliacaoItem->tipo?->nome ?? 'Avaliação' }}</span>
                        <h3>{{ $avaliacaoItem->nome }}</h3>
                        <p>
                            De {{ optional($avaliacaoItem->data_inicio_preenchimento ?? $avaliacaoItem->data_inicio)->format('d/m/Y') }}
                            a {{ optional($avaliacaoItem->data_fim_preenchimento ?? $avaliacaoItem->data_fim)->format('d/m/Y') }}
                        </p>
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
    <section class="av-professor-context">
        <a class="av-professor-back" href="{{ \App\Filament\Admin\Pages\AvaliacoesProfessor::getUrl() }}" wire:navigate>
            <span aria-hidden="true">←</span> Voltar às avaliações
        </a>
        <div class="av-professor-context__row">
            <div>
                <span class="av-professor-eyebrow">{{ $avaliacaoAtual->tipo?->nome ?? 'Avaliação' }}</span>
                <h2>{{ $avaliacaoAtual->nome }}</h2>
                <p>Abra uma turma para visualizar os componentes que você pode avaliar.</p>
            </div>
            <span class="av-workspace-count">{{ $this->turmasDaAvaliacaoProfessor->count() }} {{ $this->turmasDaAvaliacaoProfessor->count() === 1 ? 'turma' : 'turmas' }}</span>
        </div>
    </section>

    @if (! $this->podeResponder())
        <section class="av-note">Esta avaliação está disponível somente para leitura.</section>
    @endif

    <div class="av-professor-class-list">
        @forelse ($this->turmasDaAvaliacaoProfessor as $turmaItem)
            @php($turmaIdAtual = (int) $turmaItem->id)
            @php($turmaExpandida = $this->turmaEstaExpandida($turmaIdAtual))
            @php($progressoTurma = $turmaExpandida ? ($this->progressoPorTurma[$turmaIdAtual] ?? null) : null)
            <section class="av-professor-class-card {{ $turmaExpandida ? 'is-open' : '' }}" wire:key="turma-professor-{{ $turmaIdAtual }}">
                <button type="button" class="av-professor-class-toggle" wire:click="abrirTurma({{ $turmaIdAtual }})">
                    <div class="av-professor-class-toggle__title">
                        <span class="av-professor-class-icon" aria-hidden="true">{{ mb_strtoupper(mb_substr($this->rotuloTurma($turmaItem), 0, 1)) }}</span>
                        <div>
                            <h3>{{ $this->rotuloTurma($turmaItem) }}</h3>
                            <p>{{ $turmaItem->escola?->nome }} · {{ $turmaItem->serie?->nome }}</p>
                        </div>
                    </div>

                    <div class="av-professor-class-toggle__side">
                        @if ($progressoTurma)
                            <div class="av-professor-compact-progress" data-av-progress-type="turma" data-av-progress-turma="{{ $turmaIdAtual }}" data-av-progress-filled="{{ $progressoTurma['preenchidas'] }}" data-av-progress-total="{{ $progressoTurma['total'] }}">
                                <span>{{ $progressoTurma['preenchidas'] }}/{{ $progressoTurma['total'] }}</span>
                                <span>{{ $progressoTurma['percentual'] }}%</span>
                                <div class="av-progress-track av-progress-track--compact">
                                    <div class="av-progress-bar" style="width: {{ $progressoTurma['percentual'] }}%"></div>
                                </div>
                            </div>
                        @else
                            <span class="av-professor-load-hint">Abrir componentes</span>
                        @endif
                        <span class="av-pauta-arrow {{ $turmaExpandida ? 'is-open' : '' }}" aria-hidden="true">⌄</span>
                    </div>
                </button>

                @if ($turmaExpandida)
                    <div class="av-professor-components" wire:loading.class="is-loading" wire:target="abrirTurma({{ $turmaIdAtual }})">
                        <div class="av-professor-components__heading">
                            <div>
                                <strong>Seus componentes</strong>
                                <span>Selecione um componente para abrir a avaliação.</span>
                            </div>
                            <span>{{ $this->gruposPorComponenteDaTurma($turmaIdAtual)->count() }} {{ $this->gruposPorComponenteDaTurma($turmaIdAtual)->count() === 1 ? 'componente' : 'componentes' }}</span>
                        </div>

                        <div class="av-professor-component-grid">
                            @forelse ($this->gruposPorComponenteDaTurma($turmaIdAtual) as $grupo)
                                @php($pautasIds = collect($grupo['pautas'])->pluck('id'))
                                @php($preenchidas = $pautasIds->sum(fn ($pautaId) => (int) ($this->progressoPorPauta[$turmaIdAtual][$pautaId]['preenchidas'] ?? 0)))
                                @php($total = $pautasIds->sum(fn ($pautaId) => (int) ($this->progressoPorPauta[$turmaIdAtual][$pautaId]['total'] ?? 0)))
                                @php($percentual = $total > 0 ? min(100, (int) round(($preenchidas / $total) * 100)) : 0)
                                <button type="button" class="av-professor-component-card" wire:click="abrirComponente({{ $turmaIdAtual }}, {{ $grupo['componente_id'] }})">
                                    <span class="av-professor-component-card__name">{{ $grupo['componente_nome'] }}</span>
                                    <span class="av-professor-component-card__teacher">{{ $grupo['professor_nome'] }}</span>
                                    <span class="av-professor-component-card__meta">
                                        <span>{{ count($grupo['pautas']) }} {{ count($grupo['pautas']) === 1 ? 'pauta' : 'pautas' }}</span>
                                        <span>{{ $percentual }}% preenchido</span>
                                    </span>
                                    <span class="av-progress-track av-progress-track--compact">
                                        <span class="av-progress-bar" style="width: {{ $percentual }}%"></span>
                                    </span>
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
            <section class="av-professor-empty">
                <strong>Nenhuma turma disponível</strong>
                <p>Esta avaliação não possui turmas vinculadas aos seus componentes.</p>
            </section>
        @endforelse
    </div>

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
                                <div class="av-professor-answer-list">
                                    @foreach ($alunosModal as $aluno)
                                        <div class="av-professor-answer-group">
                                            @include('livewire.avaliacoes.partials.avaliacao-resposta-professor', [
                                                'pauta' => $this->pautaModal,
                                                'aluno' => $aluno,
                                                'turmaIdAtual' => $turmaIdAtual,
                                                'tituloResposta' => $aluno->nome,
                                                'subtituloResposta' => $aluno->cgm ? 'CGM '.$aluno->cgm : null,
                                            ])
                                            @include('livewire.avaliacoes.partials.avaliacao-informacao-complementar-professor', [
                                                'componenteId' => $componenteModalId,
                                                'aluno' => $aluno,
                                                'turmaIdAtual' => $turmaIdAtual,
                                            ])
                                        </div>
                                    @endforeach
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
