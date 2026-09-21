<div class="evento-create-modal__intro" x-data x-init="
    $nextTick(() => {
        const modal = $el.closest('.evento-create-modal');
        if (! modal) return;
        modal.dataset.eventoStep = '1';
        modal.__eventoStep = 1;
        modal.scrollTop = 0;
        modal.querySelectorAll('.fi-modal-content, [data-modal-content]').forEach((elemento) => elemento.scrollTop = 0);
        const sync = () => {
            const activeStep = modal.querySelector('[aria-current="step"], [data-active="true"], .fi-sc-wizard-header-step-active, .fi-wizard-header-step-active');
            const activeText = (activeStep?.textContent || '').trim();
            if (activeText.includes('Convidar participantes')) modal.__eventoStep = 2;
            else if (activeText.includes('Transporte escolar')) modal.__eventoStep = 3;
            else if (activeText.includes('Dados do evento')) modal.__eventoStep = 1;
            modal.dataset.eventoStep = String(modal.__eventoStep || 1);
            const submit = modal.querySelector('button[type=submit]');
            if (submit) {
                submit.textContent = modal.__eventoStep === 3 ? 'Criar evento' : 'Próximo';
                submit.style.display = modal.__eventoStep === 3 ? '' : 'none';
            }
        };
        sync();
        const observer = new MutationObserver(sync);
        observer.observe(modal, { subtree: true, childList: true, characterData: true, attributes: true, attributeFilter: ['class', 'aria-current', 'aria-selected', 'data-active'] });
        const timer = window.setInterval(sync, 150);
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
    .evento-create-modal .fi-sc-wizard-footer { position:relative; z-index:2; }
    .evento-create-modal .leaflet-container { position:relative; z-index:0; }
    .evento-create-modal .fi-dropdown-panel, .evento-create-modal .fi-select-options, .evento-create-modal [role="listbox"], .evento-create-modal .fi-fo-date-time-picker-panel { z-index:1000; }

    .evento-create-modal .evento-transporte-pergunta {
        display:flex;
        flex-direction:column;
        align-items:center;
        gap:.75rem;
        text-align:center;
    }
    .evento-create-modal .evento-transporte-pergunta > label,
    .evento-create-modal .evento-transporte-pergunta .fi-fo-field-label {
        width:100%;
        justify-content:center;
        text-align:center;
    }
    .evento-create-modal .evento-transporte-pergunta [role="group"],
    .evento-create-modal .evento-transporte-pergunta .fi-fo-toggle-buttons,
    .evento-create-modal .evento-transporte-pergunta .fi-btn-group {
        display:flex;
        justify-content:center;
        gap:.75rem;
        width:100%;
    }
    .evento-create-modal .evento-transporte-pergunta [role="group"] > button {
        min-width:5.5rem;
        justify-content:center;
        color:#173b73;
        font-weight:600;
        line-height:1.25;
    }
    .evento-create-modal .evento-transporte-pergunta [role="group"] > button[aria-pressed="true"],
    .evento-create-modal .evento-transporte-pergunta [role="group"] > button[data-state="on"] {
        color:#fff;
    }
</style>
