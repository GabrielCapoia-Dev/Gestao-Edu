<div
    class="pe-person-form"
    wire:key="pessoa-form-{{ $pessoaId ?? 'nova' }}"
    x-data="{ aba: 'dados' }"
>
    <div
        class="pe-person-form__loading"
        wire:loading.flex
        wire:target="salvar"
        role="status"
        aria-live="assertive"
        aria-label="Processando o formulário"
    >
        <div class="pe-person-form__loading-card">
            <span class="pe-person-form__spinner" aria-hidden="true"></span>
            <div>
                <strong>{{ $modoCriacao ? 'Cadastrando pessoa...' : 'Salvando alterações...' }}</strong>
                <small>Aguarde enquanto os dados são validados e processados.</small>
            </div>
        </div>
    </div>

    <form wire:submit="salvar" class="pe-person-form__form" novalidate>
        @if ($errors->any())
            <div class="pe-person-form__alert" role="alert" aria-labelledby="pessoa-form-errors-title">
                <strong id="pessoa-form-errors-title">Revise os dados antes de salvar.</strong>
                <ul>
                    @foreach ($errors->all() as $mensagem)
                        <li>{{ $mensagem }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <div class="pe-person-form__main-tabs" role="tablist" aria-label="Seções do formulário">
            <button
                id="pessoa-form-tab-dados"
                type="button"
                role="tab"
                :class="{ 'is-active': aba === 'dados' }"
                :aria-selected="aba === 'dados' ? 'true' : 'false'"
                :tabindex="aba === 'dados' ? 0 : -1"
                aria-controls="pessoa-form-panel-dados"
                @click="aba = 'dados'"
            >
                Dados pessoais
            </button>
            <button
                id="pessoa-form-tab-vinculos"
                type="button"
                role="tab"
                :class="{ 'is-active': aba === 'vinculos' }"
                :aria-selected="aba === 'vinculos' ? 'true' : 'false'"
                :tabindex="aba === 'vinculos' ? 0 : -1"
                aria-controls="pessoa-form-panel-vinculos"
                @click="aba = 'vinculos'"
            >
                Matrículas e vínculos
            </button>
        </div>

        <div class="pe-person-form__body">
            <div
                id="pessoa-form-panel-dados"
                role="tabpanel"
                aria-labelledby="pessoa-form-tab-dados"
                x-show="aba === 'dados'"
                x-cloak
            >
                @include('livewire.pessoas.partials.dados-pessoais')
            </div>

            <div
                id="pessoa-form-panel-vinculos"
                role="tabpanel"
                aria-labelledby="pessoa-form-tab-vinculos"
                x-show="aba === 'vinculos'"
                x-cloak
            >
                @include('livewire.pessoas.partials.matriculas-lotacoes')

                @include('livewire.pessoas.partials.equipe-gestora')

                @include('livewire.pessoas.partials.manutencao')

                @include('livewire.pessoas.partials.obras')
            </div>
        </div>

        <footer class="pe-person-form__footer">
            <p>
                @if ($modoCriacao)
                    O cadastro será aplicado em uma única transação.
                @elseif ($gerenciaEstrutura)
                    Alterações estruturais serão aplicadas em uma única transação.
                @elseif ($podeEditarTurmas || $podeEditarDados)
                    Somente os dados autorizados da sua unidade serão atualizados.
                @else
                    Este formulário está disponível somente para consulta.
                @endif
            </p>
            <div class="pe-person-form__footer-actions">
                @if ($podeSalvar)
                    <button
                        type="submit"
                        class="pe-person-form__button pe-person-form__button--primary"
                        wire:loading.attr="disabled"
                        wire:target="salvar"
                    >
                        <span wire:loading.remove wire:target="salvar">
                            {{ $modoCriacao ? 'Cadastrar pessoa' : 'Salvar alterações' }}
                        </span>
                        <span wire:loading wire:target="salvar">Processando...</span>
                    </button>
                @endif
                <button
                    type="button"
                    class="pe-person-form__button pe-person-form__button--cancel"
                    x-on:click="$dispatch('close-modal', { id: $el.closest('[data-fi-modal-id]').dataset.fiModalId })"
                    wire:loading.attr="disabled"
                >
                    {{ $podeSalvar ? 'Cancelar' : 'Fechar' }}
                </button>
            </div>
        </footer>
    </form>
</div>
