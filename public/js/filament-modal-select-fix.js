(() => {
    const modalSelector = '.fi-modal';
    const selectContainerSelector = '.fi-select-input-ctn';
    const dropdownSelector = '.fi-dropdown-panel';

    const activeDropdowns = new WeakMap();

    const getButton = (container) =>
        container.querySelector('.fi-select-input-btn[aria-expanded="true"]');

    const getDropdown = (container) =>
        container.querySelector(`:scope > ${dropdownSelector}`);

    const positionDropdown = (dropdown, button) => {
        const rect = button.getBoundingClientRect();

        dropdown.style.position = 'fixed';
        dropdown.style.top = `${rect.bottom + 4}px`;
        dropdown.style.left = `${rect.left}px`;
        dropdown.style.width = `${rect.width}px`;
        dropdown.style.zIndex = '99999';
    };

    const moveToBody = (dropdown, container, button) => {
        if (activeDropdowns.has(dropdown)) return;

        const originalParent = dropdown.parentElement;
        const nextSibling = dropdown.nextSibling;

        activeDropdowns.set(dropdown, {
            originalParent,
            nextSibling,
            container,
            button
        });

        document.body.appendChild(dropdown);

        positionDropdown(dropdown, button);
    };

    const restoreDropdown = (dropdown) => {
        const state = activeDropdowns.get(dropdown);
        if (!state) return;

        const { originalParent, nextSibling } = state;

        if (originalParent?.isConnected) {
            originalParent.insertBefore(dropdown, nextSibling);
        }

        dropdown.style.position = '';
        dropdown.style.top = '';
        dropdown.style.left = '';
        dropdown.style.width = '';
        dropdown.style.zIndex = '';

        activeDropdowns.delete(dropdown);
    };

    const sync = () => {
        document.querySelectorAll(selectContainerSelector).forEach((container) => {
            const button = getButton(container);
            const dropdown = getDropdown(container);

            if (!dropdown) return;

            if (button) {
                moveToBody(dropdown, container, button);
                positionDropdown(dropdown, button);
            } else {
                restoreDropdown(dropdown);
            }
        });

        activeDropdowns.forEach((state, dropdown) => {
            if (!state.button.isConnected || state.button.getAttribute('aria-expanded') !== 'true') {
                restoreDropdown(dropdown);
            } else {
                positionDropdown(dropdown, state.button);
            }
        });
    };

    const observe = () => {
        const observer = new MutationObserver(sync);

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['aria-expanded', 'class']
        });

        window.addEventListener('resize', sync);
        window.addEventListener('scroll', sync, true);

        document.addEventListener('click', sync, true);
        document.addEventListener('focusin', sync, true);

        sync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', observe, { once: true });
    } else {
        observe();
    }
})();