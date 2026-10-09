(() => {
    if (window.GestaoEduActionLoading) {
        return;
    }

    const config = {
        localFallbackMs: 1400,
        fieldFallbackMs: 1200,
        requestFallbackMs: 30000,
        navigationFallbackMs: 12000,
        recentActionMs: 2200,
    };

    const heavyPattern = /(salvar|excluir|deletar|delete|confirmar|enviar|gerar|exportar|importar|atualizar|sincronizar|baixar|cancelar|aprovar|recusar|finalizar|validar|aplicar|registrar|remover|vincular|desvincular|create|store|save|update|destroy|submit|export|import|download|pdf|xlsx|csv|relatorio|relatorio)/i;
    const passivePattern = /(fechar|close|toggle|alternar|mudarAba|definirVisualizacao|definirModo|abrirModal|abrirSlide|abrirAluno|abrirTurma|selecionar|previousPage|nextPage|gotoPage|mudarPagina|sortBy|limparFiltros|refreshPresence|pollAutoDownload)/i;
    const backgroundUrlPattern = /(user-presence|presence|heartbeat|notifications\/unread|notificacoes\/contador)/i;

    const state = {
        overlayCount: 0,
        lastAction: null,
        livewireHooked: false,
        axiosHooked: false,
        fetchHooked: false,
        xhrHooked: false,
    };

    const localRecords = new WeakMap();

    const now = () => Date.now();

    const noop = () => {};

    const isElement = (value) => value instanceof Element;

    const asElement = (value) => {
        if (isElement(value)) {
            return value;
        }

        return value?.parentElement instanceof Element ? value.parentElement : null;
    };

    const closest = (target, selector) => {
        const element = asElement(target);

        if (!element) {
            return null;
        }

        return element.closest(selector);
    };

    const readAttr = (element, name) => element?.getAttribute?.(name) || '';

    const readFirstDirective = (element, names) => {
        if (!element?.attributes) {
            return '';
        }

        for (const attribute of element.attributes) {
            if (names.some((name) => attribute.name === name || attribute.name.startsWith(`${name}.`))) {
                return attribute.value || '';
            }
        }

        return '';
    };

    const hasDirective = (element, names) => readFirstDirective(element, names) !== '';

    const isDisabled = (element) => {
        if (!element) {
            return true;
        }

        return Boolean(
            element.disabled ||
            element.getAttribute('aria-disabled') === 'true' ||
            element.closest('[disabled], [aria-disabled="true"]')
        );
    };

    const ensureOverlay = () => {
        let overlay = document.querySelector('[data-edu-loading-overlay]');

        if (overlay) {
            return overlay;
        }

        overlay = document.createElement('div');
        overlay.className = 'edu-loading-overlay';
        overlay.dataset.eduLoadingOverlay = 'true';
        overlay.setAttribute('role', 'status');
        overlay.setAttribute('aria-live', 'polite');
        overlay.setAttribute('aria-hidden', 'true');
        overlay.innerHTML = [
            '<div class="edu-loading-dialog">',
            '<span class="edu-loading-spinner" aria-hidden="true"></span>',
            '<p class="edu-loading-title">Processando...</p>',
            '<p class="edu-loading-copy">Aguarde enquanto a acao e concluida.</p>',
            '</div>',
        ].join('');

        document.body.appendChild(overlay);

        return overlay;
    };

    const startOverlay = () => {
        const overlay = ensureOverlay();

        state.overlayCount += 1;
        overlay.removeAttribute('aria-hidden');
        document.body.classList.add('edu-loading-overlay-active');
        document.body.setAttribute('aria-busy', 'true');

        let stopped = false;
        const safety = window.setTimeout(() => stop(), config.requestFallbackMs);

        const stop = () => {
            if (stopped) {
                return;
            }

            stopped = true;
            window.clearTimeout(safety);
            state.overlayCount = Math.max(0, state.overlayCount - 1);

            if (state.overlayCount === 0) {
                overlay.setAttribute('aria-hidden', 'true');
                document.body.classList.remove('edu-loading-overlay-active');
                document.body.removeAttribute('aria-busy');
            }
        };

        return stop;
    };

    const getLocalTarget = (element) => {
        if (!element) {
            return null;
        }

        if (element.matches('input, select, textarea')) {
            return element.closest('.fi-fo-field-wrp, .fi-input-wrp, .gi-field, .inv-field, .mobile-field, label') || element;
        }

        return element;
    };

    const startLocalLoading = (element, options = {}) => {
        const target = getLocalTarget(element);

        if (!target || target.closest('[data-loading-ignore], [data-action-loading-ignore]')) {
            return noop;
        }

        const isFieldTarget = target !== element || target.matches('.fi-fo-field-wrp, .fi-input-wrp, .gi-field, .inv-field, .mobile-field, label');
        const className = isFieldTarget ? 'edu-field-loading' : 'edu-action-loading-target';
        const previous = localRecords.get(target) || {
            count: 0,
            disabledByUs: false,
            originalDisabled: element.disabled,
        };

        previous.count += 1;
        localRecords.set(target, previous);

        target.classList.add(className, 'is-edu-loading');
        target.setAttribute('aria-busy', 'true');

        if (options.disable && element.matches('button, input[type="button"], input[type="submit"], input[type="reset"]')) {
            window.setTimeout(() => {
                if (!previous.originalDisabled && target.isConnected && element.isConnected) {
                    previous.disabledByUs = true;
                    element.disabled = true;
                    element.setAttribute('aria-disabled', 'true');
                }
            }, 0);
        } else if (options.disable && element.matches('a, [role="button"]')) {
            element.setAttribute('aria-disabled', 'true');
            element.dataset.eduLoadingLocked = String(now());
        }

        let stopped = false;

        return () => {
            if (stopped) {
                return;
            }

            stopped = true;

            const record = localRecords.get(target);

            if (!record) {
                return;
            }

            record.count = Math.max(0, record.count - 1);

            if (record.count > 0) {
                return;
            }

            target.classList.remove('is-edu-loading');
            target.removeAttribute('aria-busy');
            localRecords.delete(target);

            if (record.disabledByUs && element.isConnected) {
                element.disabled = false;
                element.removeAttribute('aria-disabled');
            } else if (element?.dataset?.eduLoadingLocked) {
                element.removeAttribute('aria-disabled');
                delete element.dataset.eduLoadingLocked;
            }
        };
    };

    const scheduleStop = (stop, ms) => {
        window.setTimeout(stop, ms);
        return stop;
    };

    const isLockedClick = (element) => {
        const lockedAt = Number(element?.dataset?.eduLoadingLocked || 0);

        return lockedAt > 0 && now() - lockedAt < config.requestFallbackMs;
    };

    const getWireAction = (element) => {
        return readFirstDirective(element, ['wire:click', 'wire:submit', 'wire:change', 'wire:keydown']);
    };

    const getActionElement = (target) => {
        const direct = closest(
            target,
            [
                '[data-action-loading]',
                '[wire\\:click]',
                '[wire\\:submit]',
                '[x-on\\:click]',
                '[onclick]',
                'button',
                'a[href]',
                'input[type="button"]',
                'input[type="submit"]',
                'input[type="reset"]',
                '[role="button"]',
                'summary',
                '.fi-tabs-item',
                '.fi-dropdown-list-item',
            ].join(',')
        );

        if (direct) {
            return direct;
        }

        let current = asElement(target);

        while (current && current !== document.body) {
            if (
                current.hasAttribute?.('@click') ||
                hasDirective(current, ['wire:click', 'x-on:click'])
            ) {
                return current;
            }

            current = current.parentElement;
        }

        return null;
    };

    const actionText = (element) => {
        if (!element) {
            return '';
        }

        return [
            element.dataset.actionLoading || '',
            readAttr(element, 'aria-label'),
            readAttr(element, 'title'),
            readAttr(element, 'href'),
            readAttr(element, 'onclick'),
            getWireAction(element),
            element.textContent || '',
        ].join(' ');
    };

    const isDownloadLikeUrl = (href) => {
        if (!href || href === '#') {
            return false;
        }

        return heavyPattern.test(href);
    };

    const classifyAction = (element, eventType = 'click') => {
        const text = actionText(element);
        const wireAction = getWireAction(element);
        const href = element?.matches?.('a[href]') ? readAttr(element, 'href') : '';
        const isSubmit = eventType === 'submit' || element?.matches?.('button[type="submit"], input[type="submit"]');
        const isLivewireMountOnly = /mount(Action|TableAction|FormComponentAction|InfolistAction)/i.test(wireAction) && !/callMounted|callTable/i.test(wireAction);
        const isPassive = passivePattern.test(text) || isLivewireMountOnly;
        const explicitHeavy = element?.matches?.('[data-action-loading="global"], [data-action-loading-heavy]');
        const explicitLocal = element?.matches?.('[data-action-loading="local"]');
        const heavy = !explicitLocal && (explicitHeavy || isSubmit || (!isPassive && heavyPattern.test(text)) || isDownloadLikeUrl(href));
        const shouldDisable = element?.matches?.('button, a[href], input[type="button"], input[type="submit"], input[type="reset"], [role="button"]') &&
            !isPassive &&
            (heavy || /callMounted|callTable|save|store|update|destroy|delete|submit|registrar|salvar|excluir|confirmar|cancelar|finalizar/i.test(wireAction));

        return {
            disable: Boolean(shouldDisable),
            heavy,
            passive: isPassive,
        };
    };

    const rememberAction = (element, classification, stopLocal = noop) => {
        state.lastAction = {
            at: now(),
            element,
            heavy: classification.heavy,
            stopLocal,
        };
    };

    const hasRecentAction = () => state.lastAction && now() - state.lastAction.at <= config.recentActionMs;

    const finishRecentLocal = () => {
        if (!state.lastAction) {
            return;
        }

        state.lastAction.stopLocal?.();
    };

    const beginAsyncRequest = (options = {}) => {
        if (!hasRecentAction() && !options.force) {
            return noop;
        }

        const recent = state.lastAction;
        const shouldBlock = options.blocking || recent?.heavy || false;
        const stopLocal = recent?.element
            ? startLocalLoading(recent.element, { disable: true })
            : noop;
        const stopOverlay = shouldBlock ? startOverlay() : noop;

        let stopped = false;
        const safety = window.setTimeout(() => stop(), config.requestFallbackMs);

        const stop = () => {
            if (stopped) {
                return;
            }

            stopped = true;
            window.clearTimeout(safety);
            stopLocal();
            stopOverlay();
            finishRecentLocal();
        };

        return stop;
    };

    const onClick = (event) => {
        const element = getActionElement(event.target);

        if (!element || element.closest('[data-loading-ignore], [data-action-loading-ignore]')) {
            return;
        }

        if (isDisabled(element)) {
            return;
        }

        if (isLockedClick(element)) {
            event.preventDefault();
            event.stopImmediatePropagation();
            return;
        }

        const classification = classifyAction(element, 'click');
        const stopLocal = startLocalLoading(element, { disable: classification.disable });

        rememberAction(element, classification, stopLocal);
        scheduleStop(stopLocal, config.localFallbackMs);

        if (classification.heavy && element.matches('a[href]') && !getWireAction(element)) {
            const stopOverlay = startOverlay();
            scheduleStop(stopOverlay, config.navigationFallbackMs);
        }
    };

    const onSubmit = (event) => {
        const form = event.target;

        if (!form || form.closest('[data-loading-ignore], [data-action-loading-ignore]')) {
            return;
        }

        const submitter = event.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
        const stopLocal = submitter ? startLocalLoading(submitter, { disable: true }) : noop;
        const stopOverlay = startOverlay();

        form.setAttribute('aria-busy', 'true');
        rememberAction(submitter || form, { heavy: true }, () => {
            stopLocal();
            form.removeAttribute('aria-busy');
        });

        scheduleStop(() => {
            stopLocal();
            stopOverlay();
            form.removeAttribute('aria-busy');
        }, config.navigationFallbackMs);

        window.setTimeout(() => {
            if (event.defaultPrevented) {
                stopLocal();
                stopOverlay();
                form.removeAttribute('aria-busy');
            }
        }, 0);
    };

    const onFieldInteraction = (event) => {
        const field = closest(event.target, 'input, select, textarea');

        if (!field || field.closest('[data-loading-ignore], [data-action-loading-ignore]')) {
            return;
        }

        const hasAsyncBinding = Boolean(
            getWireAction(field) ||
            hasDirective(field, ['wire:model', 'wire:change', 'wire:input'])
        );

        if (!hasAsyncBinding && event.type !== 'change') {
            return;
        }

        const stopLocal = startLocalLoading(field);
        rememberAction(field, { heavy: false }, stopLocal);
        scheduleStop(stopLocal, config.fieldFallbackMs);
    };

    const shouldTrackUrl = (input) => {
        const url = typeof input === 'string'
            ? input
            : input?.url || '';

        return !backgroundUrlPattern.test(url);
    };

    const installFetchHook = () => {
        if (state.fetchHooked || typeof window.fetch !== 'function') {
            return;
        }

        const originalFetch = window.fetch.bind(window);

        window.fetch = (...args) => {
            const stop = shouldTrackUrl(args[0]) ? beginAsyncRequest() : noop;

            return originalFetch(...args)
                .finally(stop);
        };

        state.fetchHooked = true;
    };

    const installXhrHook = () => {
        if (state.xhrHooked || typeof window.XMLHttpRequest !== 'function') {
            return;
        }

        const originalOpen = window.XMLHttpRequest.prototype.open;
        const originalSend = window.XMLHttpRequest.prototype.send;

        window.XMLHttpRequest.prototype.open = function open(method, url, ...rest) {
            this.__eduLoadingTrack = shouldTrackUrl(url || '');
            return originalOpen.call(this, method, url, ...rest);
        };

        window.XMLHttpRequest.prototype.send = function send(...args) {
            const stop = this.__eduLoadingTrack ? beginAsyncRequest() : noop;
            this.addEventListener('loadend', stop, { once: true });
            return originalSend.apply(this, args);
        };

        state.xhrHooked = true;
    };

    const installAxiosHook = () => {
        if (state.axiosHooked || !window.axios?.interceptors) {
            return;
        }

        window.axios.interceptors.request.use((request) => {
            if (shouldTrackUrl(request.url || '')) {
                request.__eduLoadingStop = beginAsyncRequest();
            }

            return request;
        });

        const finish = (value) => {
            value?.config?.__eduLoadingStop?.();
            return value;
        };

        const fail = (error) => {
            error?.config?.__eduLoadingStop?.();
            return Promise.reject(error);
        };

        window.axios.interceptors.response.use(finish, fail);
        state.axiosHooked = true;
    };

    const installLivewireHooks = () => {
        const livewire = window.Livewire;

        if (state.livewireHooked || !livewire?.hook) {
            return;
        }

        try {
            livewire.hook('commit', ({ succeed, fail }) => {
                const stop = beginAsyncRequest();
                succeed?.(() => stop());
                fail?.(() => stop());
            });
        } catch (error) {
            // Older Livewire versions use message hooks below.
        }

        try {
            livewire.hook('message.sent', () => {
                window.__eduLivewireStop = beginAsyncRequest();
            });

            livewire.hook('message.processed', () => {
                window.__eduLivewireStop?.();
                window.__eduLivewireStop = null;
            });

            livewire.hook('message.failed', () => {
                window.__eduLivewireStop?.();
                window.__eduLivewireStop = null;
            });
        } catch (error) {
            // Livewire v3 does not expose v2 message hooks.
        }

        state.livewireHooked = true;
    };

    const boot = () => {
        ensureOverlay();

        document.addEventListener('click', onClick, true);
        document.addEventListener('submit', onSubmit, true);
        document.addEventListener('change', onFieldInteraction, true);
        document.addEventListener('input', onFieldInteraction, true);

        document.addEventListener('livewire:init', installLivewireHooks);
        document.addEventListener('livewire:load', installLivewireHooks);

        installFetchHook();
        installXhrHook();
        installAxiosHook();
        installLivewireHooks();
    };

    window.GestaoEduActionLoading = {
        startOverlay,
        startLocalLoading,
        beginAsyncRequest,
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();
