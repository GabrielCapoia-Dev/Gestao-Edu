@if ($cargo === 'manutencao' && $podeGerenciarEquipeGestora)
    <section class="pe-person-form__section" aria-labelledby="pessoa-form-manutencao-title">
        <header class="pe-person-form__section-header">
            <span class="pe-person-form__section-icon" aria-hidden="true">
                <x-filament::icon icon="heroicon-o-wrench-screwdriver" />
            </span>
            <div>
                <h3 id="pessoa-form-manutencao-title">Manutenção</h3>
                <p>Defina o setor operacional responsável pelo fluxo de pedidos de todas as escolas.</p>
            </div>
        </header>

        <div class="pe-person-form__grid pe-person-form__grid--2">
            <label class="pe-person-form__field pe-person-form__span-2">
                <span>Setor operacional <b aria-hidden="true">*</b></span>
                <select
                    wire:model="setorManutencaoId"
                    wire:loading.attr="disabled"
                    wire:target="salvar"
                    @error('setorManutencaoId') aria-invalid="true" aria-describedby="pessoa-form-setor-manutencao-error" @enderror
                >
                    <option value="">Selecione um setor</option>
                    @foreach ($setoresManutencaoOptions as $value => $label)
                        <option value="{{ $value }}">{{ $label }}</option>
                    @endforeach
                </select>
                <small>A edição e o encaminhamento de pedidos respeitam a matriz de acesso configurada para este setor.</small>
                @error('setorManutencaoId')
                    <small id="pessoa-form-setor-manutencao-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>
        </div>
    </section>
@endif
