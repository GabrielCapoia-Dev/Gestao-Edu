<x-filament-panels::page>
    <div class="av-livewire-root">
    {{ $this->table }}

    @if ($modalAberto)
        <div class="gi-overlay" wire:click="fecharModal"></div>

        <div class="gi-modal" role="dialog" aria-modal="true">
            <header>
                <div>
                    <p class="gi-eyebrow">Avaliações</p>
                    <h3>{{ $pautaIdEditando ? 'Editar Pauta' : 'Novas Pautas' }}</h3>
                </div>

                <button type="button" wire:click="fecharModal">
                    Fechar
                </button>
            </header>

            @php($abaPautas = $modalAbaPautas ?? 'configuracao')

            <div class="av-modal-step-tabs" role="tablist">
                <button
                    type="button"
                    class="{{ $abaPautas === 'configuracao' ? 'is-active' : '' }}"
                    wire:click="abrirAbaPautas('configuracao')"
                    role="tab"
                    aria-selected="{{ $abaPautas === 'configuracao' ? 'true' : 'false' }}"
                >
                    <span class="av-tab-index">01</span>
                    <span>Configuração da pauta</span>
                </button>

                <button
                    type="button"
                    class="{{ $abaPautas === 'textos' ? 'is-active' : '' }}"
                    wire:click="abrirAbaPautas('textos')"
                    role="tab"
                    aria-selected="{{ $abaPautas === 'textos' ? 'true' : 'false' }}"
                >
                    <span class="av-tab-index">02</span>
                    <span>Textos das pautas</span>
                </button>
            </div>

            <div class="gi-modal-body">
                @if ($abaPautas === 'configuracao')
                    <div class="av-stack">
                        <section class="av-form-section av-form-section--plain">
                            <h4>Configuração da pauta</h4>

                            <div class="av-form-grid av-form-grid--two">
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
                                    <span>Série</span>
                                    <select wire:model.defer="form.serie_id">
                                        <option value="">Selecione uma série</option>
                                        @foreach ($this->seriesOptions as $serieId => $serieNome)
                                            <option value="{{ $serieId }}">{{ $serieNome }}</option>
                                        @endforeach
                                    </select>
                                    @error('form.serie_id')
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
                        </section>

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
                                            <span>Tem observação?</span>
                                            <select wire:model.live="novasAlternativas.{{ $index }}.tem_observacao">
                                                <option value="0">Não</option>
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
                                                <span>Observação da alternativa (opcional)</span>
                                                <input type="text" maxlength="1000" wire:model.defer="novasAlternativas.{{ $index }}.observacao" />
                                            </label>
                                        @endif

                                        <label class="gi-field gi-field--small">
                                            <span>Vai no documento?</span>
                                            <select wire:model.live="novasAlternativas.{{ $index }}.vai_no_documento">
                                                <option value="1">Sim</option>
                                                <option value="0">Não</option>
                                            </select>
                                        </label>

                                        @if ((bool) ($novasAlternativas[$index]['vai_no_documento'] ?? true))
                                            <label class="gi-field">
                                                <span>Descrição no documento (opcional)</span>
                                                <input type="text" maxlength="1000" wire:model.defer="novasAlternativas.{{ $index }}.descricao_documento" />
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
                @else
                    <section class="av-form-section av-form-section--plain">
                        <div class="gi-toolbar">
                            <div>
                                <h4>Textos das pautas</h4>
                            </div>

                            @if (! $pautaIdEditando)
                                <div class="gi-toolbar-right">
                                    <button type="button" class="gi-action" wire:click="adicionarTextoPauta">
                                        Adicionar texto
                                    </button>
                                </div>
                            @endif
                        </div>

                        <div class="av-stack">
                            @foreach (($form['textos'] ?? [['texto' => '']]) as $index => $textoPauta)
                                <div class="av-repeater-item" wire:key="texto-pauta-{{ $index }}">
                                    <label class="gi-field">
                                        <span>Texto {{ $index + 1 }}</span>
                                        <textarea wire:model.defer="form.textos.{{ $index }}.texto" rows="4" maxlength="2000"></textarea>
                                        @error("form.textos.{$index}.texto")
                                            <p class="error">{{ $message }}</p>
                                        @enderror
                                    </label>

                                    @if (! $pautaIdEditando && count($form['textos'] ?? []) > 1)
                                        <div class="gi-row-actions">
                                            <button type="button" wire:click="removerTextoPauta({{ $index }})">
                                                Remover
                                            </button>
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </section>
                @endif
            </div>

            <footer>
                <button type="button" class="gi-action" wire:click="fecharModal">
                    Cancelar
                </button>

                @if ($abaPautas === 'configuracao')
                    <button type="button" class="gi-action gi-action--primary" wire:click="avancarParaTextosPautas">
                        Próximo
                    </button>
                @else
                    <button type="button" class="gi-action" wire:click="voltarParaConfiguracaoPautas">
                        Voltar
                    </button>
                    <button type="button" class="gi-action gi-action--primary" wire:click="salvarPauta">
                        {{ $pautaIdEditando ? 'Salvar pauta' : 'Salvar pautas' }}
                    </button>
                @endif
            </footer>
        </div>
    @endif

    @include('filament.pages.partials.avaliacoes-page-styles')
    </div>
</x-filament-panels::page>
