(() => {
    const contextSelector = '.fi-modal-window, .fi-modal-window-ctn';
    const contextClass = 'fi-fixed-positioning-context';
    const openSelectClass = 'fi-has-open-select';
    const openSelectSelector =
        '.fi-select-input-btn[aria-expanded="true"], .choices.is-open';
    const selectableContextSelector =
        '.fi-modal-window, .fi-modal-content, .fi-modal-window-ctn';

    let syncRequest = null;

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

    const scheduleOpenSelectSync = () => {
        if (syncRequest !== null) {
            return;
        }

        syncRequest = requestAnimationFrame(() => {
            syncRequest = null;
            syncOpenSelectContexts();
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
            });
        });

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(() => {
                markModalContexts();
                syncOpenSelectContexts();
            });
        });

        syncOpenSelectContexts();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
