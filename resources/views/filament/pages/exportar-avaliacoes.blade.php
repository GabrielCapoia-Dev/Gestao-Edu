<x-filament-panels::page>
    <div class="av-livewire-root">
        <div class="gi-page av-page">
            <section class="gi-hero">
                <div>
                    <p class="gi-eyebrow">Pedagogico</p>
                    <h1>Exportar Avaliacoes</h1>
                    <p>Gere documentos por aluno, turma ou escola.</p>
                </div>
            </section>

            <section class="gi-panel">
                <div class="av-form-grid av-form-grid--two">
                    <label class="gi-field">
                        <span>Escopo</span>
                        <select wire:model.live="escopo">
                            <option value="aluno">Aluno</option>
                            <option value="turma">Turma</option>
                            <option value="escola">Escola</option>
                        </select>
                    </label>

                    @if ($escopo === 'aluno')
                        <label class="gi-field">
                            <span>Buscar aluno</span>
                            <input type="search" wire:model.live.debounce.350ms="buscaAluno" placeholder="Nome ou CGM">
                        </label>
                    @else
                        <label class="gi-field">
                            <span>Avaliacao</span>
                            <select wire:model.live="avaliacao">
                                <option value="">Selecione uma avaliacao</option>
                                @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                                    <option value="{{ $avaliacaoItem->id }}">
                                        {{ $avaliacaoItem->nome }}
                                        @if ($avaliacaoItem->data_inicio)
                                            | {{ $avaliacaoItem->data_inicio->format('d/m/Y') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    @endif
                </div>

                @if ($escopo === 'aluno')
                    <div class="av-form-grid av-form-grid--two">
                        <label class="gi-field">
                            <span>Aluno</span>
                            <select wire:model.live="aluno">
                                <option value="">Selecione um aluno</option>
                                @foreach ($this->alunosDisponiveis as $alunoItem)
                                    <option value="{{ $alunoItem->id }}">
                                        {{ $alunoItem->nome }} | CGM: {{ $alunoItem->cgm }} | {{ $alunoItem->turma?->escola?->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label class="gi-field">
                            <span>Avaliacao</span>
                            <select wire:model.live="avaliacao" @disabled(! $aluno)>
                                <option value="">Selecione uma avaliacao</option>
                                @foreach ($this->avaliacoesDisponiveis as $avaliacaoItem)
                                    <option value="{{ $avaliacaoItem->id }}">
                                        {{ $avaliacaoItem->nome }}
                                        @if ($avaliacaoItem->data_inicio || $avaliacaoItem->data_fim)
                                            | {{ optional($avaliacaoItem->data_inicio)->format('d/m/Y') }}
                                            ate
                                            {{ optional($avaliacaoItem->data_fim)->format('d/m/Y') }}
                                        @endif
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @elseif ($escopo === 'turma')
                    <div class="av-form-grid av-form-grid--two">
                        <label class="gi-field">
                            <span>Escola</span>
                            <select wire:model.live="escola" @disabled(! $avaliacao)>
                                <option value="">Todas as escolas</option>
                                @foreach ($this->escolasDisponiveis as $escolaItem)
                                    <option value="{{ $escolaItem->id }}">{{ $escolaItem->nome }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="gi-field">
                            <span>Turma</span>
                            <select wire:model.live="turma" @disabled(! $avaliacao)>
                                <option value="">Selecione uma turma</option>
                                @foreach ($this->turmasDisponiveis as $turmaItem)
                                    <option value="{{ $turmaItem->id }}">
                                        {{ $turmaItem->escola?->nome }} | {{ $turmaItem->serie?->nome }} | {{ $turmaItem->nome }}
                                    </option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @elseif ($escopo === 'escola')
                    <div class="av-form-grid av-form-grid--two">
                        <label class="gi-field">
                            <span>Escola</span>
                            <select wire:model.live="escola" @disabled(! $avaliacao)>
                                <option value="">Selecione uma escola</option>
                                @foreach ($this->escolasDisponiveis as $escolaItem)
                                    <option value="{{ $escolaItem->id }}">{{ $escolaItem->nome }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>
                @endif
            </section>

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div>
                        <h3 class="av-pauta-title">Arquivos</h3>
                        @if ($this->podeExportarSelecao)
                            <p class="av-pauta-meta">A selecao esta pronta para exportacao.</p>
                        @else
                            <p class="av-pauta-meta">Complete os campos acima para liberar os arquivos.</p>
                        @endif
                    </div>

                    <div class="gi-toolbar-right">
                        @if ($this->podeExportarSelecao)
                            <a class="gi-action" href="{{ $this->pdfUrl }}">
                                Exportar PDF
                            </a>
                            <a class="gi-action gi-action--primary" href="{{ $this->csvUrl }}">
                                Exportar CSV
                            </a>
                        @else
                            <button type="button" class="gi-action" disabled>Exportar PDF</button>
                            <button type="button" class="gi-action gi-action--primary" disabled>Exportar CSV</button>
                        @endif
                    </div>
                </div>
            </section>
        </div>

        @include('filament.pages.partials.avaliacoes-page-styles')
    </div>
</x-filament-panels::page>
