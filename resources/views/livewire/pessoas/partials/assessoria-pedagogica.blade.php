@if ($cargo === 'assessoria_pedagogica' && $podeGerenciarEquipeGestora)
    <section class="pe-person-form__section" aria-labelledby="pessoa-form-assessoria-pedagogica-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-academic-cap" />
            </span>
            <div>
                <h3 id="pessoa-form-assessoria-pedagogica-title">Assessoria Pedagógica</h3>
                <p>Define a matrícula e as escolas sob responsabilidade da Assessoria Pedagógica.</p>
            </div>
        </header>

        <div class="pe-person-form__grid pe-person-form__grid--2">
            <label class="pe-person-form__field">
                <span>Matrícula</span>
                <input
                    type="text"
                    wire:model.blur="matriculaOperacional"
                    maxlength="255"
                    placeholder="Número da matrícula"
                    @error('matriculaOperacional') aria-invalid="true" aria-describedby="pessoa-form-matricula-assessoria-error" @enderror
                >
                @error('matriculaOperacional')
                    <small id="pessoa-form-matricula-assessoria-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>

            <label class="pe-person-form__field">
                <span>Turno <b aria-hidden="true">*</b></span>
                <select
                    wire:model.blur="turnoOperacional"
                    @error('turnoOperacional') aria-invalid="true" aria-describedby="pessoa-form-turno-assessoria-error" @enderror
                >
                    <option value="">Selecione o turno</option>
                    @foreach ($turnosOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                @error('turnoOperacional')
                    <small id="pessoa-form-turno-assessoria-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>

            <fieldset class="pe-person-form__field pe-person-form__span-2">
                <legend>Escolas assessoradas <b aria-hidden="true">*</b></legend>
                <div class="pe-person-form__checks pe-person-form__checks--2 pe-person-form__checks--scroll">
                    @forelse ($escolasOptions as $value => $label)
                        <label>
                            <input
                                type="checkbox"
                                value="{{ $value }}"
                                wire:model="escolaIdsAssessoria"
                                wire:loading.attr="disabled"
                            >
                            {{ $label }}
                        </label>
                    @empty
                        <p>Nenhuma escola disponível.</p>
                    @endforelse
                </div>
                @error('escolaIdsAssessoria')
                    <small class="is-error">{{ $message }}</small>
                @enderror
                @error('escolaIdsAssessoria.*')
                    <small class="is-error">{{ $message }}</small>
                @enderror
            </fieldset>
        </div>
    </section>
@endif
