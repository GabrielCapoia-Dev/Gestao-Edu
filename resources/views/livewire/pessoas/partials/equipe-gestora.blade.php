@if ($cargo === 'equipe_gestora' && $podeGerenciarEquipeGestora)
    @php
        $idsTurmasGestaoSelecionadas = collect($turmaIds)
            ->filter(fn (mixed $id): bool => filled($id))
            ->map(fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $turmasGestaoSelecionadas = collect($turmasGestaoOptions)
            ->filter(fn (string $label, int|string $id): bool => in_array((int) $id, $idsTurmasGestaoSelecionadas, true));
        $turmasGestaoDisponiveis = collect($turmasGestaoOptions)
            ->reject(fn (string $label, int|string $id): bool => in_array((int) $id, $idsTurmasGestaoSelecionadas, true));
    @endphp

    <section class="pe-person-form__section" aria-labelledby="pessoa-form-gestao-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-building-office-2" />
            </span>
            <div>
                <h3 id="pessoa-form-gestao-title">Equipe Gestora</h3>
                <p>Escola, cargos, portaria e turmas da coordenação.</p>
            </div>
        </header>

        <div class="pe-person-form__grid pe-person-form__grid--2">
            <label class="pe-person-form__field pe-person-form__span-2">
                <span>Escola / CMEI <b aria-hidden="true">*</b></span>
                <select
                    wire:change="escolaGestoraAlterada($event.target.value)"
                    wire:loading.attr="disabled"
                    wire:target="escolaGestoraAlterada,salvar"
                    @error('idEscolaGestora') aria-invalid="true" aria-describedby="pessoa-form-escola-gestora-error" @enderror
                >
                    <option value="">Selecione uma escola</option>
                    @foreach ($escolasOptions as $value => $label)
                        <option value="{{ $value }}" @selected((int) $idEscolaGestora === (int) $value)>{{ $label }}</option>
                    @endforeach
                </select>
                @error('idEscolaGestora')
                    <small id="pessoa-form-escola-gestora-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>

            <fieldset class="pe-person-form__field pe-person-form__span-2">
                <legend>Cargos gestores <b aria-hidden="true">*</b></legend>
                <div class="pe-person-form__checks pe-person-form__checks--3">
                    <label>
                        <input type="checkbox" value="diretor" wire:model.live="cargosGestores" wire:loading.attr="disabled">
                        Diretor
                    </label>
                    <label>
                        <input type="checkbox" value="coordenador" wire:model.live="cargosGestores" wire:loading.attr="disabled">
                        Coordenador
                    </label>
                    <label>
                        <input type="checkbox" value="secretario" wire:model.live="cargosGestores" wire:loading.attr="disabled">
                        Secretário
                    </label>
                </div>
                @error('cargosGestores')
                    <small class="is-error">{{ $message }}</small>
                @enderror
                @error('cargosGestores.*')
                    <small class="is-error">{{ $message }}</small>
                @enderror
            </fieldset>

            @if ($temDiretor || $temCoordenador)
                <label class="pe-person-form__field pe-person-form__span-2">
                    <span>Portaria <b aria-hidden="true">*</b></span>
                    <input
                        type="text"
                        wire:model.blur="portaria"
                        maxlength="255"
                        @error('portaria') aria-invalid="true" aria-describedby="pessoa-form-portaria-error" @enderror
                    >
                    <small>Diretor e Coordenador compartilham a mesma portaria alfanumérica.</small>
                    @error('portaria')
                        <small id="pessoa-form-portaria-error" class="is-error">{{ $message }}</small>
                    @enderror
                </label>
            @endif

            @if ($temCoordenador)
                <div
                    class="pe-person-form__field pe-person-form__span-2"
                    role="group"
                    aria-labelledby="pessoa-form-turmas-gestao-label"
                >
                    <div class="pe-person-form__field-heading">
                        <span id="pessoa-form-turmas-gestao-label">Turmas da coordenação <b aria-hidden="true">*</b></span>
                        @if ($turmasGestaoOptions !== [])
                            <button
                                type="button"
                                class="pe-person-form__text-button"
                                wire:click="alternarTodasTurmasGestao"
                                wire:loading.attr="disabled"
                                wire:target="alternarTodasTurmasGestao,salvar"
                            >
                                {{ $todasTurmasGestaoSelecionadas ? 'Desmarcar todas' : 'Selecionar todas' }}
                            </button>
                        @endif
                    </div>
                    <div class="pe-person-form__school-groups">
                        <section class="pe-person-form__school-group pe-person-form__school-group--selected" aria-labelledby="pessoa-form-turmas-selecionadas">
                            <header>
                                <h4 id="pessoa-form-turmas-selecionadas">Turmas coordenadas</h4>
                                <span>{{ $turmasGestaoSelecionadas->count() }}</span>
                            </header>
                            <div class="pe-person-form__checks pe-person-form__checks--2 pe-person-form__checks--scroll">
                                @forelse ($turmasGestaoSelecionadas as $value => $label)
                                    <label wire:key="coordinator-selected-class-{{ $value }}">
                                        <input type="checkbox" value="{{ $value }}" @checked(in_array((int) $value, $idsTurmasGestaoSelecionadas, true)) wire:model.live.debounce.200ms="turmaIds" wire:loading.attr="disabled">
                                        {{ $label }}
                                    </label>
                                @empty
                                    <p>Nenhuma turma selecionada.</p>
                                @endforelse
                            </div>
                        </section>

                        <section class="pe-person-form__school-group" aria-labelledby="pessoa-form-turmas-disponiveis">
                            <header>
                                <h4 id="pessoa-form-turmas-disponiveis">Outras turmas disponíveis</h4>
                                <span>{{ $turmasGestaoDisponiveis->count() }}</span>
                            </header>
                            <div class="pe-person-form__checks pe-person-form__checks--2 pe-person-form__checks--scroll">
                                @forelse ($turmasGestaoDisponiveis as $value => $label)
                                    <label wire:key="coordinator-available-class-{{ $value }}">
                                        <input type="checkbox" value="{{ $value }}" @checked(in_array((int) $value, $idsTurmasGestaoSelecionadas, true)) wire:model.live.debounce.200ms="turmaIds" wire:loading.attr="disabled">
                                        {{ $label }}
                                    </label>
                                @empty
                                    <p>Não há outras turmas disponíveis nesta escola.</p>
                                @endforelse
                            </div>
                        </section>
                    </div>
                    @error('turmaIds')
                        <small class="is-error">{{ $message }}</small>
                    @enderror
                    @error('turmaIds.*')
                        <small class="is-error">{{ $message }}</small>
                    @enderror
                </div>
            @endif
        </div>
    </section>
@endif
