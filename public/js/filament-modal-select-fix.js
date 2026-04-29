(() => {
    const modalSelector = '.fi-modal';
    const contextSelector = '.fi-modal, .fi-modal-window, .fi-modal-window-ctn';
    const contextClass = 'fi-fixed-positioning-context';
    const openSelectClass = 'fi-has-open-select';
    const openSelectSelector =
        '.fi-select-input-btn[aria-expanded="true"], .choices.is-open';
    const selectableContextSelector =
        '.fi-modal, .fi-modal-window, .fi-modal-content, .fi-modal-window-ctn';
    const selectContainerSelector = '.fi-select-input-ctn';
    const selectDropdownSelector = '.fi-dropdown-panel';
    const portaledDropdownClass = 'fi-select-dropdown-portal';
    const minDropdownHeight = 120;
    const maxDropdownHeight = 288;
    const viewportPadding = 8;
    const dropdownGap = 4;

    let syncRequest = null;
    const portaledDropdowns = new Set();
    const dropdownPortalState = new WeakMap();
    const containerPortalDropdown = new WeakMap();

    const markModalContexts = (root = document) => {
        if (!(root instanceof Element) && root !== document) {
            return;
        }

        if (root instanceof Element && root.matches(contextSelector)) {
            root.classList.add(contextClass);
        }

        const scope = root === document ? document : root;

        scope.querySelectorAll?.(contextSelector).forEach((element) => {
            element.classList.add(contextClass);
        });
    };

    const syncOpenSelectContexts = () => {
        document.querySelectorAll('.fi-select-input').forEach((element) => {
            element.classList.toggle(
                openSelectClass,
                element.querySelector(openSelectSelector) !== null,
            );
        });

        document
            .querySelectorAll(selectableContextSelector)
            .forEach((element) => {
                element.classList.toggle(
                    openSelectClass,
                    element.querySelector(openSelectSelector) !== null,
                );
            });
    };

    const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

    const getOpenSelectButton = (container) =>
        container.querySelector('.fi-select-input-btn[aria-expanded="true"]');

    const getContainerDropdown = (container) =>
        container.querySelector(`:scope > ${selectDropdownSelector}`) ||
        containerPortalDropdown.get(container);

    const getDropdownHeight = (dropdown, availableHeight) => {
        const scrollHeight = dropdown.scrollHeight || maxDropdownHeight;

        return Math.max(
            minDropdownHeight,
            Math.min(scrollHeight, availableHeight, maxDropdownHeight),
        );
    };

    const positionPortaledDropdown = (dropdown) => {
        const state = dropdownPortalState.get(dropdown);

        if (!state || !state.button.isConnected) {
            restorePortaledDropdown(dropdown);

            return;
        }

        const buttonRect = state.button.getBoundingClientRect();
        const availableWidth = Math.max(0, window.innerWidth - viewportPadding * 2);
        const width = Math.min(buttonRect.width, availableWidth);
        const left = clamp(
            buttonRect.left,
            viewportPadding,
            Math.max(viewportPadding, window.innerWidth - width - viewportPadding),
        );
        const bottomSpace = window.innerHeight - buttonRect.bottom - viewportPadding;
        const topSpace = buttonRect.top - viewportPadding;
        const shouldOpenUpward = bottomSpace < minDropdownHeight && topSpace > bottomSpace;
        const availableHeight = Math.max(
            minDropdownHeight,
            (shouldOpenUpward ? topSpace : bottomSpace) - dropdownGap,
        );
        const height = getDropdownHeight(dropdown, availableHeight);
        const top = shouldOpenUpward
            ? Math.max(viewportPadding, buttonRect.top - height - dropdownGap)
            : Math.min(
                  buttonRect.bottom + dropdownGap,
                  window.innerHeight - height - viewportPadding,
              );

        Object.assign(dropdown.style, {
            left: `${left}px`,
            position: 'fixed',
            top: `${top}px`,
            width: `${width}px`,
            zIndex: '99999',
        });
        dropdown.style.setProperty('--edu-select-available-height', `${height}px`);
    };

    const restorePortaledDropdown = (dropdown) => {
        const state = dropdownPortalState.get(dropdown);

        if (!state) {
            return;
        }

        dropdown.removeEventListener('pointerdown', state.stopPropagation);
        dropdown.removeEventListener('mousedown', state.stopPropagation);
        dropdown.removeEventListener('click', state.stopPropagation);

        if (state.parent.isConnected) {
            state.parent.insertBefore(
                dropdown,
                state.nextSibling?.parentNode === state.parent
                    ? state.nextSibling
                    : null,
            );
        } else {
            dropdown.remove();
        }

        dropdown.classList.remove(portaledDropdownClass);
        dropdown.style.removeProperty('--edu-select-available-height');
        dropdown.style.removeProperty('left');
        dropdown.style.removeProperty('position');
        dropdown.style.removeProperty('top');
        dropdown.style.removeProperty('width');
        dropdown.style.removeProperty('z-index');

        portaledDropdowns.delete(dropdown);
        dropdownPortalState.delete(dropdown);
        containerPortalDropdown.delete(state.container);
    };

    // Keep modal select lists inside the focus trap, but outside the modal window
    // that can clip them behind headers, footers, and scroll containers.
    const portalDropdown = (container, button, dropdown, modal) => {
        if (!dropdownPortalState.has(dropdown)) {
            const stopPropagation = (event) => event.stopPropagation();

            dropdownPortalState.set(dropdown, {
                button,
                container,
                nextSibling: dropdown.nextSibling,
                parent: dropdown.parentElement,
                stopPropagation,
            });
            containerPortalDropdown.set(container, dropdown);
            portaledDropdowns.add(dropdown);

            dropdown.addEventListener('pointerdown', stopPropagation);
            dropdown.addEventListener('mousedown', stopPropagation);
            dropdown.addEventListener('click', stopPropagation);
            dropdown.classList.add(portaledDropdownClass);
            modal.appendChild(dropdown);
        }

        positionPortaledDropdown(dropdown);
        requestAnimationFrame(() => positionPortaledDropdown(dropdown));
    };

    const syncPortaledSelectDropdowns = () => {
        document.querySelectorAll(selectContainerSelector).forEach((container) => {
            const button = getOpenSelectButton(container);
            const dropdown = getContainerDropdown(container);
            const modal = container.closest(modalSelector);

            if (!dropdown) {
                return;
            }

            if (button && modal) {
                portalDropdown(container, button, dropdown, modal);

                return;
            }

            restorePortaledDropdown(dropdown);
        });

        Array.from(portaledDropdowns).forEach((dropdown) => {
            const state = dropdownPortalState.get(dropdown);
            const isStillOpen =
                state?.button.isConnected &&
                state.button.getAttribute('aria-expanded') === 'true';

            if (isStillOpen) {
                positionPortaledDropdown(dropdown);

                return;
            }

            restorePortaledDropdown(dropdown);
        });
    };

    const scheduleOpenSelectSync = () => {
        if (syncRequest !== null) {
            return;
        }

        syncRequest = requestAnimationFrame(() => {
            syncRequest = null;
            syncOpenSelectContexts();
            syncPortaledSelectDropdowns();
        });
    };

    const boot = () => {
        markModalContexts();

        if (!document.body) {
            return;
        }

        const observer = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                    if (node instanceof Element) {
                        markModalContexts(node);
                    }
                });

                if (
                    mutation.type === 'attributes' &&
                    mutation.target instanceof Element &&
                    (mutation.target.matches('.fi-select-input-btn') ||
                        mutation.target.matches('.choices'))
                ) {
                    scheduleOpenSelectSync();
                }

                if (mutation.addedNodes.length > 0) {
                    scheduleOpenSelectSync();
                }
            });
        });

        observer.observe(document.body, {
            attributes: true,
            attributeFilter: ['aria-expanded', 'class'],
            childList: true,
            subtree: true,
        });

        window.addEventListener('open-modal', () => {
            requestAnimationFrame(() => {
                markModalContexts();
                syncOpenSelectContexts();
                syncPortaledSelectDropdowns();
            });
        });

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(() => {
                markModalContexts();
                syncOpenSelectContexts();
                syncPortaledSelectDropdowns();
            });
        });

        window.addEventListener('resize', scheduleOpenSelectSync);
        window.addEventListener('scroll', scheduleOpenSelectSync, true);
        document.addEventListener('click', scheduleOpenSelectSync, true);
        document.addEventListener('focusin', scheduleOpenSelectSync, true);

        syncOpenSelectContexts();
        syncPortaledSelectDropdowns();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
