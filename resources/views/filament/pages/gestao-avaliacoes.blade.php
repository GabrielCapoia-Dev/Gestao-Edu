<x-filament-panels::page>
    <div class="av-livewire-root">
    {{ $this->table }}

    @if ($modalAberto)
        <div class="gi-overlay" wire:click="fecharModal"></div>

        <div class="gi-modal av-modal--wide" role="dialog" aria-modal="true">
            <header>
                <div>
                    <p class="gi-eyebrow">Avaliações</p>
                    <h3>{{ $avaliacaoIdEditando ? 'Editar avaliação' : 'Nova avaliação' }}</h3>
                </div>

                <button type="button" wire:click="fecharModal">
                    Fechar
                </button>
            </header>

            <div class="gi-modal-body">
                <section class="av-form-section av-form-section--plain">
                    <div class="av-section-heading">
                        <span class="av-section-index">1</span>
                        <div>
                            <h4>Dados da avaliação</h4>
                            <p>Defina a identificação, o período e as datas de aplicação e preenchimento.</p>
                        </div>
                    </div>

                <div class="av-form-grid av-form-grid--three">
                    <label class="gi-field av-span-2">
                        <span>Nome da avaliação</span>
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
                        <span>Período</span>
                        <select wire:model.defer="form.periodo_avaliacao_id">
                            <option value="">Selecione um período</option>
                            @foreach ($this->periodosOptions as $periodoId => $periodoNome)
                                <option value="{{ $periodoId }}">{{ $periodoNome }}</option>
                            @endforeach
                        </select>
                        @error('form.periodo_avaliacao_id')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="gi-field">
                        <span>Ou cadastre novo período</span>
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
                        <span>Data início</span>
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

                    <label class="gi-field">
                        <span>Início do preenchimento</span>
                        <input type="date" wire:model.defer="form.data_inicio_preenchimento" />
                        @error('form.data_inicio_preenchimento')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>

                    <label class="gi-field">
                        <span>Fim do preenchimento</span>
                        <input type="date" wire:model.defer="form.data_fim_preenchimento" />
                        @error('form.data_fim_preenchimento')
                            <p class="error">{{ $message }}</p>
                        @enderror
                    </label>
                </div>
                </section>

                <section class="av-form-section">
                    <div class="av-section-heading">
                        <span class="av-section-index">2</span>
                        <div>
                            <h4>Escopo pedagógico</h4>
                            <p>Selecione séries, componentes e escolas. As pautas e turmas serão carregadas automaticamente por esse escopo.</p>
                        </div>
                    </div>

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
                            <small class="av-selection-title">Séries selecionadas</small>
                            <div class="av-chip-grid">
                                @forelse ($seriesSelecionadas as $serieSelecionada)
                                    <span class="av-chip">{{ $serieSelecionada }}</span>
                                @empty
                                    <span class="av-chip av-chip--muted">Nenhuma série selecionada</span>
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

                @php
                    $pautasCarregadas = $this->pautasCarregadas;
                @endphp

                <section class="av-form-section av-alternatives-section">
                    <div class="av-section-heading av-section-heading--between">
                        <div class="av-section-heading-main">
                            <span class="av-section-index">3</span>
                            <div>
                                <h4>Alternativas das pautas</h4>
                                <p>Use o tipo padrão da avaliação ou personalize as alternativas de cada pauta.</p>
                            </div>
                        </div>

                        @if ($pautasCarregadas->isNotEmpty())
                            <span class="av-count-badge">
                                {{ $pautasCarregadas->count() }} {{ $pautasCarregadas->count() === 1 ? 'pauta' : 'pautas' }}
                            </span>
                        @endif
                    </div>

                    @if ($pautasCarregadas->isEmpty())
                        <p class="gi-empty">Nenhuma pauta encontrada para o escopo atual.</p>
                    @else
                        @php
                            $alternativasAtivasAgrupadas = $this->alternativasAtivasAgrupadas;
                        @endphp

                        <div class="av-alternatives-bulk">
                            <div class="av-alternatives-bulk-copy">
                                <strong>Adicionar por tipo em todas as pautas</strong>
                                <small>As alternativas ativas dos tipos selecionados serão adicionadas às pautas carregadas. Você ainda poderá ajustar cada pauta individualmente.</small>
                            </div>

                            <div class="av-alternatives-bulk-actions">
                                <div class="av-type-checkbox-grid" aria-label="Tipos de alternativas">
                                    @foreach ($alternativasAtivasAgrupadas as $tipoAlternativas)
                                        <label class="av-type-checkbox">
                                            <input
                                                type="checkbox"
                                                value="{{ $tipoAlternativas['id'] }}"
                                                wire:model.defer="tiposAlternativasEmMassa"
                                            />
                                            <span>
                                                <strong>{{ $tipoAlternativas['nome'] }}</strong>
                                                <small>{{ count($tipoAlternativas['alternativas']) }} alternativas ativas</small>
                                            </span>
                                        </label>
                                    @endforeach
                                </div>

                                <button
                                    type="button"
                                    class="gi-action gi-action--primary"
                                    wire:click="adicionarTiposAlternativasEmMassa"
                                    wire:loading.attr="disabled"
                                    wire:target="adicionarTiposAlternativasEmMassa"
                                >
                                    Aplicar às pautas
                                </button>
                            </div>

                            @error('tiposAlternativasEmMassa')
                                <p class="error">{{ $message }}</p>
                            @enderror
                        </div>

                        @php
                            $tiposAlternativasOptions = $this->tiposAlternativasOptions;
                        @endphp

                        <div class="av-stack av-alternatives-list">
                            @foreach ($pautasCarregadas as $pauta)
                                @php
                                    $overrideHabilitado = (bool) ($pautasOverrideHabilitado[$pauta->id] ?? false);
                                    $totalAlternativas = count($alternativasOverride[$pauta->id] ?? []);
                                @endphp
                                <article
                                    class="av-alternative-card {{ $overrideHabilitado ? 'is-customized' : '' }}"
                                    wire:key="avaliacao-pauta-{{ $pauta->id }}"
                                >
                                    <div class="av-alternative-card-summary">
                                        <strong>{{ $pauta->texto }}</strong>
                                        <small>
                                            Série: {{ $pauta->serie?->nome ?? 'Sem série' }} |
                                            Componente: {{ $pauta->componente?->nome ?? 'Sem componente' }}
                                        </small>

                                        <span class="av-configuration-status {{ $overrideHabilitado ? 'is-customized' : '' }}">
                                            {{ $overrideHabilitado ? $totalAlternativas . ' alternativas personalizadas' : 'Alternativas do tipo da avaliação' }}
                                        </span>
                                    </div>

                                    <label class="gi-field gi-field--small">
                                        <span>Configuração</span>
                                        <select wire:model.live="pautasOverrideHabilitado.{{ $pauta->id }}">
                                            <option value="0">Usar o tipo da avaliação</option>
                                            <option value="1">Personalizar alternativas</option>
                                        </select>
                                        @error('pautasOverrideHabilitado.' . $pauta->id)
                                            <p class="error">{{ $message }}</p>
                                        @enderror
                                    </label>

                                    @if ($overrideHabilitado)
                                        <div class="av-alternative-card-editor">
                                            <div class="av-add-type-row">
                                                <label class="gi-field gi-field--small">
                                                    <span>Adicionar todas de um tipo</span>
                                                    <select wire:model.defer="tipoAlternativaAdicionar.{{ $pauta->id }}">
                                                        <option value="">Selecione um tipo</option>
                                                        @foreach ($tiposAlternativasOptions as $tipoId => $tipoNome)
                                                            <option value="{{ $tipoId }}">{{ $tipoNome }}</option>
                                                        @endforeach
                                                    </select>
                                                    @error('tipoAlternativaAdicionar.' . $pauta->id)
                                                        <p class="error">{{ $message }}</p>
                                                    @enderror
                                                </label>

                                                <button
                                                    type="button"
                                                    class="gi-action"
                                                    wire:click="adicionarTipoAlternativasNaPauta({{ $pauta->id }})"
                                                    wire:loading.attr="disabled"
                                                    wire:target="adicionarTipoAlternativasNaPauta({{ $pauta->id }})"
                                                >
                                                    Adicionar tipo
                                                </button>
                                            </div>

                                            <div class="av-override-select">
                                                <div class="av-options-heading">
                                                    <strong>Alternativas selecionadas</strong>
                                                    <small>Marque ou desmarque alternativas para ajustar somente esta pauta.</small>
                                                </div>

                                                <div class="av-options-groups">
                                                    @foreach ($alternativasAtivasAgrupadas as $tipoAlternativas)
                                                        <fieldset class="av-options-group">
                                                            <legend>{{ $tipoAlternativas['nome'] }}</legend>
                                                            <div class="av-options-grid">
                                                                @foreach ($tipoAlternativas['alternativas'] as $alternativa)
                                                                    <label class="av-option-checkbox">
                                                                        <input
                                                                            type="checkbox"
                                                                            value="{{ $alternativa['id'] }}"
                                                                            wire:model.defer="alternativasOverride.{{ $pauta->id }}"
                                                                        />
                                                                        <span>
                                                                            {{ $alternativa['nome'] }}
                                                                            @if ($alternativa['tem_observacao'])
                                                                                <small>Exige observação</small>
                                                                            @endif
                                                                        </span>
                                                                    </label>
                                                                @endforeach
                                                            </div>
                                                        </fieldset>
                                                    @endforeach
                                                </div>

                                                @error('alternativasOverride.' . $pauta->id)
                                                    <p class="error">{{ $message }}</p>
                                                @enderror
                                            </div>
                                        </div>
                                    @endif
                                </article>
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
                    Salvar Avaliação
                </button>
            </footer>
        </div>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')
    </div>
</x-filament-panels::page>
