<section class="pe-person-form__section" aria-labelledby="pessoa-form-motorista-title">
    <header class="pe-person-form__section-header">
        <span class="pe-person-form__section-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-identification" />
        </span>
        <div>
            <h3 id="pessoa-form-motorista-title">Dados do motorista</h3>
            <p>O motorista atende toda a rede e não recebe usuário, permissões ou vínculo escolar.</p>
        </div>
    </header>

    <div class="pe-person-form__grid pe-person-form__grid--2">
        <label class="pe-person-form__field pe-person-form__span-2">
            <span>Matrícula</span>
            <input
                type="text"
                wire:model.blur="matriculaMotorista"
                maxlength="255"
                placeholder="Opcional"
                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                @error('matriculaMotorista') aria-invalid="true" aria-describedby="pessoa-form-motorista-matricula-error" @enderror
            >
            @error('matriculaMotorista')
                <small id="pessoa-form-motorista-matricula-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>
    </div>
</section>
