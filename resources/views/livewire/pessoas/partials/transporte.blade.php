@if ($cargo === 'transporte' && $podeGerenciarEquipeGestora)
    <section class="pe-person-form__section" aria-labelledby="pessoa-form-transporte-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-truck" />
            </span>
            <div>
                <h3 id="pessoa-form-transporte-title">Transporte</h3>
                <p>Define a matrícula do servidor vinculado ao cargo de Transporte.</p>
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
                    @error('matriculaOperacional') aria-invalid="true" aria-describedby="pessoa-form-matricula-transporte-error" @enderror
                >
                @error('matriculaOperacional')
                    <small id="pessoa-form-matricula-transporte-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>
        </div>
    </section>
@endif
