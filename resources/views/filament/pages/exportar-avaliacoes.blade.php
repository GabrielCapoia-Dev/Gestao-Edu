<x-filament-panels::page>
    <div class="av-livewire-root">
        <div class="gi-page av-page">

            <section class="gi-panel">
                <div class="gi-toolbar">
                    <div>
                        <h3 class="av-pauta-title">Listagem</h3>
                        <p class="av-pauta-meta">Os registros respeitam as escolas vinculadas ao usuário.</p>
                    </div>

                    <div class="av-mode-actions">
                        <label class="gi-field av-bulk-select">
                            <span>Buscar</span>
                            <input type="search" wire:model.live.debounce.350ms="busca" placeholder="Aluno, CGM, turma, escola ou avaliação">
                        </label>

                        <label class="gi-field av-page-size">
                            <span>Mostrar</span>
                            <select wire:model.live="perPage">
                                <option value="5">5</option>
                                <option value="10">10</option>
                                <option value="25">25</option>
                                <option value="50">50</option>
                                <option value="100">100</option>
                            </select>
                        </label>

                        <div class="av-segmented-control" role="tablist">
                            <button type="button" class="{{ $modoListagem === 'alunos' ? 'is-active' : '' }}" wire:click="definirModo('alunos')">
                                Por alunos
                            </button>
                            <button type="button" class="{{ $modoListagem === 'turmas' ? 'is-active' : '' }}" wire:click="definirModo('turmas')">
                                Por turmas
                            </button>
                        </div>
                    </div>
                </div>
            </section>

            @if ($modoListagem === 'alunos')
                @php($alunosComAvaliacoes = $this->alunosComAvaliacoes)
                <section class="gi-panel">
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                                <tr>
                                    <th>Aluno</th>
                                    <th>Turma</th>
                                    <th>Avaliações</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($alunosComAvaliacoes as $alunoItem)
                                    @php($avaliacoesAluno = $alunoItem->turma?->avaliacoes?->sortBy([['data_inicio', 'asc'], ['data_fim', 'asc'], ['id', 'asc']]) ?? collect())
                                    <tr wire:key="exportar-aluno-{{ $alunoItem->id }}">
                                        <td>
                                            <strong>{{ $alunoItem->nome }}</strong>
                                            <small>CGM: {{ $alunoItem->cgm }}</small>
                                            <small>Status: {{ $alunoItem->statusLabel() }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $this->turmaLabel($alunoItem->turma) }}</strong>
                                            <small>{{ $alunoItem->turma?->escola?->nome }}</small>
                                        </td>
                                        <td>
                                            <strong>{{ $avaliacoesAluno->count() }} avaliação(ões)</strong>
                                            <small>{{ $avaliacoesAluno->take(2)->pluck('nome')->implode(' | ') }}</small>
                                        </td>
                                        <td class="av-table-actions">
                                            <button type="button" class="gi-action gi-action--primary" wire:click="abrirAluno({{ $alunoItem->id }})">
                                                Abrir
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4">
                                            <section class="av-note">Nenhum aluno com avaliação encontrado neste escopo.</section>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="av-pagination">
                        @include('filament.pages.partials.exportar-avaliacoes-pagination', ['paginator' => $alunosComAvaliacoes])
                    </div>
                </section>
            @else
                @php($turmasComAvaliacoes = $this->turmasComAvaliacoes)
                <section class="gi-panel">
                    <div class="gi-table-wrap">
                        <table class="gi-table">
                            <thead>
                                <tr>
                                    <th>Turma</th>
                                    <th>Escola</th>
                                    <th>Alunos</th>
                                    <th>Avaliações</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($turmasComAvaliacoes as $turmaItem)
                                    @php($avaliacoesTurma = $turmaItem->avaliacoes?->sortBy([['data_inicio', 'asc'], ['data_fim', 'asc'], ['id', 'asc']]) ?? collect())
                                    <tr wire:key="exportar-turma-{{ $turmaItem->id }}">
                                        <td>
                                            <strong>{{ $this->turmaLabel($turmaItem) }}</strong>
                                        </td>
                                        <td>{{ $turmaItem->escola?->nome }}</td>
                                        <td>{{ $turmaItem->alunos_count }}</td>
                                        <td>
                                            <strong>{{ $avaliacoesTurma->count() }} avaliação(ões)</strong>
                                            <small>{{ $avaliacoesTurma->take(2)->pluck('nome')->implode(' | ') }}</small>
                                        </td>
                                        <td class="av-table-actions">
                                            <button type="button" class="gi-action gi-action--primary" wire:click="abrirTurma({{ $turmaItem->id }})">
                                                Abrir
                                            </button>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="5">
                                            <section class="av-note">Nenhuma turma com avaliação encontrada neste escopo.</section>
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <div class="av-pagination">
                        @include('filament.pages.partials.exportar-avaliacoes-pagination', ['paginator' => $turmasComAvaliacoes])
                    </div>
                </section>
            @endif
        </div>

        @if ($this->alunoSelecionado)
            <div class="av-slide-overlay" wire:click="fecharModais">
                <aside class="av-slide-panel" wire:click.stop>
                    <header class="av-slide-head">
                        <div>
                            <p>Aluno</p>
                            <h2>{{ $this->alunoSelecionado->nome }}</h2>
                            <span>CGM: {{ $this->alunoSelecionado->cgm }} | {{ $this->alunoSelecionado->turma?->nome }}</span>
                        </div>
                        <button type="button" wire:click="fecharModais">Fechar</button>
                    </header>

                    <div class="av-slide-list">
                        @forelse ($this->avaliacoesAlunoSelecionado as $avaliacaoItem)
                            <section class="av-export-row" wire:key="modal-aluno-avaliacao-{{ $avaliacaoItem->id }}">
                                <div>
                                    <h3>{{ $avaliacaoItem->nome }}</h3>
                                    <p>{{ $avaliacaoItem->tipo?->nome }} | {{ optional($avaliacaoItem->data_inicio)->format('d/m/Y') }} até {{ optional($avaliacaoItem->data_fim)->format('d/m/Y') }}</p>
                                </div>
                                <div class="av-export-actions">
                                    <a class="gi-action" href="{{ $this->alunoExportPdfUrl((int) $avaliacaoItem->id, (int) $this->alunoSelecionado->id) }}">PDF</a>
                                    <a class="gi-action gi-action--primary" href="{{ $this->alunoExportCsvUrl((int) $avaliacaoItem->id, (int) $this->alunoSelecionado->id) }}">CSV</a>
                                </div>
                            </section>
                        @empty
                            <section class="av-note">Nenhuma avaliação encontrada para este aluno.</section>
                        @endforelse
                    </div>
                </aside>
            </div>
        @endif

        @if ($this->turmaSelecionada)
            <div class="av-slide-overlay" wire:click="fecharModais">
                <aside class="av-slide-panel av-slide-panel--wide" wire:click.stop>
                    <header class="av-slide-head">
                        <div>
                            <p>Turma</p>
                            <h2>{{ $this->turmaSelecionada->nome }}</h2>
                            <span>{{ $this->turmaSelecionada->serie?->nome }} | {{ $this->turmaSelecionada->escola?->nome }}</span>
                        </div>
                        <button type="button" wire:click="fecharModais">Fechar</button>
                    </header>

                    <div class="av-slide-list">
                        @forelse ($this->avaliacoesTurmaSelecionada as $avaliacaoItem)
                            <section class="av-export-row" wire:key="modal-turma-avaliacao-{{ $avaliacaoItem->id }}">
                                <div>
                                    <h3>{{ $avaliacaoItem->nome }}</h3>
                                    <p>{{ $avaliacaoItem->tipo?->nome }} | {{ optional($avaliacaoItem->data_inicio)->format('d/m/Y') }} até {{ optional($avaliacaoItem->data_fim)->format('d/m/Y') }}</p>
                                </div>
                                <div class="av-export-actions">
                                    <a class="gi-action" href="{{ $this->turmaExportPdfUrl((int) $avaliacaoItem->id, (int) $this->turmaSelecionada->id) }}">Turma PDF</a>
                                    <a class="gi-action" href="{{ $this->turmaExportCsvUrl((int) $avaliacaoItem->id, (int) $this->turmaSelecionada->id) }}">Turma CSV</a>
                                    <button type="button" class="gi-action gi-action--primary" wire:click="abrirExportacaoAlunoDaTurma({{ $avaliacaoItem->id }})">
                                        Por aluno
                                    </button>
                                </div>
                            </section>
                        @empty
                            <section class="av-note">Nenhuma avaliação encontrada para esta turma.</section>
                        @endforelse
                    </div>
                </aside>
            </div>
        @endif

        @if ($this->turmaSelecionada && $this->avaliacaoParaAlunoDaTurma)
            <div class="av-modal-overlay" wire:click="fecharModalAlunoDaTurma">
                <section class="av-export-modal" wire:click.stop>
                    <header class="av-slide-head">
                        <div>
                            <p>Exportar aluno da turma</p>
                            <h2>{{ $this->avaliacaoParaAlunoDaTurma->nome }}</h2>
                        </div>
                        <button type="button" wire:click="fecharModalAlunoDaTurma">Fechar</button>
                    </header>

                    <label class="gi-field">
                        <span>Aluno</span>
                        <select wire:model.live="alunoDaTurmaSelecionadoId">
                            <option value="">Selecione um aluno</option>
                            @foreach ($this->alunosDaTurmaSelecionada as $alunoItem)
                                <option value="{{ $alunoItem->id }}">{{ $alunoItem->nome }} | CGM: {{ $alunoItem->cgm }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="av-export-actions av-export-actions--footer">
                        <a class="gi-action" href="{{ $this->turmaExportPdfUrl((int) $this->avaliacaoParaAlunoDaTurma->id, (int) $this->turmaSelecionada->id) }}">Turma inteira PDF</a>
                        <a class="gi-action" href="{{ $this->turmaExportCsvUrl((int) $this->avaliacaoParaAlunoDaTurma->id, (int) $this->turmaSelecionada->id) }}">Turma inteira CSV</a>

                        @if ($alunoDaTurmaSelecionadoId)
                            <a class="gi-action gi-action--primary" href="{{ $this->alunoExportPdfUrl((int) $this->avaliacaoParaAlunoDaTurma->id, (int) $alunoDaTurmaSelecionadoId) }}">Aluno PDF</a>
                            <a class="gi-action gi-action--primary" href="{{ $this->alunoExportCsvUrl((int) $this->avaliacaoParaAlunoDaTurma->id, (int) $alunoDaTurmaSelecionadoId) }}">Aluno CSV</a>
                        @else
                            <button type="button" class="gi-action gi-action--primary" disabled>Aluno PDF</button>
                            <button type="button" class="gi-action gi-action--primary" disabled>Aluno CSV</button>
                        @endif
                    </div>
                </section>
            </div>
        @endif

        @include('filament.pages.partials.avaliacoes-page-styles')

        <style>
            .av-livewire-root .av-mode-actions {
                display: grid;
                grid-template-columns: minmax(260px, 1fr) 92px auto;
                gap: 12px;
                align-items: end;
                justify-content: end;
            }

            .av-livewire-root .av-page-size {
                width: 92px;
            }

            .av-livewire-root .av-segmented-control {
                display: inline-grid;
                grid-template-columns: repeat(2, minmax(96px, 1fr));
                width: max-content;
                min-width: 226px;
                height: 42px;
                padding: 3px;
                overflow: hidden;
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                background: #eef2f7;
                box-sizing: border-box;
            }

            .av-livewire-root .av-segmented-control button {
                display: flex;
                align-items: center;
                justify-content: center;
                width: 100%;
                min-width: 0;
                height: 34px;
                margin: 0;
                border: 0;
                border-radius: 6px;
                padding: 0 10px;
                appearance: none;
                color: #334155;
                background: transparent;
                box-shadow: none;
                font-size: 14px;
                font-weight: 700;
                line-height: 1;
                white-space: nowrap;
                cursor: pointer;
            }

            .av-livewire-root .av-segmented-control button.is-active {
                color: #0b225c;
                background: #fff;
                box-shadow: 0 1px 3px rgba(15, 23, 42, 0.12);
            }

            .av-livewire-root .av-segmented-control button:focus,
            .av-livewire-root .av-segmented-control button:focus-visible {
                outline: 2px solid #93c5fd;
                outline-offset: -2px;
            }

            .av-livewire-root .gi-table td strong {
                word-break: normal;
            }

            .av-table-actions {
                text-align: right;
                white-space: nowrap;
            }

            .av-pagination {
                margin-top: 16px;
            }

            .av-livewire-root .av-pagination {
                padding-top: 14px;
                border-top: 1px solid #e2e8f0;
            }

            .av-livewire-root .av-pager {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: center;
                justify-content: space-between;
            }

            .av-livewire-root .av-pager--single {
                justify-content: flex-start;
            }

            .av-livewire-root .av-pager-summary {
                margin: 0;
                color: #475569;
                font-size: 13px;
                font-weight: 600;
            }

            .av-livewire-root .av-pager-list {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                align-items: center;
                justify-content: flex-end;
            }

            .av-livewire-root .av-pager-button,
            .av-livewire-root .av-pager-ellipsis {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                min-width: 34px;
                height: 34px;
                border-radius: 8px;
                padding: 0 10px;
                border: 1px solid #cbd5e1;
                color: #1e3a8a;
                background: #fff;
                font-size: 13px;
                font-weight: 700;
                line-height: 1;
                text-decoration: none;
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
            }

            .av-livewire-root button.av-pager-button {
                cursor: pointer;
            }

            .av-livewire-root button.av-pager-button:hover {
                border-color: #93c5fd;
                color: #0b225c;
                background: #eff6ff;
            }

            .av-livewire-root .av-pager-button.is-active {
                border-color: #1e3a8a;
                color: #fff;
                background: #1e3a8a;
            }

            .av-livewire-root .av-pager-button.is-disabled {
                color: #94a3b8;
                background: #f8fafc;
                cursor: not-allowed;
            }

            .av-livewire-root .av-pager-ellipsis {
                min-width: 24px;
                border-color: transparent;
                color: #64748b;
                background: transparent;
                box-shadow: none;
            }

            .av-slide-overlay,
            .av-modal-overlay {
                position: fixed;
                inset: 0;
                z-index: 60;
                background: rgba(15, 23, 42, 0.35);
            }

            .av-slide-panel {
                position: fixed;
                inset: 0 0 0 auto;
                width: min(620px, 100vw);
                padding: 24px;
                overflow: auto;
                background: #fff;
                box-shadow: -18px 0 40px rgba(15, 23, 42, 0.18);
            }

            .av-slide-panel--wide {
                width: min(760px, 100vw);
            }

            .av-slide-head {
                display: flex;
                gap: 16px;
                align-items: flex-start;
                justify-content: space-between;
                margin-bottom: 20px;
            }

            .av-slide-head p {
                margin: 0 0 4px;
                color: #64748b;
                font-size: 12px;
                font-weight: 700;
                text-transform: uppercase;
            }

            .av-slide-head h2 {
                margin: 0;
                color: #0f172a;
                font-size: 22px;
                font-weight: 800;
            }

            .av-slide-head span {
                display: block;
                margin-top: 4px;
                color: #475569;
                font-size: 13px;
            }

            .av-slide-head button {
                border: 1px solid #cbd5e1;
                border-radius: 8px;
                padding: 8px 12px;
                color: #334155;
                background: #fff;
                font-weight: 700;
            }

            .av-slide-list {
                display: grid;
                gap: 12px;
            }

            .av-export-row {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                gap: 16px;
                align-items: center;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                padding: 14px;
                background: #f8fafc;
            }

            .av-export-row h3 {
                margin: 0 0 4px;
                color: #0f172a;
                font-size: 15px;
                font-weight: 800;
            }

            .av-export-row p {
                margin: 0;
                color: #64748b;
                font-size: 13px;
            }

            .av-export-actions {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                justify-content: flex-end;
            }

            .av-export-actions--footer {
                justify-content: flex-start;
                margin-top: 18px;
            }

            .av-export-modal {
                position: fixed;
                top: 50%;
                left: 50%;
                width: min(560px, calc(100vw - 32px));
                transform: translate(-50%, -50%);
                border-radius: 12px;
                padding: 22px;
                background: #fff;
                box-shadow: 0 24px 60px rgba(15, 23, 42, 0.24);
            }

            @media (max-width: 720px) {
                .av-livewire-root .av-mode-actions {
                    grid-template-columns: 1fr;
                }

                .av-livewire-root .av-page-size,
                .av-livewire-root .av-segmented-control {
                    width: 100%;
                }

                .av-export-row {
                    grid-template-columns: 1fr;
                }

                .av-export-actions {
                    justify-content: flex-start;
                }
            }
        </style>
    </div>
</x-filament-panels::page>
