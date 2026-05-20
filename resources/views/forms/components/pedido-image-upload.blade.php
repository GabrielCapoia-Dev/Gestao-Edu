@php
    $fieldWrapperView = $getFieldWrapperView();
    $id = $getId();
    $key = $getKey();
    $statePath = $getStatePath();
    $isDisabled = $isDisabled();
    $isMultiple = $isMultiple();
    $label = $getLabel();
    $maxFiles = $getMaxFiles() ?? 10;
    $maxSizeKb = $getMaxSize() ?? \App\Support\PedidoFotoUpload::MAX_SIZE_KB;
    $maxSizeBytes = $maxSizeKb * 1024;
    $acceptedMimeTypes = $getAcceptedFileTypes() ?: \App\Support\PedidoFotoUpload::acceptedMimeTypes();
    $acceptedExtensions = collect($acceptedMimeTypes)
        ->map(fn (string $type) => match ($type) {
            'image/jpeg', 'image/jpg' => ['jpg', 'jpeg'],
            'image/png' => ['png'],
            'image/webp' => ['webp'],
            default => [],
        })
        ->flatten()
        ->unique()
        ->values()
        ->all();
    $accept = collect($acceptedExtensions)
        ->map(fn (string $extension) => ".{$extension}")
        ->merge($acceptedMimeTypes)
        ->implode(',');
    $cameraInputId = "{$id}-camera";
    $galleryInputId = "{$id}-gallery";
@endphp

