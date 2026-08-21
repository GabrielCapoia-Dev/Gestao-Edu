<section class="pe-person-form__section" aria-labelledby="pessoa-form-dados-funcionais-title">
    <header class="pe-person-form__section-header">
        <span class="pe-person-form__section-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-clock" />
        </span>
        <div>
            <h3 id="pessoa-form-dados-funcionais-title">Dados funcionais</h3>
            <p>Carga semanal, jornada adicional e lotação principal da pessoa.</p>
        </div>
    </header>

    <div class="pe-person-form__grid pe-person-form__grid--2">
        <label class="pe-person-form__field">
            <span>Carga horária <b aria-hidden="true">*</b></span>
            <select
                id="pessoa-form-carga-horaria"
                wire:change="cargaHorariaAlterada($event.target.value)"
                wire:confirm="Alterar a carga horária pode ajustar os turnos e remover a matrícula de jornada. Deseja continuar?"
                wire:loading.attr="disabled"
                wire:target="cargaHorariaAlterada,jornadaAlterada,salvar"
                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                @error('cargaHoraria') aria-invalid="true" aria-describedby="pessoa-form-carga-horaria-error" @enderror
            >
                <option value="">Selecione</option>
                @foreach ($cargasHorariasOptions as $value => $label)
                    <option value="{{ $value }}" @selected((int) $cargaHoraria === (int) $value)>{{ $label }}</option>
                @endforeach
            </select>
            <small>20 horas utiliza manhã ou tarde; 40 horas utiliza turno integral.</small>
            @error('cargaHoraria')
                <small id="pessoa-form-carga-horaria-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>

        <fieldset class="pe-person-form__field">
            <legend>Jornada</legend>
            <div class="pe-person-form__checks">
                <label>
                    <input
                        id="pessoa-form-jornada"
                        type="checkbox"
                        wire:change="jornadaAlterada($event.target.checked)"
                        wire:confirm="Alterar a jornada pode criar ou remover a matrícula do turno oposto. Deseja continuar?"
                        wire:loading.attr="disabled"
                        wire:target="cargaHorariaAlterada,jornadaAlterada,salvar"
                        @checked($jornada)
                        @disabled((! $modoCriacao && ! $gerenciaEstrutura) || ! $permiteJornada || $cargaHoraria !== 20)
                    >
                    Habilitar jornada em turno oposto
                </label>
            </div>
            <small>
                Disponível somente para 20 horas. A matrícula secundária terá número próprio e poderá ser vinculada a outra escola.
            </small>
            @error('jornada')
                <small class="is-error">{{ $message }}</small>
            @enderror
        </fieldset>

        <label class="pe-person-form__field pe-person-form__span-2">
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
