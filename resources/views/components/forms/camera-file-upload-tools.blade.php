@php
    $target = $target ?? 'pedido-fotos';
    $maxSizeBytes = $maxSizeBytes ?? \App\Support\PedidoFotoUpload::MAX_SIZE_BYTES;
    $acceptedMimeTypes = $acceptedMimeTypes ?? \App\Support\PedidoFotoUpload::acceptedMimeTypes();
    $cameraInputId = 'camera-upload-' . \Illuminate\Support\Str::uuid();
@endphp

@once
    <style>
        .pedido-camera-shortcut[x-cloak] {
            display: none !important;
        }

        .pedido-camera-shortcut {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
            margin-top: -0.25rem;
        }

        .pedido-camera-shortcut__input {
            height: 1px !important;
            left: -9999px !important;
            opacity: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
            position: fixed !important;
            top: auto !important;
            width: 1px !important;
        }

        .pedido-camera-shortcut__button {
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            color: #1f2937;
            cursor: pointer;
            display: inline-flex;
            font-size: 0.875rem;
            font-weight: 700;
            gap: 0.45rem;
            justify-content: center;
            line-height: 1.25rem;
            min-height: 2.5rem;
            padding: 0.55rem 0.85rem;
            transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
            user-select: none;
        }

        .pedido-camera-shortcut__button:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .pedido-camera-shortcut__button:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22);
            outline: none;
        }

        .pedido-camera-shortcut__button svg {
            height: 1.15rem !important;
            width: 1.15rem !important;
        }

        .pedido-camera-shortcut__message {
            align-items: center;
            color: #64748b;
            display: inline-flex;
            font-size: 0.78rem;
            line-height: 1.2rem;
        }

        .pedido-camera-shortcut__message--error {
            color: #b91c1c;
        }

        .dark .pedido-camera-shortcut__button {
            background: #111827;
            border-color: #334155;
            color: #e5e7eb;
        }

        .dark .pedido-camera-shortcut__button:hover {
            background: #1f2937;
            border-color: #475569;
        }

        .dark .pedido-camera-shortcut__message {
            color: #94a3b8;
        }

        .dark .pedido-camera-shortcut__message--error {
            color: #fca5a5;
        }

        @media (max-width: 640px) {
            .pedido-camera-shortcut,
            .pedido-camera-shortcut__button {
                width: 100%;
            }
        }
    </style>
@endonce

<div
    x-data="pedidoCameraShortcut(@js($target), @js($maxSizeBytes), @js($acceptedMimeTypes))"
    x-cloak
    class="pedido-camera-shortcut"
>
    <input
        id="{{ $cameraInputId }}"
        x-ref="cameraInput"
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/webp"
        capture="environment"
        multiple
        class="pedido-camera-shortcut__input"
        tabindex="-1"
        aria-hidden="true"
        x-on:click="resetNativeInput($event)"
        x-on:change="handleCameraFiles($event)"
    />

    <label
        for="{{ $cameraInputId }}"
        role="button"
        tabindex="0"
        class="pedido-camera-shortcut__button"
        x-on:keydown.enter.prevent="$refs.cameraInput.click()"
        x-on:keydown.space.prevent="$refs.cameraInput.click()"
    >
        <x-heroicon-o-camera />
        Tirar foto
    </label>

    <template x-if="message">
        <span
            class="pedido-camera-shortcut__message"
            :class="{ 'pedido-camera-shortcut__message--error': hasError }"
            x-text="message"
        ></span>
    </template>
</div>

<script>
    window.pedidoCameraShortcut = function (target, maxSizeBytes, acceptedMimeTypes) {
        return {
            acceptedExtensions: ['jpeg', 'jpg', 'png', 'webp'],
            hasError: false,
            message: '',

            resetNativeInput(event) {
                event.target.value = '';
                this.message = '';
                this.hasError = false;
            },

            getUploadElement() {
                const targetSelector = `[data-camera-upload-target="${target}"]`;
                const root = this.$root || document;
                const scopes = [
                    '[data-repeater-item]',
                    '.fi-fo-repeater-item',
                    '.fi-fo-repeater-item-content',
                    '.fi-section-content',
                    'fieldset',
                ];

                for (const scopeSelector of scopes) {
                    const scope = root.closest?.(scopeSelector);
                    const scopedUpload = scope?.querySelector(targetSelector);

                    if (scopedUpload) {
                        return scopedUpload;
                    }
                }

                return document.querySelector(targetSelector);
            },

            getUploadData() {
                const uploadElement = this.getUploadElement();

                return uploadElement && window.Alpine ? window.Alpine.$data(uploadElement) : null;
            },

            makeUploadErrorMessage(file) {
                if (!file) {
                    return null;
                }

                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const mimeType = (file.type || '').toLowerCase();
                const hasInvalidFormat = !this.acceptedExtensions.includes(extension)
                    || (mimeType && !acceptedMimeTypes.includes(mimeType));
                const hasInvalidSize = file.size > maxSizeBytes;

                if (!hasInvalidFormat && !hasInvalidSize) {
                    return null;
                }

                if (hasInvalidFormat && hasInvalidSize) {
                    return `A foto "${file.name}" não foi enviada: formato incorreto e tamanho acima de 5 MB.`;
                }

                if (hasInvalidFormat) {
                    return `A foto "${file.name}" não foi enviada: use JPEG, JPG, PNG ou WEBP.`;
                }

                return `A foto "${file.name}" não foi enviada: tamanho acima de 5 MB.`;
            },

            async handleCameraFiles(event) {
                const files = Array.from(event.target.files || []);
                event.target.value = '';

                if (files.length === 0) {
                    return;
                }

                const uploadData = this.getUploadData();

                if (!uploadData?.pond) {
                    this.hasError = true;
                    this.message = 'Campo de fotos ainda não está pronto. Tente novamente.';

                    return;
                }

                let uploaded = 0;

                for (const file of files) {
                    const validationMessage = this.makeUploadErrorMessage(file);

                    if (validationMessage) {
                        this.hasError = true;
                        this.message = validationMessage;

                        continue;
                    }

                    try {
                        await uploadData.pond.addFile(file);
                        uploaded++;
                    } catch (error) {
                        this.hasError = true;
                        this.message = 'Não foi possível carregar uma das fotos. Confira o formato e o tamanho.';
                    }
                }

                if (uploaded > 0 && !this.hasError) {
                    this.message = uploaded === 1
                        ? 'Foto adicionada ao campo acima.'
                        : `${uploaded} fotos adicionadas ao campo acima.`;
                }
            },
        };
    };
</script>
