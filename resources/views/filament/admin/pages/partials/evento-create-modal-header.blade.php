<div class="evento-create-modal__intro" x-data x-init="
    $nextTick(() => {
        const modal = $el.closest('.evento-create-modal');
        if (! modal) return;
        const sync = () => {
            const active = modal.querySelector('[aria-current=\'step\'], [data-active=\'true\'], .fi-active');
            const texto = active?.textContent?.trim() || '';
            modal.dataset.eventoStep = texto.includes('Transporte escolar') ? '3' : (texto.includes('Convidar participantes') ? '2' : '1');
        };
        sync();
        new MutationObserver(sync).observe(modal, { subtree: true, attributes: true, attributeFilter: ['class', 'aria-current', 'data-active'] });
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
    .evento-create-modal[data-evento-step="1"] .fi-modal-footer .fi-btn-color-primary,
    .evento-create-modal[data-evento-step="2"] .fi-modal-footer .fi-btn-color-primary { display:none; }
    .evento-create-modal .fi-sc-wizard-footer { position:absolute; z-index:20; left:0; right:0; bottom:4.6rem; display:flex; justify-content:space-between; padding:1rem 1.5rem; border-top:1px solid #e2e8f0; background:#fff; }
    .evento-create-modal .fi-modal-content { padding-bottom:5.2rem; }
</style>