@once
    <style>
        .pedido-image-upload[x-cloak] {
            display: none !important;
        }

        .pedido-image-upload {
            display: grid;
            gap: 0.8rem;
        }

        .pedido-image-upload__label {
            color: #111827;
            display: block;
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .pedido-image-upload__required {
            color: #dc2626;
            margin-left: 0.15rem;
        }

        .pedido-image-upload__native-input {
            height: 1px !important;
            left: -9999px !important;
            opacity: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
            position: fixed !important;
            top: auto !important;
            width: 1px !important;
        }

        .pedido-image-upload__dropzone {
            align-items: center;
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            color: #475569;
            cursor: pointer;
            display: grid;
            gap: 0.45rem;
            justify-items: center;
            min-height: 6.25rem;
            padding: 1rem;
            text-align: center;
            transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }

        .pedido-image-upload__dropzone:hover,
        .pedido-image-upload__dropzone--dragging {
            background: #f8fafc;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .pedido-image-upload__dropzone-icon {
            align-items: center;
            background: #eff6ff;
            border-radius: 999px;
            color: #1d4ed8;
            display: inline-flex;
            height: 2.4rem;
            justify-content: center;
            width: 2.4rem;
        }

        .pedido-image-upload__dropzone-icon svg,
        .pedido-image-upload__button svg {
            height: 1.15rem !important;
            width: 1.15rem !important;
        }

        .pedido-image-upload__dropzone-title {
            color: #1f2937;
            font-size: 0.92rem;
            font-weight: 750;
            line-height: 1.25rem;
        }

        .pedido-image-upload__dropzone-text,
        .pedido-image-upload__helper {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.2rem;
        }

        .pedido-image-upload__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pedido-image-upload__button {
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

        .pedido-image-upload__button:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .pedido-image-upload__button:focus,
        .pedido-image-upload__dropzone:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22);
            outline: none;
        }

        .pedido-image-upload__preview-grid {
            display: grid;
            gap: 0.75rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pedido-image-upload__preview {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 9px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
            min-width: 0;
            overflow: hidden;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .pedido-image-upload__preview--ok {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.28), 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pedido-image-upload__preview--error {
            border-color: #ef4444;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.28), 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pedido-image-upload__preview--uploading {
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.25), 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pedido-image-upload__media {
            background: #f1f5f9;
            position: relative;
        }

        .pedido-image-upload__media img,
        .pedido-image-upload__empty-media {
            aspect-ratio: 4 / 3;
            display: block;
            height: auto;
            object-fit: cover;
            width: 100%;
        }

        .pedido-image-upload__empty-media {
            align-items: center;
            color: #64748b;
            display: flex;
            font-size: 0.82rem;
            justify-content: center;
        }

        .pedido-image-upload__status {
            align-items: center;
            border-radius: 999px;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.22);
            color: #ffffff;
            display: inline-flex;
            font-size: 0.9rem;
            font-weight: 800;
            height: 1.8rem;
            justify-content: center;
            position: absolute;
            right: 0.55rem;
            top: 0.55rem;
            width: 1.8rem;
        }

        .pedido-image-upload__status--ok {
            background: #059669;
        }

        .pedido-image-upload__status--error {
            background: #dc2626;
        }

        .pedido-image-upload__status--uploading {
            background: #d97706;
        }

        .pedido-image-upload__spinner {
            animation: pedido-image-upload-spin 0.8s linear infinite;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-radius: 999px;
            border-top-color: #ffffff;
            display: inline-block;
            height: 0.9rem;
            width: 0.9rem;
        }

        .pedido-image-upload__body {
            display: grid;
            gap: 0.35rem;
            padding: 0.65rem 0.7rem;
        }

        .pedido-image-upload__meta {
            align-items: flex-start;
            display: flex;
            gap: 0.5rem;
            justify-content: space-between;
            min-width: 0;
        }

        .pedido-image-upload__name {
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 750;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pedido-image-upload__size {
            color: #64748b;
            flex: 0 0 auto;
            font-size: 0.74rem;
            line-height: 1.15rem;
        }

        .pedido-image-upload__message {
            font-size: 0.76rem;
            line-height: 1.15rem;
            margin: 0;
        }

        .pedido-image-upload__message--ok {
            color: #047857;
        }

        .pedido-image-upload__message--error {
            color: #b91c1c;
        }

        .pedido-image-upload__message--uploading {
            color: #b45309;
        }

        .pedido-image-upload__progress {
            background: #e5e7eb;
            border-radius: 999px;
            height: 0.28rem;
            overflow: hidden;
        }

        .pedido-image-upload__progress-bar {
            background: #2563eb;
            height: 100%;
            transition: width 140ms ease;
        }

        .pedido-image-upload__remove {
            align-items: center;
            background: rgba(15, 23, 42, 0.82);
            border: 0;
            border-radius: 999px;
            color: #ffffff;
            cursor: pointer;
            display: inline-flex;
            font-size: 1rem;
            height: 1.75rem;
            justify-content: center;
            left: 0.55rem;
            position: absolute;
            top: 0.55rem;
            width: 1.75rem;
        }

        .pedido-image-upload__error {
            background: #fef2f2;
            border: 1px solid #fecaca;
            border-radius: 8px;
            color: #b91c1c;
            font-size: 0.8rem;
            line-height: 1.2rem;
            padding: 0.55rem 0.7rem;
        }

        .dark .pedido-image-upload__label,
        .dark .pedido-image-upload__dropzone-title,
        .dark .pedido-image-upload__name {
            color: #f8fafc;
        }

        .dark .pedido-image-upload__button,
        .dark .pedido-image-upload__preview,
        .dark .pedido-image-upload__dropzone {
            background: #111827;
            border-color: #334155;
            color: #e5e7eb;
        }

        .dark .pedido-image-upload__button:hover,
        .dark .pedido-image-upload__dropzone:hover,
        .dark .pedido-image-upload__dropzone--dragging {
            background: #1f2937;
            border-color: #475569;
        }

        .dark .pedido-image-upload__dropzone-icon {
            background: #172554;
            color: #bfdbfe;
        }

        .dark .pedido-image-upload__media {
            background: #0f172a;
        }

        .dark .pedido-image-upload__dropzone-text,
        .dark .pedido-image-upload__helper,
        .dark .pedido-image-upload__size,
        .dark .pedido-image-upload__empty-media {
            color: #94a3b8;
        }

        @keyframes pedido-image-upload-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 640px) {
            .pedido-image-upload__actions,
            .pedido-image-upload__button {
                width: 100%;
            }

            .pedido-image-upload__preview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce

<x-dynamic-component
    :component="$fieldWrapperView"
    :field="$field"
    label-tag="div"
>
    <div
        x-data="pedidoImageUploadField({
            acceptedExtensions: @js($acceptedExtensions),
            acceptedMimeTypes: @js($acceptedMimeTypes),
            isDisabled: @js($isDisabled),
            isMultiple: @js($isMultiple),
            maxFiles: @js($maxFiles),
            maxSizeBytes: @js($maxSizeBytes),
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            statePath: @js($statePath),
            uploadUsing: (fileKey, file, success, error, progress) => {
                $wire.upload(
                    `{{ $statePath }}.${fileKey}`,
                    file,
                    success,
                    error,
                    (progressEvent) => progress(progressEvent.detail.progress),
                )
            },
            removeUsing: (fileKey) => {
                return $wire.callSchemaComponentMethod(
                    @js($key),
                    'removeUploadedFile',
                    { fileKey },
                )
            },
        })"
        x-init="init()"
        x-cloak
        id="{{ $id }}"
        class="pedido-image-upload"
    >
        <input
            id="{{ $cameraInputId }}"
            x-ref="cameraInput"
            type="file"
            accept="{{ $accept }}"
            capture="environment"
            class="pedido-image-upload__native-input"
            tabindex="-1"
            aria-hidden="true"
            x-bind:disabled="isDisabled"
            x-on:click="resetNativeInput($event)"
            x-on:change="handleFiles($event, 'camera')"
        />

        <input
            id="{{ $galleryInputId }}"
            x-ref="galleryInput"
            type="file"
            accept="{{ $accept }}"
            @if($isMultiple) multiple @endif
            class="pedido-image-upload__native-input"
            tabindex="-1"
            aria-hidden="true"
            x-bind:disabled="isDisabled"
            x-on:click="resetNativeInput($event)"
            x-on:change="handleFiles($event, 'gallery')"
        />

        <div class="pedido-image-upload__label">
            {{ $label }}
            @if($isRequired())
                <span class="pedido-image-upload__required">*</span>
            @endif
        </div>

        <label
            for="{{ $galleryInputId }}"
            class="pedido-image-upload__dropzone"
            tabindex="0"
            :class="{ 'pedido-image-upload__dropzone--dragging': isDragging }"
            x-on:keydown.enter.prevent="$refs.galleryInput.click()"
            x-on:keydown.space.prevent="$refs.galleryInput.click()"
            x-on:dragenter.prevent="isDragging = true"
            x-on:dragover.prevent="isDragging = true"
            x-on:dragleave.prevent="isDragging = false"
            x-on:drop.prevent="handleDrop($event)"
        >
            <span class="pedido-image-upload__dropzone-icon">
                <x-heroicon-o-cloud-arrow-up />
            </span>

            <span class="pedido-image-upload__dropzone-title">
                Arraste as fotos aqui ou clique para selecionar
            </span>

            <span class="pedido-image-upload__dropzone-text">
                JPEG, JPG, PNG ou WEBP. Até {{ $maxFiles }} imagens de {{ (int) ($maxSizeKb / 1024) }} MB.
            </span>
        </label>

        <div class="pedido-image-upload__actions">
            <label
                for="{{ $cameraInputId }}"
                role="button"
                tabindex="0"
                x-show="hasCamera"
                x-on:keydown.enter.prevent="$refs.cameraInput.click()"
                x-on:keydown.space.prevent="$refs.cameraInput.click()"
                class="pedido-image-upload__button"
            >
                <x-heroicon-o-camera />
                Tirar foto
            </label>

            <label
                for="{{ $galleryInputId }}"
                role="button"
                tabindex="0"
                x-on:keydown.enter.prevent="$refs.galleryInput.click()"
                x-on:keydown.space.prevent="$refs.galleryInput.click()"
                class="pedido-image-upload__button"
            >
                <x-heroicon-o-photo />
                Galeria
            </label>
        </div>

        <template x-if="error">
            <div class="pedido-image-upload__error" x-text="error"></div>
        </template>

        <template x-if="items.length > 0">
            <div class="pedido-image-upload__preview-grid">
                <template x-for="item in items" :key="item.id">
                    <div
                        class="pedido-image-upload__preview"
                        :class="{
                            'pedido-image-upload__preview--ok': item.status === 'uploaded',
                            'pedido-image-upload__preview--error': item.status === 'error',
                            'pedido-image-upload__preview--uploading': ['checking', 'uploading'].includes(item.status),
                        }"
                    >
                        <div class="pedido-image-upload__media">
                            <template x-if="item.previewUrl">
                                <img :src="item.previewUrl" :alt="item.name" />
                            </template>

                            <template x-if="! item.previewUrl">
                                <div class="pedido-image-upload__empty-media">Sem preview</div>
                            </template>

                            <button
                                type="button"
                                class="pedido-image-upload__remove"
                                x-on:click="removeItem(item)"
                                aria-label="Remover imagem"
                            >
                                &times;
                            </button>

                            <span
                                class="pedido-image-upload__status"
                                :class="{
                                    'pedido-image-upload__status--ok': item.status === 'uploaded',
                                    'pedido-image-upload__status--error': item.status === 'error',
                                    'pedido-image-upload__status--uploading': ['checking', 'uploading'].includes(item.status),
                                }"
                            >
                                <span x-show="item.status === 'uploaded'">&#10003;</span>
                                <span x-show="item.status === 'error'">&times;</span>
                                <span x-show="['checking', 'uploading'].includes(item.status)" class="pedido-image-upload__spinner"></span>
                            </span>
                        </div>

                        <div class="pedido-image-upload__body">
                            <div class="pedido-image-upload__meta">
                                <span class="pedido-image-upload__name" x-text="item.name"></span>
                                <span class="pedido-image-upload__size" x-text="item.sizeLabel"></span>
                            </div>

                            <p
                                class="pedido-image-upload__message"
                                :class="{
                                    'pedido-image-upload__message--ok': item.status === 'uploaded',
                                    'pedido-image-upload__message--error': item.status === 'error',
                                    'pedido-image-upload__message--uploading': ['checking', 'uploading'].includes(item.status),
                                }"
                                x-text="item.message"
                            ></p>

                            <template x-if="item.status === 'uploading'">
                                <div class="pedido-image-upload__progress">
                                    <div class="pedido-image-upload__progress-bar" :style="`width: ${item.progress}%`"></div>
                                </div>
                            </template>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>
