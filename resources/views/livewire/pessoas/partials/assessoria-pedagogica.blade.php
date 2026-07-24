@if ($cargo === 'assessoria_pedagogica' && $podeGerenciarEquipeGestora)
    <section class="pe-person-form__section" aria-labelledby="pessoa-form-assessoria-pedagogica-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-academic-cap" />
            </span>
            <div>
                <h3 id="pessoa-form-assessoria-pedagogica-title">Assessoria Pedagógica</h3>
                <p>Define a matrícula do servidor vinculado ao cargo de Assessoria Pedagógica.</p>
            </div>
        </header>

        <div class="pe-person-form__grid pe-person-form__grid--2">
            <label class="pe-person-form__field pe-person-form__span-2">
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
        </div>
    </section>
@endif
