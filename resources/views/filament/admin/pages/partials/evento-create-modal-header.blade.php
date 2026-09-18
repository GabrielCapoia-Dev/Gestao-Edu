<div class="evento-create-modal__intro" x-data x-init="
    $nextTick(() => {
        const modal = $el.closest('.evento-create-modal');
        if (! modal) return;
        modal.dataset.eventoStep = '1';
        modal.scrollTop = 0;
        modal.querySelectorAll('.fi-modal-content, [data-modal-content]').forEach((elemento) => elemento.scrollTop = 0);
        const sync = () => {
            const candidatos = [...modal.querySelectorAll('[aria-current=\'step\'], [aria-selected=\'true\'], [data-active=\'true\'], [class*=\'active\'], [class*=\'current\']')];
            const ativo = candidatos.find((elemento) => /Dados do evento|Convidar participantes|Transporte escolar/.test(elemento.textContent || ''));
            const texto = ativo?.textContent?.trim() || '';
            modal.dataset.eventoStep = texto.includes('Transporte escolar') ? '3' : (texto.includes('Convidar participantes') ? '2' : '1');
        };
        sync();
        const observer = new MutationObserver(sync);
        observer.observe(modal, { subtree: true, attributes: true, attributeFilter: ['class', 'aria-current', 'aria-selected', 'data-active'] });
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
    .evento-create-modal:not([data-evento-step="3"]) .fi-modal-footer .fi-btn-color-primary { display:none; }
    .evento-create-modal .fi-sc-wizard-footer { position:sticky; bottom:0; z-index:20; display:flex; justify-content:space-between; padding:1rem 1.5rem; border-top:1px solid #e2e8f0; background:#fff; box-shadow:0 -4px 12px rgba(15, 35, 65, .06); }
    .evento-create-modal .leaflet-container { position:relative; z-index:0; }
    .evento-create-modal .fi-dropdown-panel, .evento-create-modal .fi-select-options, .evento-create-modal [role="listbox"], .evento-create-modal .fi-fo-date-time-picker-panel { z-index:1000; }
</style>
