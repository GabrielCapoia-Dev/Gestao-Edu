<x-filament-panels::page>
    <div class="av-livewire-root">
    {{ $this->table }}

    {{--
    <div class="gi-page av-page">
        <section class="gi-hero">
            <div>
                <p class="gi-eyebrow">Avaliacoes</p>
                <h1>Gestao de Avaliacoes</h1>
                <p>Monte avaliacoes por tipo, periodo e escopo pedagogico (serie, componente e escola).</p>
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
                        <input type="search" wire:model.live.debounce.300ms="busca" placeholder="Nome, tipo, periodo ou componente" />
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
                            <th>Tipo</th>
                            <th>Periodo</th>
                            <th>Escopo</th>
                            <th class="text-right">Pautas</th>
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
                                    <small>
                                        {{ optional($avaliacao->data_inicio)->format('d/m/Y') }}
                                        ate
                                        {{ optional($avaliacao->data_fim)->format('d/m/Y') }}
                                    </small>
                                </td>
                                <td>{{ $avaliacao->tipo?->nome ?? 'Sem tipo' }}</td>
                                <td>{{ $avaliacao->periodo?->nome ?? 'Sem periodo' }}</td>
                                <td>
                                    <div class="av-chip-grid">
                                        @forelse ($avaliacao->series as $serie)
                                            <span class="av-chip">{{ $serie->nome }}</span>
                                        @empty
                                            <span class="av-chip av-chip--muted">Sem series</span>
                                        @endforelse
                                    </div>
                                    <div class="av-chip-grid" style="margin-top: .35rem;">
                                        @forelse ($avaliacao->componentes as $componente)
                                            <span class="av-chip">{{ $componente->nome }}</span>
                                        @empty
                                            <span class="av-chip av-chip--muted">Sem componentes</span>
                                        @endforelse
                                    </div>
                                    <small>{{ $avaliacao->escolas->count() }} escola(s)</small>
                                </td>
                                <td class="text-right">{{ $avaliacao->pautas_count }}</td>
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
                                <td colspan="8" class="gi-empty">Nenhuma avaliacao encontrada para os filtros aplicados.</td>
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
    --}}

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
                <div class="av-form-grid av-form-grid--three">
                    <label class="gi-field av-span-2">
                        <span>Nome da avaliacao</span>
                        <input type="text" wire:model.defer="form.nome" maxlength="255" />
                        @error('form.nome')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="gi-field">
                        <span>Tipo</span>
                        <select wire:model.live="form.tipo_avaliacao_id">
                            <option value="">Selecione um tipo</option>
                            @foreach ($this->tiposOptions as $tipoId => $tipoNome)
                                <option value="{{ $tipoId }}">{{ $tipoNome }}</option>
                            @endforeach
                        </select>
                        @error('form.tipo_avaliacao_id')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>
                </div>

                <div class="av-form-grid av-form-grid--three">
                    <label class="gi-field">
                        <span>Periodo</span>
                        <select wire:model.defer="form.periodo_avaliacao_id">
                            <option value="">Selecione um periodo</option>
                            @foreach ($this->periodosOptions as $periodoId => $periodoNome)
                                <option value="{{ $periodoId }}">{{ $periodoNome }}</option>
                            @endforeach
                        </select>
                        @error('form.periodo_avaliacao_id')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="gi-field">
                        <span>Ou cadastre novo periodo</span>
                        <input type="text" wire:model.defer="form.novo_periodo_nome" maxlength="255" placeholder="Ex.: 1º Semestre" />
                        @error('form.novo_periodo_nome')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

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
                </div>

                <div class="av-form-grid av-form-grid--three">
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

                <section class="av-form-section">
                    <h4>Escopo pedagogico</h4>
                    <p>Selecione series, componentes e escolas. As pautas e turmas serao carregadas automaticamente por esse escopo.</p>

                    <div class="av-filament-scope-form">
                        {{ $this->escopoForm }}
                    </div>

                    @php
                        $seriesSelecionadas = collect($form['series_ids'] ?? [])
                            ->map(fn ($id) => $this->seriesOptions[(int) $id] ?? null)
                            ->filter()
                            ->values();
                        $componentesSelecionados = collect($form['componentes_ids'] ?? [])
                            ->map(fn ($id) => $this->componentesOptions[(int) $id] ?? null)
                            ->filter()
                            ->values();
                        $escolasSelecionadas = collect($form['escolas_ids'] ?? [])
                            ->map(function ($id) {
                                $key = (string) $id;
                                return $this->escolasOptions[$key] ?? null;
                            })
                            ->filter()
                            ->values();
                    @endphp

                    <div class="av-selection-grid">
                        <div>
                            <small class="av-selection-title">Series selecionadas</small>
                            <div class="av-chip-grid">
                                @forelse ($seriesSelecionadas as $serieSelecionada)
                                    <span class="av-chip">{{ $serieSelecionada }}</span>
                                @empty
                                    <span class="av-chip av-chip--muted">Nenhuma serie selecionada</span>
                                @endforelse
                            </div>
                        </div>

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
                            <small class="av-selection-title">Escolas selecionadas</small>
                            <div class="av-chip-grid">
                                @forelse ($escolasSelecionadas as $escolaSelecionada)
                                    <span class="av-chip">{{ $escolaSelecionada }}</span>
                                @empty
                                    <span class="av-chip av-chip--muted">Nenhuma escola selecionada</span>
                                @endforelse
                            </div>
                        </div>
                    </div>

                    @error('form.series_ids')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    @error('form.componentes_ids')
                        <p class="error">{{ $message }}</p>
                    @enderror
                    @error('form.escolas_ids')
                        <p class="error">{{ $message }}</p>
                    @enderror
                </section>

                <section class="av-form-section">
                    <h4>Pautas carregadas automaticamente</h4>
                    <p>As pautas abaixo foram carregadas por tipo + serie + componente. Você pode substituir as alternativas por pauta apenas nesta avaliacao.</p>

                    @if ($this->pautasCarregadas->isEmpty())
                        <p class="gi-empty">Nenhuma pauta encontrada para o escopo atual.</p>
                    @else
                        <div class="av-stack">
                            @foreach ($this->pautasCarregadas as $pauta)
                                @php($overrideHabilitado = (bool) ($form['pautas_override_habilitado'][$pauta->id] ?? false))
                                <div class="av-subitem">
                                    <div>
                                        <strong>{{ $pauta->texto }}</strong>
                                        <small>
                                            Serie: {{ $pauta->serie?->nome ?? 'Sem serie' }} |
                                            Componente: {{ $pauta->componente?->nome ?? 'Sem componente' }}
                                        </small>
                                    </div>

                                    <label class="gi-field gi-field--small">
                                        <span>Alternativas nesta pauta</span>
                                        <select wire:model.live="form.pautas_override_habilitado.{{ $pauta->id }}">
                                            <option value="0">Usar alternativas do tipo</option>
                                            <option value="1">Substituir nessa avaliacao</option>
                                        </select>
                                        @error('form.pautas_override_habilitado.' . $pauta->id)
                                            <p class="error">{{ $message }}</p>
                                        @enderror
                                    </label>

                                    @if ($overrideHabilitado)
                                        <div class="av-filament-scope-form av-override-select">
                                            {{ $this->getSchemaComponent('alternativasOverrideForm.' . $this->alternativasOverrideComponentKey((int) $pauta->id)) }}
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
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
    </div>
</x-filament-panels::page>
