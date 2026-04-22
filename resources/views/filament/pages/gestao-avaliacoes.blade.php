<x-filament-panels::page>
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Avaliacoes</p>
                <h1>Gestao de Avaliacoes</h1>
                <p>Monte ciclos avaliativos com periodo, status e pautas obrigatorias por componente.</p>
            </div>

            @can('Criar Avaliações')
                <div class="gi-actions">
                    <button type="button" class="gi-action gi-action--primary" wire:click="abrirModalCriacao">
                        Nova Avaliacao
                    </button>
                </div>
            @endcan
        </section>

        <section class="gi-panel">
            <div class="gi-toolbar">
                <div class="gi-toolbar-left">
                    <label class="gi-field">
                        <span>Buscar</span>
                        <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome da avaliacao" />
                    </label>

                    <label class="gi-field gi-field--small">
                        <span>Status</span>
                        <select wire:model.live="filtroStatus">
                            <option value="todas">Todos</option>
                            @foreach ($this->statusOptions as $statusValue => $statusLabel)
                                <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                            @endforeach
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
                        <strong>{{ $this->avaliacoes->total() }}</strong>
                    </div>
                </div>
            </div>

            <div class="gi-table-wrap">
                <table class="gi-table">
                    <thead>
                        <tr>
                            <th>Avaliacao</th>
                            <th>Periodo</th>
                            <th class="text-right">Pautas</th>
                            <th>Componentes</th>
                            <th>Status</th>
                            <th>Atualizada em</th>
                            <th class="text-right">Acoes</th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse ($this->avaliacoes as $avaliacao)
                            @php
                                $statusClass = match ($avaliacao->status) {
                                    'ativa' => 'av-status--active',
                                    'inativa' => 'av-status--inactive',
                                    'encerrada' => 'av-status--closed',
                                    'cancelada' => 'av-status--canceled',
                                    default => 'av-status--inactive',
                                };
                            @endphp
                            <tr>
                                <td>
                                    <strong>{{ $avaliacao->nome }}</strong>
                                </td>
                                <td class="av-no-wrap">
                                    {{ optional($avaliacao->data_inicio)->format('d/m/Y') }}
                                    ate
                                    {{ optional($avaliacao->data_fim)->format('d/m/Y') }}
                                </td>
                                <td class="text-right">{{ $avaliacao->pautas_count }}</td>
                                <td>
                                    @php
                                        $componentes = $avaliacao->pautas
                                            ->map(fn ($pauta) => $pauta->componente?->nome)
                                            ->filter()
                                            ->unique()
                                            ->values();
                                    @endphp
                                    <div class="av-chip-grid">
                                        @forelse ($componentes as $componenteNome)
                                            <span class="av-chip">{{ $componenteNome }}</span>
                                        @empty
                                            <span class="av-chip av-chip--muted">Geral</span>
                                        @endforelse
                                    </div>
                                </td>
                                <td>
                                    <span class="av-status {{ $statusClass }}">
                                        {{ \App\Models\Avaliacao::statusOptions()[$avaliacao->status] ?? $avaliacao->status }}
                                    </span>
                                </td>
                                <td class="av-no-wrap">{{ optional($avaliacao->updated_at)->format('d/m/Y H:i') }}</td>
                                <td>
                                    <div class="gi-row-actions">
                                        @can('Editar Avaliações')
                                            <button type="button" wire:click="abrirModalEdicao({{ $avaliacao->id }})">
                                                Editar
                                            </button>
                                        @endcan

                                        @can('Excluir Avaliações')
                                            <button
                                                type="button"
                                                wire:click="excluirAvaliacao({{ $avaliacao->id }})"
                                                onclick="return confirm('Deseja realmente excluir esta avaliacao?')">
                                                Excluir
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="gi-empty">Nenhuma avaliacao encontrada para os filtros aplicados.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @php
                $paginacao = $this->avaliacoes;
            @endphp
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

        <div class="gi-modal av-modal--wide" role="dialog" aria-modal="true">
            <header>
                <div>
                    <p class="gi-eyebrow">Avaliacoes</p>
                    <h3>{{ $avaliacaoIdEditando ? 'Editar Avaliacao' : 'Nova Avaliacao' }}</h3>
                </div>

                <button type="button" wire:click="fecharModal">
                    Fechar
                </button>
            </header>

            <div class="gi-modal-body">
                <div class="av-form-grid">
                    <label class="gi-field av-span-2">
                        <span>Nome da avaliacao</span>
                        <input type="text" wire:model.defer="form.nome" maxlength="255" />
                        @error('form.nome')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="gi-field">
                        <span>Data inicio</span>
                        <input type="date" wire:model.defer="form.data_inicio" />
                        @error('form.data_inicio')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="gi-field">
                        <span>Data fim</span>
                        <input type="date" wire:model.defer="form.data_fim" />
                        @error('form.data_fim')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <label class="gi-field gi-field--small">
                    <span>Status</span>
                    <select wire:model.defer="form.status">
                        @foreach ($this->statusOptions as $statusValue => $statusLabel)
                            <option value="{{ $statusValue }}">{{ $statusLabel }}</option>
                        @endforeach
                    </select>
                    @error('form.status')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </label>

                <section class="av-form-section">
                    <h4>Escopo pedagogico</h4>
                    <p>Selecione escola, componentes e pautas. As turmas serao vinculadas automaticamente pelos componentes escolhidos.</p>

                    <div class="av-filament-scope-form">
                        {{ $this->escopoForm }}
                    </div>

                    @php
                        $componentesSelecionados = collect($form['componentes_ids'] ?? [])
                            ->map(fn ($id) => $this->componentesOptions[(int) $id] ?? null)
                            ->filter()
                            ->values();
                        $pautasSelecionadas = collect($form['pautas_ids'] ?? [])
                            ->map(fn ($id) => $this->pautasOptions[(int) $id] ?? null)
                            ->filter()
                            ->values();
                    @endphp

                    <div class="av-selection-grid">
                        <div>
                            <small class="av-selection-title">Componentes selecionados</small>
                            <div class="av-chip-grid">
                                @forelse ($componentesSelecionados as $componenteSelecionado)
                                    <span class="av-chip">{{ $componenteSelecionado }}</span>
                                @empty
                                    <span class="av-chip av-chip--muted">Nenhum componente selecionado</span>
                                @endforelse
                            </div>
                        </div>

                        <div>
                            <small class="av-selection-title">Pautas selecionadas</small>
                            <div class="av-chip-grid">
                                @forelse ($pautasSelecionadas as $pautaSelecionada)
                                    <span class="av-chip">{{ $pautaSelecionada }}</span>
                                @empty
                                    <span class="av-chip av-chip--muted">Nenhuma pauta selecionada</span>
                                @endforelse
                            </div>
                        </div>
                    </div>
                </section>
            </div>

            <footer>
                <button type="button" class="gi-action" wire:click="fecharModal">
                    Cancelar
                </button>
                <button type="button" class="gi-action gi-action--primary" wire:click="salvarAvaliacao">
                    Salvar Avaliacao
                </button>
            </footer>
        </div>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')
</x-filament-panels::page>
