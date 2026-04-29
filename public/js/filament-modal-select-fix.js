(() => {
    const selectContainerSelector = '.fi-select-input-ctn';
    const dropdownSelector = '.fi-dropdown-panel';
    const buttonSelector = '.fi-select-input-btn[aria-expanded="true"]';

    const viewportGap = 5;
    const dropdownGap = 4;
    const maxDropdownHeight = 360;
    const minDropdownHeight = 120;

    const dropdownStates = new Map();
    const containerDropdowns = new Map();

    let frame = null;

    const getButton = (container) => {
        return container.querySelector(buttonSelector);
    };

    const getDropdown = (container) => {
        return container.querySelector(`:scope > ${dropdownSelector}`)
            || containerDropdowns.get(container)
            || null;
    };

    const stopPropagation = (event) => {
        event.stopPropagation();
    };

    const clamp = (value, min, max) => {
        return Math.min(Math.max(value, min), max);
    };

    const getAvailablePosition = (buttonRect) => {
        const spaceBelow = window.innerHeight - buttonRect.bottom - viewportGap;
        const spaceAbove = buttonRect.top - viewportGap;

        const shouldOpenUp =
            spaceBelow < minDropdownHeight &&
            spaceAbove > spaceBelow;

        const availableHeight = shouldOpenUp
            ? spaceAbove - dropdownGap
            : spaceBelow - dropdownGap;

        return {
            shouldOpenUp,
            availableHeight: Math.max(minDropdownHeight, availableHeight),
        };
    };

    const positionDropdown = (dropdown, button) => {
        const rect = button.getBoundingClientRect();

        const width = rect.width;
        const left = clamp(
            rect.left,
            viewportGap,
            window.innerWidth - width - viewportGap
        );

        const { shouldOpenUp, availableHeight } = getAvailablePosition(rect);

        const height = Math.min(
            maxDropdownHeight,
            Math.max(minDropdownHeight, availableHeight)
        );

        const top = shouldOpenUp
            ? Math.max(viewportGap, rect.top - height - dropdownGap)
            : Math.min(rect.bottom + dropdownGap, window.innerHeight - height - viewportGap);

        Object.assign(dropdown.style, {
            position: 'fixed',
            left: `${left}px`,
            top: `${top}px`,
            width: `${width}px`,
            minWidth: `${width}px`,
            maxWidth: `${width}px`,
            maxHeight: `${height}px`,
            overflowY: 'auto',
            overflowX: 'hidden',
            zIndex: '99999',
        });
    };

    const moveToBody = (dropdown, container, button) => {
        if (!dropdownStates.has(dropdown)) {
            dropdownStates.set(dropdown, {
                originalParent: dropdown.parentElement,
                nextSibling: dropdown.nextSibling,
                container,
                button,
            });

            containerDropdowns.set(container, dropdown);

            dropdown.addEventListener('pointerdown', stopPropagation);
            dropdown.addEventListener('mousedown', stopPropagation);
            dropdown.addEventListener('click', stopPropagation);

            dropdown.classList.add('fi-select-dropdown-portal');
            document.body.appendChild(dropdown);
        }

        const state = dropdownStates.get(dropdown);
        state.button = button;

        positionDropdown(dropdown, button);
    };

    const restoreDropdown = (dropdown) => {
        const state = dropdownStates.get(dropdown);

        if (!state) {
            return;
        }

        dropdown.removeEventListener('pointerdown', stopPropagation);
        dropdown.removeEventListener('mousedown', stopPropagation);
        dropdown.removeEventListener('click', stopPropagation);

        if (state.originalParent?.isConnected) {
            state.originalParent.insertBefore(
                dropdown,
                state.nextSibling?.parentNode === state.originalParent
                    ? state.nextSibling
                    : null
            );
        } else {
            dropdown.remove();
        }

        dropdown.classList.remove('fi-select-dropdown-portal');

        dropdown.style.removeProperty('position');
        dropdown.style.removeProperty('left');
        dropdown.style.removeProperty('top');
        dropdown.style.removeProperty('width');
        dropdown.style.removeProperty('min-width');
        dropdown.style.removeProperty('max-width');
        dropdown.style.removeProperty('max-height');
        dropdown.style.removeProperty('overflow-y');
        dropdown.style.removeProperty('overflow-x');
        dropdown.style.removeProperty('z-index');

        containerDropdowns.delete(state.container);
        dropdownStates.delete(dropdown);
    };

    const sync = () => {
        document.querySelectorAll(selectContainerSelector).forEach((container) => {
            const button = getButton(container);
            const dropdown = getDropdown(container);

            if (!dropdown) {
                return;
            }

            if (button) {
                moveToBody(dropdown, container, button);
                return;
            }

            restoreDropdown(dropdown);
        });

        Array.from(dropdownStates.entries()).forEach(([dropdown, state]) => {
            const button = state.button;

            if (
                !button?.isConnected ||
                button.getAttribute('aria-expanded') !== 'true'
            ) {
                restoreDropdown(dropdown);
                return;
            }

            positionDropdown(dropdown, button);
        });
    };

    const scheduleSync = () => {
        if (frame !== null) {
            return;
        }

        frame = requestAnimationFrame(() => {
            frame = null;
            sync();
        });
    };

    const boot = () => {
        const observer = new MutationObserver(scheduleSync);

        observer.observe(document.body, {
            subtree: true,
            childList: true,
            attributes: true,
            attributeFilter: ['aria-expanded', 'class'],
        });

        window.addEventListener('resize', scheduleSync);
        window.addEventListener('scroll', scheduleSync, true);

        document.addEventListener('click', scheduleSync, true);
        document.addEventListener('focusin', scheduleSync, true);

        scheduleSync();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();