</x-dynamic-component>

<script>
    window.pedidoImageUploadField = function ({
        acceptedExtensions,
        acceptedMimeTypes,
        isDisabled,
        isMultiple,
        maxFiles,
        maxSizeBytes,
        state,
        statePath,
        uploadUsing,
        removeUsing,
    }) {
        return {
            error: null,
            hasCamera: false,
            isDisabled,
            isDragging: false,
            items: [],
            nextItemId: 1,
            state,
            statePath,

            async init() {
                this.hasCamera = await this.detectCamera();

                this.$watch('items', () => {
                    this.error = null;
                });
            },

            async detectCamera() {
                const isTouchDevice = navigator.maxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches;

                if (!navigator.mediaDevices?.enumerateDevices) {
                    return isTouchDevice;
                }

                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();

                    return devices.some((device) => device.kind === 'videoinput') || isTouchDevice;
                } catch (error) {
                    return isTouchDevice;
                }
            },

            dispatchFormEvent(name, detail = {}) {
                this.$el.closest('form')?.dispatchEvent(new CustomEvent(name, {
                    composed: true,
                    cancelable: true,
                    detail,
                }));
            },

            resetNativeInput(event) {
                event.target.value = '';
            },

            makeFileKey() {
                return ([1e7] + -1e3 + -4e3 + -8e3 + -1e11).replace(/[018]/g, (character) => (
                    character ^ (crypto.getRandomValues(new Uint8Array(1))[0] & (15 >> (character / 4)))
                ).toString(16));
            },

            activeItemsCount() {
                return this.items.filter((item) => item.status !== 'error' && !item.removed).length;
            },

            fileSizeLabel(file) {
                const size = file?.size || 0;

                if (size >= 1024 * 1024) {
                    return `${(size / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
                }

                return `${Math.max(1, Math.round(size / 1024))} KB`;
            },

            makePreviewItem(file, source) {
                return {
                    id: this.nextItemId++,
                    fileKey: this.makeFileKey(),
                    name: file.name || (source === 'camera' ? 'foto-camera.jpg' : 'imagem.jpg'),
                    previewUrl: file.type?.startsWith('image/') ? URL.createObjectURL(file) : null,
                    progress: 0,
                    removed: false,
                    sizeLabel: this.fileSizeLabel(file),
                    status: 'checking',
                    message: 'Verificando imagem...',
                };
            },

            validateFile(file) {
                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const mimeType = (file.type || '').toLowerCase();
                const invalidFormat = !acceptedExtensions.includes(extension)
                    || (mimeType && !acceptedMimeTypes.includes(mimeType));
                const invalidSize = file.size > maxSizeBytes;
                const tooManyFiles = this.activeItemsCount() >= maxFiles;

                if (tooManyFiles) {
                    return `A foto "${file.name}" não foi enviada: o limite é de ${maxFiles} imagens.`;
                }

                if (invalidFormat && invalidSize) {
                    return `A foto "${file.name}" não foi enviada: formato incorreto e tamanho acima de 5 MB. Use JPEG, JPG, PNG ou WEBP com até 5 MB.`;
                }

                if (invalidFormat) {
                    return `A foto "${file.name}" não foi enviada: formato incorreto. Use JPEG, JPG, PNG ou WEBP.`;
                }

                if (invalidSize) {
                    return `A foto "${file.name}" não foi enviada: tamanho acima de 5 MB. Envie uma imagem com até 5 MB.`;
                }

                return null;
            },

            async handleFiles(event, source) {
                const files = Array.from(event.target.files || []);
                event.target.value = '';

                await this.queueFiles(files, source);
            },

            async handleDrop(event) {
                this.isDragging = false;

                await this.queueFiles(Array.from(event.dataTransfer?.files || []), 'drop');
            },

            async queueFiles(files, source) {
                if (isDisabled || files.length === 0) {
                    return;
                }

                if (!isMultiple) {
                    this.items.forEach((item) => this.removeItem(item));
                    files = files.slice(0, 1);
                }

                for (const file of files) {
                    await this.queueFile(file, source);
                }
            },

            async queueFile(file, source) {
                const item = this.makePreviewItem(file, source);

                this.items.unshift(item);

                await new Promise((resolve) => setTimeout(resolve, 80));

                const validationMessage = this.validateFile(file);

                if (validationMessage) {
                    item.status = 'error';
                    item.message = validationMessage;
                    this.error = validationMessage;

                    return;
                }

                item.status = 'uploading';
                item.message = 'Enviando imagem...';
                this.dispatchFormEvent('form-processing-started', {
                    message: 'Enviando imagem...',
                });

                uploadUsing(
                    item.fileKey,
                    file,
                    () => {
                        item.status = 'uploaded';
                        item.progress = 100;
                        item.message = 'Imagem carregada com sucesso.';
                        this.finishProcessingIfIdle();
                    },
                    (message) => {
                        item.status = 'error';
                        item.message = message || 'Não foi possível carregar esta imagem. Tente novamente.';
                        this.error = item.message;
                        this.finishProcessingIfIdle();
                    },
                    (progress) => {
                        item.progress = progress;
                    },
                );
            },

            finishProcessingIfIdle() {
                if (this.items.some((item) => item.status === 'uploading')) {
                    return;
                }

                this.dispatchFormEvent('form-processing-finished');
            },

            removeItem(item) {
                item.removed = true;

                if (item.previewUrl) {
                    URL.revokeObjectURL(item.previewUrl);
                }

                if (item.fileKey && item.status === 'uploaded') {
                    removeUsing(item.fileKey);
                }

                this.items = this.items.filter((current) => current.id !== item.id);
            },
        };
    };
</script>
