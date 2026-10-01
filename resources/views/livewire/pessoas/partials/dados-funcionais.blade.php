<section class="pe-person-form__section" aria-labelledby="pessoa-form-dados-funcionais-title">
    <header class="pe-person-form__section-header">
        <span class="pe-person-form__section-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-clock" />
        </span>
        <div>
            <h3 id="pessoa-form-dados-funcionais-title">Dados funcionais</h3>
            <p>Lotação principal da pessoa. A carga horária é definida em cada matrícula.</p>
        </div>
    </header>

    <div class="pe-person-form__grid">
        <label class="pe-person-form__field">
            <span>Lotação</span>
            <select
                id="pessoa-form-lotacao"
                wire:change="lotacaoAlterada($event.target.value)"
                wire:loading.attr="disabled"
                wire:target="lotacaoAlterada,escolaAlterada,escolaGestoraAlterada,salvar"
                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                @error('lotacaoId') aria-invalid="true" aria-describedby="pessoa-form-lotacao-error" @enderror
            >
                <option value="">Sem lotação definida</option>
                @foreach ($lotacoesOptionsPorEscola as $grupo)
                    <optgroup label="{{ $grupo['label'] }}">
                        @foreach ($grupo['options'] as $value => $label)
                            <option value="{{ $value }}" @selected((int) $lotacaoId === (int) $value)>{{ $label }}</option>
                        @endforeach
                    </optgroup>
                @endforeach
            </select>
            <small>
                Opcional. São exibidas somente as lotações das escolas atualmente vinculadas à pessoa.
            </small>
            @if ($lotacoesOptionsPorEscola === [])
                <small>Nenhuma lotação disponível enquanto não houver escola vinculada.</small>
            @endif
            @error('lotacaoId')
                <small id="pessoa-form-lotacao-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>
    </div>
</section>
