<div class="evento-create-modal__intro" x-data x-init="
    $nextTick(() => {
        const modal = $el.closest('.evento-create-modal');
        if (! modal) return;
        modal.dataset.eventoStep = '1';
        modal.__eventoStep = 1;
        modal.scrollTop = 0;
        modal.querySelectorAll('.fi-modal-content, [data-modal-content]').forEach((elemento) => elemento.scrollTop = 0);
        const sync = () => {
            modal.dataset.eventoStep = String(modal.__eventoStep || 1);
            const submit = modal.querySelector('button[type=submit]');
            if (submit) {
                submit.textContent = modal.__eventoStep === 3 ? 'Criar evento' : 'Próximo';
                submit.style.display = '';
            }
        };
        sync();
        const observer = new MutationObserver(sync);
        observer.observe(modal, { subtree: true, attributes: true, attributeFilter: ['class', 'aria-current', 'aria-selected', 'data-active'] });
        const timer = window.setInterval(sync, 150);
        modal.addEventListener('click', (event) => {
            const passo = [...modal.querySelectorAll('.fi-sc-wizard-header-step, .fi-wizard-header-step, button')]
                .find((elemento) => elemento.contains(event.target) && ['Dados do evento', 'Convidar participantes', 'Transporte escolar'].includes((elemento.textContent || '').trim()));
            if (passo) {
                modal.__eventoStep = passo.textContent.trim() === 'Dados do evento' ? 1 : (passo.textContent.trim() === 'Convidar participantes' ? 2 : 3);
                sync();
                return;
            }

            const submit = event.target.closest('button[type=submit]');
            if (! submit || modal.__eventoStep === 3) return;

            event.preventDefault();
            event.stopImmediatePropagation();
            const proximo = [...modal.querySelectorAll('button')]
                .find((botao) => (botao.textContent || '').trim().toLowerCase().includes('próximo'));
            if (proximo) {
                modal.__eventoStep = Math.min(3, modal.__eventoStep + 1);
                proximo.click();
                sync();
            }
        }, true);
        $el.addEventListener('alpine:destroy', () => { observer.disconnect(); window.clearInterval(timer); }, { once: true });
    });
">
    <div class="evento-create-modal__intro-icon" aria-hidden="true">
        <x-heroicon-o-calendar-days />
    </div>
    <div>
        <p class="evento-create-modal__eyebrow">Agenda escolar</p>
        <h2>Planeje um novo evento</h2>
        <p>Organize as informações, defina o local e escolha quem deverá acompanhar este evento.</p>
    </div>
    <div class="evento-create-modal__tip">
        <x-heroicon-o-sparkles aria-hidden="true" />
        <span>Preencha somente o que fizer sentido para sua agenda.</span>
    </div>
</div>

<style>
    .evento-create-modal { position:relative; }
    .evento-create-modal .fi-sc-wizard-footer { display:none; }
    .evento-create-modal .leaflet-container { position:relative; z-index:0; }
    .evento-create-modal .fi-dropdown-panel, .evento-create-modal .fi-select-options, .evento-create-modal [role="listbox"], .evento-create-modal .fi-fo-date-time-picker-panel { z-index:1000; }
</style>
