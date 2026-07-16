<section class="pe-person-form__section" aria-labelledby="pessoa-form-matriculas-title">
    <header class="pe-person-form__section-header pe-person-form__section-header--actions">
        <div class="pe-person-form__section-title">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-academic-cap" />
            </span>
            <div>
                <h3 id="pessoa-form-matriculas-title">Matrículas e lotações</h3>
                <p>Cada aba representa uma matrícula atual. Exclusões só são persistidas ao salvar.</p>
            </div>
        </div>

        @if ($modoCriacao || $gerenciaEstrutura)
            <button
                type="button"
                class="pe-person-form__button pe-person-form__button--secondary"
                wire:click="adicionarMatricula"
                wire:loading.attr="disabled"
                wire:target="adicionarMatricula,removerMatricula,salvar"
                @disabled(count($matriculas) >= 2)
            >
                <x-heroicon-o-plus aria-hidden="true" />
                Matrícula
            </button>
        @endif
    </header>

    @if ($matriculas === [])
        <div class="pe-person-form__empty" role="status">
            Nenhuma matrícula desta unidade está disponível para edição.
        </div>
    @else
        <div class="pe-person-form__tab-strip" role="tablist" aria-label="Matrículas">
            @foreach ($matriculas as $matriculaKey => $matricula)
                <div
                    class="pe-person-form__tab {{ $matriculaAtiva === $matriculaKey ? 'is-active' : '' }}"
                    wire:key="matricula-tab-{{ $matriculaKey }}"
                >
                    <button
                        id="pessoa-form-matricula-tab-{{ $matriculaKey }}"
                        type="button"
                        role="tab"
                        aria-selected="{{ $matriculaAtiva === $matriculaKey ? 'true' : 'false' }}"
                        aria-controls="pessoa-form-matricula-panel-{{ $matriculaKey }}"
                        tabindex="{{ $matriculaAtiva === $matriculaKey ? '0' : '-1' }}"
                        wire:click="selecionarMatricula(@js($matriculaKey))"
                    >
                        {{ $matriculaLabels[$matriculaKey] }}
                    </button>
                    @if ($modoCriacao || $gerenciaEstrutura)
                        <button
                            type="button"
                            class="pe-person-form__icon-button"
                            title="Excluir matrícula"
                            aria-label="Excluir matrícula {{ $matriculaLabels[$matriculaKey] }}"
                            wire:click="removerMatricula(@js($matriculaKey))"
                            wire:confirm="Remover esta matrícula e encerrar suas lotações ao salvar?"
                            wire:loading.attr="disabled"
                            wire:target="removerMatricula,salvar"
                        >
                            <x-heroicon-o-trash aria-hidden="true" />
                        </button>
                    @endif
                </div>
            @endforeach
        </div>

        @error('matriculas')
            <p class="pe-person-form__error-block" role="alert">{{ $message }}</p>
        @enderror

        @foreach ($matriculas as $matriculaKey => $matricula)
            <div
                id="pessoa-form-matricula-panel-{{ $matriculaKey }}"
                class="pe-person-form__registration"
                role="tabpanel"
                aria-labelledby="pessoa-form-matricula-tab-{{ $matriculaKey }}"
                aria-hidden="{{ $matriculaAtiva === $matriculaKey ? 'false' : 'true' }}"
                wire:key="matricula-content-{{ $matriculaKey }}"
                @if ($matriculaAtiva !== $matriculaKey) hidden @endif
            >
                    <div class="pe-person-form__grid pe-person-form__grid--2 pe-person-form__grid--flush">
                        <label class="pe-person-form__field">
                            <span>Nº da matrícula <b aria-hidden="true">*</b></span>
                            <input
                                id="pessoa-form-matricula-{{ $matriculaKey }}"
                                type="text"
                                wire:model.blur="matriculas.{{ $matriculaKey }}.matricula"
                                maxlength="255"
                                inputmode="numeric"
                                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                                @error("matriculas.$matriculaKey.matricula") aria-invalid="true" aria-describedby="pessoa-form-matricula-{{ $matriculaKey }}-error" @enderror
                            >
                            @error("matriculas.$matriculaKey.matricula")
                                <small id="pessoa-form-matricula-{{ $matriculaKey }}-error" class="is-error">{{ $message }}</small>
                            @enderror
                        </label>

                        <label class="pe-person-form__field">
                            <span>Turno <b aria-hidden="true">*</b></span>
                            <select
                                id="pessoa-form-turno-{{ $matriculaKey }}"
                                wire:change="turnoAlterado(@js($matriculaKey), $event.target.value)"
                                wire:loading.attr="disabled"
                                wire:target="turnoAlterado,salvar"
                                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                                @error("matriculas.$matriculaKey.turno") aria-invalid="true" aria-describedby="pessoa-form-turno-{{ $matriculaKey }}-error" @enderror
                            >
                                <option value="">Selecione</option>
                                @foreach (($turnosOptionsPorMatricula[$matriculaKey] ?? $turnosOptions) as $value => $label)
                                    <option value="{{ $value }}" @selected(($matricula['turno'] ?? '') === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @error("matriculas.$matriculaKey.turno")
                                <small id="pessoa-form-turno-{{ $matriculaKey }}-error" class="is-error">{{ $message }}</small>
                            @enderror
                        </label>
                    </div>

                    @if ($cargo === 'professor')
                        <div class="pe-person-form__subheader">
                            <div>
                                <h4>Escolas / lotações do Professor</h4>
                                <p>Turmas e componentes são organizados dentro de cada unidade.</p>
                            </div>
                            @if ($modoCriacao || $gerenciaEstrutura)
                                <button
                                    type="button"
                                    class="pe-person-form__button pe-person-form__button--secondary"
                                    wire:click="adicionarLotacao(@js($matriculaKey))"
                                    wire:loading.attr="disabled"
                                    wire:target="adicionarLotacao,removerLotacao,salvar"
                                >
                                    <x-heroicon-o-plus aria-hidden="true" />
                                    Escola
                                </button>
                            @endif
                        </div>

                        @if (($matricula['escolas'] ?? []) === [])
                            <div class="pe-person-form__empty pe-person-form__empty--small" role="status">
                                Esta matrícula ainda não possui lotação escolar.
                            </div>
                        @else
                            <div
                                class="pe-person-form__tab-strip pe-person-form__tab-strip--schools"
                                role="tablist"
                                aria-label="Escolas da matrícula"
                            >
                                @foreach (($matricula['escolas'] ?? []) as $lotacaoKey => $lotacao)
                                    <div
                                        class="pe-person-form__tab {{ ($lotacoesAtivas[$matriculaKey] ?? null) === $lotacaoKey ? 'is-active' : '' }}"
                                        wire:key="lotacao-tab-{{ $matriculaKey }}-{{ $lotacaoKey }}"
                                    >
                                        <button
                                            id="pessoa-form-lotacao-tab-{{ $matriculaKey }}-{{ $lotacaoKey }}"
                                            type="button"
                                            role="tab"
                                            aria-selected="{{ ($lotacoesAtivas[$matriculaKey] ?? null) === $lotacaoKey ? 'true' : 'false' }}"
                                            aria-controls="pessoa-form-lotacao-panel-{{ $matriculaKey }}-{{ $lotacaoKey }}"
                                            tabindex="{{ ($lotacoesAtivas[$matriculaKey] ?? null) === $lotacaoKey ? '0' : '-1' }}"
                                            wire:click="selecionarLotacao(@js($matriculaKey), @js($lotacaoKey))"
                                        >
                                            {{ $lotacaoLabels[$matriculaKey][$lotacaoKey] }}
                                        </button>
                                        @if ($modoCriacao || $gerenciaEstrutura)
                                            <button
                                                type="button"
                                                class="pe-person-form__icon-button"
                                                title="Excluir lotação"
                                                aria-label="Excluir lotação {{ $lotacaoLabels[$matriculaKey][$lotacaoKey] }}"
                                                wire:click="removerLotacao(@js($matriculaKey), @js($lotacaoKey))"
                                                wire:confirm="Remover esta lotação e seus vínculos ao salvar?"
                                                wire:loading.attr="disabled"
                                                wire:target="removerLotacao,salvar"
                                            >
                                                <x-heroicon-o-trash aria-hidden="true" />
                                            </button>
                                        @endif
                                    </div>
                                @endforeach
                            </div>

                            @foreach (($matricula['escolas'] ?? []) as $lotacaoKey => $lotacao)
                                @if (($lotacoesAtivas[$matriculaKey] ?? null) === $lotacaoKey)
                                    <div
                                        id="pessoa-form-lotacao-panel-{{ $matriculaKey }}-{{ $lotacaoKey }}"
                                        class="pe-person-form__placement"
                                        role="tabpanel"
                                        aria-labelledby="pessoa-form-lotacao-tab-{{ $matriculaKey }}-{{ $lotacaoKey }}"
                                        wire:key="lotacao-content-{{ $matriculaKey }}-{{ $lotacaoKey }}"
                                    >
                                        <label class="pe-person-form__field">
                                            <span>Escola / CMEI <b aria-hidden="true">*</b></span>
                                            <select
                                                wire:change="escolaAlterada(@js($matriculaKey), @js($lotacaoKey), $event.target.value)"
                                                wire:loading.attr="disabled"
                                                wire:target="escolaAlterada,salvar"
                                                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                                                @error("matriculas.$matriculaKey.escolas.$lotacaoKey.id_escola") aria-invalid="true" aria-describedby="pessoa-form-escola-{{ $matriculaKey }}-{{ $lotacaoKey }}-error" @enderror
                                            >
                                                <option value="">Selecione uma escola</option>
                                                @foreach ($escolasOptions as $value => $label)
                                                    <option value="{{ $value }}" @selected((int) ($lotacao['id_escola'] ?? 0) === (int) $value)>{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            @error("matriculas.$matriculaKey.escolas.$lotacaoKey.id_escola")
                                                <small id="pessoa-form-escola-{{ $matriculaKey }}-{{ $lotacaoKey }}-error" class="is-error">{{ $message }}</small>
                                            @enderror
                                        </label>

                                        <div class="pe-person-form__subheader pe-person-form__subheader--assignments">
                                            <div>
                                                <h4>Turmas e componentes</h4>
                                                <p>Vínculos pedagógicos desta escola.</p>
                                            </div>
                                            @if ($modoCriacao || $podeEditarTurmas)
                                                <button
                                                    type="button"
                                                    class="pe-person-form__button pe-person-form__button--secondary"
                                                    wire:click="adicionarVinculo(@js($matriculaKey), @js($lotacaoKey))"
                                                    wire:loading.attr="disabled"
                                                    wire:target="adicionarVinculo,removerVinculo,salvar"
                                                >
                                                    <x-heroicon-o-plus aria-hidden="true" />
                                                    Turma
                                                </button>
                                            @endif
                                        </div>

                                        <div class="pe-person-form__assignments">
                                            @forelse (($lotacao['vinculos_turma_componente'] ?? []) as $vinculoKey => $vinculo)
                                                <article
                                                    class="pe-person-form__assignment"
                                                    wire:key="vinculo-{{ $matriculaKey }}-{{ $lotacaoKey }}-{{ $vinculoKey }}"
                                                >
                                                    <label class="pe-person-form__field">
                                                        <span>Turma <b aria-hidden="true">*</b></span>
                                                        <select
                                                            wire:change="turmaAlterada(@js($matriculaKey), @js($lotacaoKey), @js($vinculoKey), $event.target.value)"
                                                            wire:loading.attr="disabled"
                                                            wire:target="turmaAlterada,salvar"
                                                            @disabled(! $modoCriacao && ! $podeEditarTurmas)
                                                            @error("matriculas.$matriculaKey.escolas.$lotacaoKey.vinculos_turma_componente.$vinculoKey.turma_id") aria-invalid="true" aria-describedby="pessoa-form-turma-{{ $vinculoKey }}-error" @enderror
                                                        >
                                                            <option value="">Selecione uma turma</option>
                                                            @foreach (($turmasOptions[$matriculaKey][$lotacaoKey] ?? []) as $value => $label)
                                                                <option value="{{ $value }}" @selected((int) ($vinculo['turma_id'] ?? 0) === (int) $value)>{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error("matriculas.$matriculaKey.escolas.$lotacaoKey.vinculos_turma_componente.$vinculoKey.turma_id")
                                                            <small id="pessoa-form-turma-{{ $vinculoKey }}-error" class="is-error">{{ $message }}</small>
                                                        @enderror
                                                    </label>

                                                    <label class="pe-person-form__field">
                                                        <span>Componente <b aria-hidden="true">*</b></span>
                                                        <select
                                                            wire:model="matriculas.{{ $matriculaKey }}.escolas.{{ $lotacaoKey }}.vinculos_turma_componente.{{ $vinculoKey }}.componente_curricular_id"
                                                            @disabled(! $modoCriacao && ! $podeEditarTurmas)
                                                            @error("matriculas.$matriculaKey.escolas.$lotacaoKey.vinculos_turma_componente.$vinculoKey.componente_curricular_id") aria-invalid="true" aria-describedby="pessoa-form-componente-{{ $vinculoKey }}-error" @enderror
                                                        >
                                                            <option value="">Selecione um componente</option>
                                                            @foreach (($componentesOptions[$matriculaKey][$lotacaoKey][$vinculoKey] ?? []) as $value => $label)
                                                                <option value="{{ $value }}">{{ $label }}</option>
                                                            @endforeach
                                                        </select>
                                                        @error("matriculas.$matriculaKey.escolas.$lotacaoKey.vinculos_turma_componente.$vinculoKey.componente_curricular_id")
                                                            <small id="pessoa-form-componente-{{ $vinculoKey }}-error" class="is-error">{{ $message }}</small>
                                                        @enderror
                                                    </label>

                                                    @if ($modoCriacao || $podeEditarTurmas)
                                                        <button
                                                            type="button"
                                                            class="pe-person-form__remove-assignment"
                                                            wire:click="removerVinculo(@js($matriculaKey), @js($lotacaoKey), @js($vinculoKey))"
                                                            wire:loading.attr="disabled"
                                                            wire:target="removerVinculo,salvar"
                                                            aria-label="Remover vínculo de turma e componente"
                                                            title="Remover vínculo"
                                                        >
                                                            <x-heroicon-o-trash aria-hidden="true" />
                                                        </button>
                                                    @endif
                                                </article>
                                            @empty
                                                <div class="pe-person-form__empty pe-person-form__empty--small" role="status">
                                                    Nenhuma turma ou componente vinculado.
                                                </div>
                                            @endforelse
                                        </div>
                                    </div>
                                @endif
                            @endforeach
                        @endif
                    @endif
            </div>
        @endforeach
    @endif
</section>
