<div class="evento-transporte-pergunta" x-data>
    <p class="evento-transporte-pergunta__label">Vai precisar de transporte para os alunos?<span>*</span></p>

    <div class="evento-transporte-pergunta__buttons" role="group" aria-label="Necessidade de transporte">
        <button
            type="button"
            class="evento-transporte-pergunta__button {{ $selecionado === 'sim' ? 'is-active' : '' }}"
            wire:click="selecionar('sim')"
            x-on:click="Livewire.find($wire.__instance.parent).$set('mountedActionsData.0.precisa_transporte_evento', 'sim')"
        >Sim</button>
        <button
            type="button"
            class="evento-transporte-pergunta__button {{ $selecionado === 'nao' ? 'is-active' : '' }}"
            wire:click="selecionar('nao')"
            x-on:click="Livewire.find($wire.__instance.parent).$set('mountedActionsData.0.precisa_transporte_evento', 'nao')"
        >Não</button>
    </div>
</div>

<style>
    .evento-transporte-pergunta { display:flex; flex-direction:column; align-items:center; gap:.8rem; padding:.35rem 0 .7rem; }
    .evento-transporte-pergunta__label { margin:0; color:#172b4d; font-size:1.1rem; font-weight:700; text-align:center; }
    .evento-transporte-pergunta__label span { margin-left:.15rem; color:#dc2626; }
    .evento-transporte-pergunta__buttons { display:flex; justify-content:center; gap:.65rem; }
    .evento-transporte-pergunta__button { min-width:6rem; border:1px solid #b8c7dc; border-radius:.55rem; background:#fff; color:#173b73; padding:.6rem 1.15rem; font-size:.9rem; font-weight:700; cursor:pointer; transition:all .15s ease; }
    .evento-transporte-pergunta__button:hover { border-color:#1d4ed8; background:#eff6ff; }
    .evento-transporte-pergunta__button.is-active { border-color:#173b73; background:#173b73; color:#fff; box-shadow:0 2px 5px #173b7333; }
</style>
