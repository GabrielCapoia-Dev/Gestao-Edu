<section class="pe-person-form__section" aria-labelledby="pessoa-form-identidade-title">
    <header class="pe-person-form__section-header">
        <span class="pe-person-form__section-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-user" />
        </span>
        <div>
            <h3 id="pessoa-form-identidade-title">Identidade da pessoa</h3>
            <p>Dados básicos usados em todo o sistema.</p>
        </div>
    </header>

    <div class="pe-person-form__grid pe-person-form__grid--2">
        <label class="pe-person-form__field pe-person-form__span-2">
            <span>Nome <b aria-hidden="true">*</b></span>
            <input
                type="text"
                wire:model.blur="nome"
                maxlength="255"
                autocomplete="name"
                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                @error('nome') aria-invalid="true" aria-describedby="pessoa-form-nome-error" @enderror
            >
            @error('nome')
                <small id="pessoa-form-nome-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>

        <label class="pe-person-form__field">
            <span>CPF</span>
            <input
                type="text"
                wire:model.blur="cpf"
                x-mask="999.999.999-99"
                maxlength="14"
                inputmode="numeric"
                autocomplete="off"
                placeholder="000.000.000-00"
                @disabled(! $modoCriacao && ! $podeEditarDados)
                @error('cpf') aria-invalid="true" aria-describedby="pessoa-form-cpf-error" @enderror
            >
            @error('cpf')
                <small id="pessoa-form-cpf-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>

        <label class="pe-person-form__field">
            <span>E-mail @if ($cargo !== 'motorista') <b aria-hidden="true">*</b> @endif</span>
            <input
                type="email"
                wire:model.blur="email"
                maxlength="255"
                inputmode="email"
                autocomplete="email"
                placeholder="nome@edu.umuarama.pr.gov.br"
                @disabled(! $modoCriacao && ! $podeEditarDados)
                @error('email') aria-invalid="true" aria-describedby="pessoa-form-email-error" @enderror
            >
            @error('email')
                <small id="pessoa-form-email-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>

        <label class="pe-person-form__field">
            <span>Telefone</span>
            <input
                type="text"
                wire:model.blur="telefone"
                x-mask:dynamic="$input.replace(/\D/g, '').length > 10 ? '(99) 99999-9999' : '(99) 9999-9999'"
                maxlength="15"
                inputmode="tel"
                autocomplete="tel"
                placeholder="(00) 00000-0000"
                @disabled(! $modoCriacao && ! $podeEditarDados)
                @error('telefone') aria-invalid="true" aria-describedby="pessoa-form-telefone-error" @enderror
            >
            @error('telefone')
                <small id="pessoa-form-telefone-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>

        <label class="pe-person-form__field">
            <span>Status <b aria-hidden="true">*</b></span>
            <select
                wire:model="status"
                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                @error('status') aria-invalid="true" aria-describedby="pessoa-form-status-error" @enderror
            >
                @foreach ($statusOptions as $value => $label)
                    <option value="{{ $value }}">{{ $label }}</option>
                @endforeach
            </select>
            @error('status')
                <small id="pessoa-form-status-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>
    </div>
</section>

<section class="pe-person-form__section" aria-labelledby="pessoa-form-cargo-title">
    <header class="pe-person-form__section-header">
        <span class="pe-person-form__section-icon" aria-hidden="true">
            <x-filament::icon icon="heroicon-o-briefcase" />
        </span>
        <div>
            <h3 id="pessoa-form-cargo-title">Cargo</h3>
            <p>O cargo define os vínculos necessários. Motoristas não precisam de acesso ao sistema nem de escola.</p>
        </div>
    </header>

    <div class="pe-person-form__grid pe-person-form__grid--2">
        <label class="pe-person-form__field pe-person-form__span-2">
            <span>Função / cargo <b aria-hidden="true">*</b></span>
            <select
                wire:change="cargoAlterado($event.target.value)"
                @disabled(! $modoCriacao && ! $gerenciaEstrutura)
                @error('cargo') aria-invalid="true" aria-describedby="pessoa-form-cargo-error" @enderror
            >
                <option value="professor" @selected($cargo === 'professor')>Professor</option>
                @if ($podeGerenciarEquipeGestora || $cargo === 'equipe_gestora')
                    <option value="equipe_gestora" @selected($cargo === 'equipe_gestora')>Equipe Gestora</option>
                @endif
                @if ($podeGerenciarEquipeGestora || $cargo === 'manutencao')
                    <option value="manutencao" @selected($cargo === 'manutencao')>Manutenção</option>
                @endif
                @if ($podeGerenciarEquipeGestora || $cargo === 'obras')
                    <option value="obras" @selected($cargo === 'obras')>Obras</option>
                @endif
                @if ($podeGerenciarEquipeGestora || $cargo === 'motorista')
                    <option value="motorista" @selected($cargo === 'motorista')>Motorista</option>
                @endif
            </select>
            @error('cargo')
                <small id="pessoa-form-cargo-error" class="is-error">{{ $message }}</small>
            @enderror
        </label>

        @if ($modoCriacao || $gerenciaEstrutura)
            <label class="pe-person-form__field pe-person-form__span-2">
                <span>Observações</span>
                <textarea
                    wire:model.blur="observacoes"
                    maxlength="2000"
                    rows="3"
                    @error('observacoes') aria-invalid="true" aria-describedby="pessoa-form-observacoes-error" @enderror
                ></textarea>
                @error('observacoes')
                    <small id="pessoa-form-observacoes-error" class="is-error">{{ $message }}</small>
                @enderror
            </label>
        @endif
    </div>
</section>
