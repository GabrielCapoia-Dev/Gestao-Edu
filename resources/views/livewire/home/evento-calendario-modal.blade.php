@if ($podeCriar)
    <div class="evento-custom-modal" wire:key="evento-custom-modal">
        <button type="button" class="evento-custom-modal__trigger" wire:click="abrir">
            <x-heroicon-o-plus />
            <span>Novo evento</span>
        </button>

        @if ($aberto)
            <div class="evento-custom-modal__backdrop" role="presentation">
                <section
                    class="evento-custom-modal__panel evento-create-modal"
                    role="dialog"
                    aria-modal="true"
                    aria-labelledby="evento-custom-modal-title"
                    wire:key="evento-custom-modal-panel"
                >
                    <button
                        type="button"
                        class="evento-custom-modal__close"
                        wire:click="fechar"
                        aria-label="Fechar formulário de evento"
                    >
                        <x-heroicon-o-x-mark />
                    </button>

                    @include('filament.admin.pages.partials.evento-create-modal-header')

                    <form wire:submit="salvar" class="evento-custom-modal__form" novalidate>
                        {{ $this->form }}
                    </form>
                </section>
            </div>
        @endif
    </div>
@endif
