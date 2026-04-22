<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Avaliacoes</p>
                <h1>Gestao de Pautas</h1>
                <p>Construa perguntas por componente e organize alternativas existentes ou novas em um unico fluxo.</p>
            </div>

            @can('Criar Pautas')
                <div class="gi-actions">
                    <button type="button" class="gi-action gi-action--primary" wire:click="abrirModalCriacao">
                        Nova Pauta
                    </button>
                </div>
            @endcan
        </section>

        <section class="gi-panel">
            <div class="gi-toolbar">
                <div class="gi-toolbar-left">
                    <label class="gi-field">
                        <span>Buscar</span>
                        <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Texto da pauta" />
                    </label>

                    <label class="gi-field gi-field--small">
                        <span>Componente</span>
                        <select wire:model.live="filtroComponente">
                            <option value="">Todos</option>
                            @foreach ($this->componentesOptions as $componenteId => $componenteNome)
                                <option value="{{ $componenteId }}">{{ $componenteNome }}</option>
                            @endforeach
                        </select>
                    </label>

                    <label class="gi-field gi-field--small">
                        <span>Status</span>
                        <select wire:model.live="filtroStatus">
                            <option value="todas">Todas</option>
                            <option value="ativas">Ativas</option>
                            <option value="inativas">Inativas</option>
                        </select>
                    </label>

                    <label class="gi-field gi-field--small">
                        <span>Por pagina</span>
                        <select wire:model.live="porPagina">
                            <option value="5">5</option>
                            <option value="10">10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>
                </div>

                <div class="gi-toolbar-right">
                    <div class="av-total-card">
                        Total:
                        <strong>{{ $this->pautas->total() }}</strong>
                    </div>
                </div>
            </div>

            <div class="gi-table-wrap">
                <table class="gi-table">
                    <thead>
                        <tr>
                            <th>Pauta</th>
                            <th>Tipo</th>
                            <th>Serie</th>
                            <th>Componente</th>
                            <th class="text-right">Alternativas</th>
                            <th class="text-right">Avaliacoes</th>
                            <th>Status</th>
                            <th>Atualizada em</th>
                            <th class="text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->pautas as $pauta)
                            <tr>
                                <td><strong>{{ $pauta->texto }}</strong></td>
                                <td>{{ $pauta->tipo?->nome ?? 'Sem tipo' }}</td>
                                <td>{{ $pauta->serie?->nome ?? 'Sem serie' }}</td>
                                <td>{{ $pauta->componente?->nome ?: 'Geral' }}</td>
                                <td class="text-right">{{ $pauta->alternativas_count }}</td>
                                <td class="text-right">{{ $pauta->avaliacoes_count }}</td>
                                <td>
                                    <span class="av-status {{ $pauta->status ? 'av-status--active' : 'av-status--inactive' }}">
                                        {{ $pauta->status ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="av-no-wrap">{{ optional($pauta->updated_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="gi-row-actions">
                                        @can('Editar Pautas')
                                            <button type="button" wire:click="abrirModalEdicao({{ $pauta->id }})">
                                                Editar
                                            </button>
                                        @endcan

                                        @can('Excluir Pautas')
                                            <button
                                                type="button"
                                                wire:click="excluirPauta({{ $pauta->id }})"
                                                onclick="return confirm('Deseja realmente excluir esta pauta?')">
                                                Excluir
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="9" class="gi-empty">Nenhuma pauta encontrada para os filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @php($paginacao = $this->pautas)
            <div class="gi-pagination">
                <span>
                    Mostrando {{ $paginacao->firstItem() ?? 0 }}-{{ $paginacao->lastItem() ?? 0 }} de {{ $paginacao->total() }}
                </span>

                <div>
                    <button type="button" wire:click="previousPage" @disabled(! $paginacao->onFirstPage())>
                        Anterior
                    </button>
                    <button type="button" wire:click="nextPage" @disabled(! $paginacao->hasMorePages())>
                        Proxima
                    </button>
                </div>
            </div>
        </section>
    </div>

    @if ($modalAberto)
        <div class="gi-overlay" wire:click="fecharModal"></div>

        <div class="gi-modal" role="dialog" aria-modal="true">
            <header>
                <div>
                    <p class="gi-eyebrow">Avaliacoes</p>
                    <h3>{{ $pautaIdEditando ? 'Editar Pauta' : 'Nova Pauta' }}</h3>
                </div>

                <button type="button" wire:click="fecharModal">
                    Fechar
                </button>
            </header>

            <div class="gi-modal-body">
                <div class="av-form-grid av-form-grid--three">
                    <label class="gi-field av-span-2">
                        <span>Texto da pauta</span>
                        <textarea wire:model.defer="form.texto" rows="5" maxlength="2000"></textarea>
                        @error('form.texto')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <div class="av-stack">
                        <label class="gi-field">
                            <span>Tipo</span>
                            <select wire:model.defer="form.tipo_avaliacao_id">
                                <option value="">Selecione um tipo</option>
                                @foreach ($this->tiposOptions as $tipoId => $tipoNome)
                                    <option value="{{ $tipoId }}">{{ $tipoNome }}</option>
                                @endforeach
                            </select>
                            @error('form.tipo_avaliacao_id')
                                <p class="error">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="gi-field">
                            <span>Serie</span>
                            <select wire:model.defer="form.serie_id">
                                <option value="">Selecione uma serie</option>
                                @foreach ($this->seriesOptions as $serieId => $serieNome)
                                    <option value="{{ $serieId }}">{{ $serieNome }}</option>
                                @endforeach
                            </select>
                            @error('form.serie_id')
                                <p class="error">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="gi-field">
                            <span>Componente (opcional)</span>
                            <select wire:model.defer="form.componente_curricular_id">
                                <option value="">Geral (sem componente)</option>
                                @foreach ($this->componentesOptions as $componenteId => $componenteNome)
                                    <option value="{{ $componenteId }}">{{ $componenteNome }}</option>
                                @endforeach
                            </select>
                            @error('form.componente_curricular_id')
                                <p class="error">{{ $message }}</p>
                            @enderror
                        </label>

                        <label class="gi-field">
                            <span>Status</span>
                            <select wire:model.defer="form.status">
                                <option value="1">Ativa</option>
                                <option value="0">Inativa</option>
                            </select>
                        </label>
                    </div>
                </div>

                <section class="av-form-section">
                    <h4>Alternativas existentes</h4>
                    <p>Selecione alternativas existentes do mesmo tipo, se desejar definir alternativas fixas para a pauta.</p>

                    <div class="av-filament-scope-form">
                        {{ $this->alternativasExistentesForm }}
                    </div>
                </section>

                <section class="av-form-section">
                    <div class="gi-toolbar">
                        <div>
                            <h4>Novas alternativas</h4>
                            <p>Crie novas alternativas sem sair desta tela.</p>
                        </div>

                        <div class="gi-toolbar-right">
                            <button type="button" class="gi-action" wire:click="adicionarNovaAlternativa">
                                Adicionar alternativa
                            </button>
                        </div>
                    </div>

                    <div class="av-stack">
                        @forelse ($novasAlternativas as $index => $novaAlternativa)
                            <div class="av-subitem">
                                <label class="gi-field">
                                    <span>Nome</span>
                                    <input type="text" maxlength="255" wire:model.defer="novasAlternativas.{{ $index }}.nome" />
                                </label>

                                <label class="gi-field gi-field--small">
                                    <span>Tem observacao?</span>
                                    <select wire:model.live="novasAlternativas.{{ $index }}.tem_observacao">
                                        <option value="0">Nao</option>
                                        <option value="1">Sim</option>
                                    </select>
                                </label>

                                <label class="gi-field">
                                    <span>Status</span>
                                    <select wire:model.defer="novasAlternativas.{{ $index }}.status">
                                        <option value="1">Ativa</option>
                                        <option value="0">Inativa</option>
                                    </select>
                                </label>

                                @if ((bool) ($novasAlternativas[$index]['tem_observacao'] ?? false))
                                    <label class="gi-field">
                                        <span>Observacao da alternativa (opcional)</span>
                                        <input type="text" maxlength="1000" wire:model.defer="novasAlternativas.{{ $index }}.observacao" />
                                    </label>
                                @endif

                                <div class="gi-row-actions">
                                    <button type="button" wire:click="removerNovaAlternativa({{ $index }})">
                                        Remover
                                    </button>
                                </div>
                            </div>
                        @empty
                            <p class="gi-empty">Nenhuma nova alternativa adicionada.</p>
                        @endforelse
                    </div>
                </section>
            </div>

            <footer>
                <button type="button" class="gi-action" wire:click="fecharModal">
                    Cancelar
                </button>
                <button type="button" class="gi-action gi-action--primary" wire:click="salvarPauta">
                    Salvar pauta
                </button>
            </footer>
        </div>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')
</x-filament-panels::page>
