@if ($cargo === 'obras' && $podeGerenciarEquipeGestora)
    <section class="pe-person-form__section" aria-labelledby="pessoa-form-obras-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-building-office-2" />
            </span>
            <div>
                <h3 id="pessoa-form-obras-title">Obras</h3>
                <p>Defina o setor operacional da pessoa vinculada ao cargo Obras.</p>
            </div>
        </header>

        <div class="pe-person-form__grid pe-person-form__grid--2">
            <label class="pe-person-form__field pe-person-form__span-2">
                <span>Setor operacional <b aria-hidden="true">*</b></span>
                <select
                    wire:model="setorObrasId"
                    wire:loading.attr="disabled"
                    wire:target="salvar"
                    @error('setorObrasId') aria-invalid="true" aria-describedby="pessoa-form-setor-obras-error" @enderror
                >
                    <option value="">Selecione um setor</option>
                    @foreach ($setoresObrasOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <small>A visualização e a edição de pedidos respeitam o setor e a matriz de acesso configurada.</small>
                @error('setorObrasId')
                    <small id="pessoa-form-setor-obras-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>
        </div>
    </section>
@endif
