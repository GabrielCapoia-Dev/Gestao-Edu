(() => {
    const contextSelector = '.fi-modal-window, .fi-modal-window-ctn';
    const contextClass = 'fi-fixed-positioning-context';

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
            });
        });

        observer.observe(document.body, {
            childList: true,
            subtree: true,
        });

        window.addEventListener('open-modal', () => {
            requestAnimationFrame(() => {
                markModalContexts();
            });
        });

        document.addEventListener('livewire:navigated', () => {
            requestAnimationFrame(() => {
                markModalContexts();
            });
        });
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
