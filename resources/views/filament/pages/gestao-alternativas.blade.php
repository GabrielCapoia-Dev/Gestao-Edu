<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Avaliacoes</p>
                <h1>Gestao de Alternativas</h1>
                <p>Cadastre e organize as alternativas usadas nas pautas sem perder historico.</p>
            </div>

            @can('Criar Alternativas')
                <div class="gi-actions">
                    <button type="button" class="gi-action gi-action--primary" wire:click="abrirModalCriacao">
                        Nova Alternativa
                    </button>
                </div>
            @endcan
        </section>

        <section class="gi-panel">
            <div class="gi-toolbar">
                <div class="gi-toolbar-left">
                    <label class="gi-field">
                        <span>Buscar</span>
                        <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome ou observacao" />
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
                        <strong>{{ $this->alternativas->total() }}</strong>
                    </div>
                </div>
            </div>

            <div class="gi-table-wrap">
                <table class="gi-table">
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Tipo</th>
                            <th>Tem observacao?</th>
                            <th>Observacao</th>
                            <th>Status</th>
                            <th class="text-right">Pautas</th>
                            <th>Atualizada em</th>
                            <th class="text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->alternativas as $alternativa)
                            <tr>
                                <td><strong>{{ $alternativa->nome }}</strong></td>
                                <td>{{ $alternativa->tipo?->nome ?? 'Sem tipo' }}</td>
                                <td>
                                    <span class="av-status {{ $alternativa->tem_observacao ? 'av-status--active' : 'av-status--inactive' }}">
                                        {{ $alternativa->tem_observacao ? 'Sim' : 'Nao' }}
                                    </span>
                                </td>
                                <td>{{ $alternativa->tem_observacao ? ($alternativa->observacao ?: 'Sem observacao padrao') : 'Nao se aplica' }}</td>
                                <td>
                                    <span class="av-status {{ $alternativa->status ? 'av-status--active' : 'av-status--inactive' }}">
                                        {{ $alternativa->status ? 'Ativa' : 'Inativa' }}
                                    </span>
                                </td>
                                <td class="text-right">{{ $alternativa->pautas_count }}</td>
                                <td class="av-no-wrap">{{ optional($alternativa->updated_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="gi-row-actions">
                                        @can('Editar Alternativas')
                                            <button type="button" wire:click="abrirModalEdicao({{ $alternativa->id }})">
                                                Editar
                                            </button>
                                        @endcan

                                        @can('Excluir Alternativas')
                                            <button
                                                type="button"
                                                wire:click="excluirAlternativa({{ $alternativa->id }})"
                                                onclick="return confirm('Deseja realmente excluir esta alternativa?')">
                                                Excluir
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="8" class="gi-empty">Nenhuma alternativa encontrada para os filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @php($paginacao = $this->alternativas)
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
                    <h3>{{ $alternativaIdEditando ? 'Editar Alternativa' : 'Nova Alternativa' }}</h3>
                </div>

                <button type="button" wire:click="fecharModal">
                    Fechar
                </button>
            </header>

            <div class="gi-modal-body">
                <label class="gi-field">
                    <span>Tipo da avaliacao</span>
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
                    <span>Ou cadastre um novo tipo</span>
                    <input type="text" wire:model.defer="form.novo_tipo_nome" maxlength="255" placeholder="Ex.: Parecer Descritivo" />
                    @error('form.novo_tipo_nome')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </label>

                <label class="gi-field">
                    <span>Nome</span>
                    <input type="text" wire:model.defer="form.nome" maxlength="255" />
                    @error('form.nome')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </label>

                <label class="gi-field gi-field--small">
                    <span>Tem observacao?</span>
                    <select wire:model.live="form.tem_observacao">
                        <option value="0">Nao</option>
                        <option value="1">Sim</option>
                    </select>
                    @error('form.tem_observacao')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </label>

                @if ((bool) ($form['tem_observacao'] ?? false))
                    <label class="gi-field">
                        <span>Observacao da alternativa (opcional)</span>
                        <textarea wire:model.defer="form.observacao" rows="4" maxlength="1000"></textarea>
                        @error('form.observacao')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>
                @endif

                <label class="gi-field gi-field--small">
                    <span>Status</span>
                    <select wire:model.defer="form.status">
                        <option value="1">Ativa</option>
                        <option value="0">Inativa</option>
                    </select>
                </label>
            </div>

            <footer>
                <button type="button" class="gi-action" wire:click="fecharModal">
                    Cancelar
                </button>
                <button type="button" class="gi-action gi-action--primary" wire:click="salvarAlternativa">
                    Salvar alternativa
                </button>
            </footer>
        </div>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')
</x-filament-panels::page>
