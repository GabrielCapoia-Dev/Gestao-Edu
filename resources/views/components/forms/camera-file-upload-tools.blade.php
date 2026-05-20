@php
    $target = $target ?? 'pedido-fotos';
    $maxFiles = $maxFiles ?? 10;
    $maxSizeBytes = $maxSizeBytes ?? \App\Support\PedidoFotoUpload::MAX_SIZE_BYTES;
    $acceptedMimeTypes = $acceptedMimeTypes ?? \App\Support\PedidoFotoUpload::acceptedMimeTypes();
    $cameraInputId = 'camera-upload-' . \Illuminate\Support\Str::uuid();
    $galleryInputId = 'gallery-upload-' . \Illuminate\Support\Str::uuid();
@endphp

@once
    <style>
        .pedido-camera-upload[x-cloak] {
            display: none !important;
        }

        .pedido-camera-upload {
            display: grid;
            gap: 0.75rem;
            margin: 0 0 0.5rem;
        }

        .pedido-camera-upload-host--hidden {
            height: 0 !important;
            margin: 0 !important;
            min-height: 0 !important;
            opacity: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
            position: absolute !important;
            width: 1px !important;
        }

        .pedido-camera-upload__dropzone {
            align-items: center;
            background: #ffffff;
            border: 1px dashed #cbd5e1;
            border-radius: 10px;
            color: #475569;
            cursor: pointer;
            display: grid;
            gap: 0.45rem;
            justify-items: center;
            min-height: 6rem;
            padding: 1rem;
            text-align: center;
            transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
        }

        .pedido-camera-upload__dropzone:hover,
        .pedido-camera-upload__dropzone--dragging {
            background: #f8fafc;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.10);
        }

        .pedido-camera-upload__dropzone-icon {
            align-items: center;
            background: #eff6ff;
            border-radius: 999px;
            color: #1d4ed8;
            display: inline-flex;
            height: 2.25rem;
            justify-content: center;
            width: 2.25rem;
        }

        .pedido-camera-upload__dropzone-icon svg {
            height: 1.25rem !important;
            width: 1.25rem !important;
        }

        .pedido-camera-upload__dropzone-title {
            color: #1f2937;
            font-size: 0.92rem;
            font-weight: 700;
            line-height: 1.25rem;
        }

        .pedido-camera-upload__dropzone-text {
            color: #64748b;
            font-size: 0.78rem;
            line-height: 1.15rem;
        }

        .pedido-camera-upload__native-input {
            height: 1px !important;
            left: -9999px !important;
            opacity: 0 !important;
            overflow: hidden !important;
            pointer-events: none !important;
            position: fixed !important;
            top: auto !important;
            width: 1px !important;
        }

        .pedido-camera-upload__actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .pedido-camera-upload__button {
            align-items: center;
            background: #ffffff;
            border: 1px solid #cbd5e1;
            border-radius: 8px;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.08);
            color: #1f2937;
            cursor: pointer;
            display: inline-flex;
            font-size: 0.875rem;
            font-weight: 650;
            gap: 0.45rem;
            justify-content: center;
            line-height: 1.25rem;
            min-height: 2.5rem;
            padding: 0.55rem 0.85rem;
            transition: background-color 160ms ease, border-color 160ms ease, box-shadow 160ms ease;
            user-select: none;
        }

        .pedido-camera-upload__button:hover {
            background: #f8fafc;
            border-color: #94a3b8;
        }

        .pedido-camera-upload__button:focus {
            box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.22);
            outline: none;
        }

        .pedido-camera-upload__button svg {
            flex: 0 0 auto;
            height: 1.15rem !important;
            width: 1.15rem !important;
        }

        .pedido-camera-upload__preview-grid {
            display: grid;
            gap: 0.65rem;
            grid-template-columns: repeat(2, minmax(0, 1fr));
        }

        .pedido-camera-upload__preview {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            box-shadow: 0 6px 18px rgba(15, 23, 42, 0.06);
            min-width: 0;
            overflow: hidden;
            transition: border-color 160ms ease, box-shadow 160ms ease;
        }

        .pedido-camera-upload__preview--ok {
            border-color: #10b981;
            box-shadow: 0 0 0 1px rgba(16, 185, 129, 0.28), 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pedido-camera-upload__preview--error {
            border-color: #ef4444;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.28), 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pedido-camera-upload__preview--checking {
            border-color: #f59e0b;
            box-shadow: 0 0 0 1px rgba(245, 158, 11, 0.25), 0 6px 18px rgba(15, 23, 42, 0.06);
        }

        .pedido-camera-upload__media {
            background: #f1f5f9;
            position: relative;
        }

        .pedido-camera-upload__media img,
        .pedido-camera-upload__empty-media {
            aspect-ratio: 4 / 3;
            display: block;
            height: auto;
            object-fit: cover;
            width: 100%;
        }

        .pedido-camera-upload__empty-media {
            align-items: center;
            color: #64748b;
            display: flex;
            font-size: 0.82rem;
            justify-content: center;
        }

        .pedido-camera-upload__status {
            align-items: center;
            border-radius: 999px;
            box-shadow: 0 4px 10px rgba(15, 23, 42, 0.22);
            color: #ffffff;
            display: inline-flex;
            font-size: 0.88rem;
            font-weight: 800;
            height: 1.75rem;
            justify-content: center;
            position: absolute;
            right: 0.5rem;
            top: 0.5rem;
            width: 1.75rem;
        }

        .pedido-camera-upload__status--ok {
            background: #059669;
        }

        .pedido-camera-upload__status--error {
            background: #dc2626;
        }

        .pedido-camera-upload__status--checking {
            background: #d97706;
        }

        .pedido-camera-upload__spinner {
            animation: pedido-camera-upload-spin 0.8s linear infinite;
            border: 2px solid rgba(255, 255, 255, 0.45);
            border-radius: 999px;
            border-top-color: #ffffff;
            display: inline-block;
            height: 0.9rem;
            width: 0.9rem;
        }

        .pedido-camera-upload__body {
            display: grid;
            gap: 0.2rem;
            padding: 0.6rem 0.65rem;
        }

        .pedido-camera-upload__meta {
            align-items: flex-start;
            display: flex;
            gap: 0.5rem;
            justify-content: space-between;
            min-width: 0;
        }

        .pedido-camera-upload__name {
            color: #0f172a;
            font-size: 0.82rem;
            font-weight: 700;
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .pedido-camera-upload__size {
            color: #64748b;
            flex: 0 0 auto;
            font-size: 0.74rem;
            line-height: 1.15rem;
        }

        .pedido-camera-upload__message {
            font-size: 0.76rem;
            line-height: 1.15rem;
            margin: 0;
        }

        .pedido-camera-upload__message--ok {
            color: #047857;
        }

        .pedido-camera-upload__message--error {
            color: #b91c1c;
        }

        .pedido-camera-upload__message--checking {
            color: #b45309;
        }

        .dark .pedido-camera-upload__button,
        .dark .pedido-camera-upload__preview,
        .dark .pedido-camera-upload__dropzone {
            background: #111827;
            border-color: #334155;
            color: #e5e7eb;
        }

        .dark .pedido-camera-upload__button:hover,
        .dark .pedido-camera-upload__dropzone:hover,
        .dark .pedido-camera-upload__dropzone--dragging {
            background: #1f2937;
            border-color: #475569;
        }

        .dark .pedido-camera-upload__dropzone-icon {
            background: #172554;
            color: #bfdbfe;
        }

        .dark .pedido-camera-upload__dropzone-title {
            color: #f8fafc;
        }

        .dark .pedido-camera-upload__dropzone-text {
            color: #94a3b8;
        }

        .dark .pedido-camera-upload__media {
            background: #0f172a;
        }

        .dark .pedido-camera-upload__name {
            color: #f8fafc;
        }

        .dark .pedido-camera-upload__size,
        .dark .pedido-camera-upload__empty-media {
            color: #94a3b8;
        }

        @keyframes pedido-camera-upload-spin {
            to {
                transform: rotate(360deg);
            }
        }

        @media (max-width: 640px) {
            .pedido-camera-upload__actions,
            .pedido-camera-upload__button {
                width: 100%;
            }

            .pedido-camera-upload__button {
                flex: 1 1 0;
            }

            .pedido-camera-upload__preview-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
@endonce

<div
    x-data="pedidoCameraUpload(@js($target), @js($maxSizeBytes), @js($acceptedMimeTypes), @js($maxFiles))"
    x-init="init()"
    x-cloak
    class="pedido-camera-upload fi-camera-upload-tools"
>
    <input
        id="{{ $cameraInputId }}"
        x-ref="cameraInput"
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/webp"
        capture="environment"
        class="pedido-camera-upload__native-input"
        tabindex="-1"
        aria-hidden="true"
        x-on:click="resetNativeInput($event)"
        x-on:change="handleFiles($event, 'camera')"
    />

    <input
        id="{{ $galleryInputId }}"
        x-ref="galleryInput"
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/webp"
        multiple
        class="pedido-camera-upload__native-input"
        tabindex="-1"
        aria-hidden="true"
        x-on:click="resetNativeInput($event)"
        x-on:change="handleFiles($event, 'gallery')"
    />

    <label
        for="{{ $galleryInputId }}"
        class="pedido-camera-upload__dropzone"
        :class="{ 'pedido-camera-upload__dropzone--dragging': isDragging }"
        x-on:dragenter.prevent="isDragging = true"
        x-on:dragover.prevent="isDragging = true"
        x-on:dragleave.prevent="isDragging = false"
        x-on:drop.prevent="handleDrop($event)"
    >
        <span class="pedido-camera-upload__dropzone-icon">
            <x-heroicon-o-cloud-arrow-up />
        </span>

        <span class="pedido-camera-upload__dropzone-title">
            Arraste as fotos aqui ou clique para selecionar
        </span>

        <span class="pedido-camera-upload__dropzone-text">
            JPEG, JPG, PNG ou WEBP. Até {{ $maxFiles }} imagens de 5 MB.
        </span>
    </label>

    <div class="pedido-camera-upload__actions">
        <label
            for="{{ $cameraInputId }}"
            role="button"
            tabindex="0"
            x-on:keydown.enter.prevent="$refs.cameraInput.click()"
            x-on:keydown.space.prevent="$refs.cameraInput.click()"
            class="pedido-camera-upload__button"
            x-show="hasCamera"
        >
            <x-heroicon-o-camera class="h-5 w-5" />
            Tirar foto
        </label>

        <label
            for="{{ $galleryInputId }}"
            role="button"
            tabindex="0"
            x-on:keydown.enter.prevent="$refs.galleryInput.click()"
            x-on:keydown.space.prevent="$refs.galleryInput.click()"
            class="pedido-camera-upload__button"
        >
            <x-heroicon-o-photo class="h-5 w-5" />
            Galeria
        </label>
    </div>

    <template x-if="items.length > 0">
        <div class="pedido-camera-upload__preview-grid">
            <template x-for="item in items" :key="item.id">
                <div
                    class="pedido-camera-upload__preview"
                    :class="{
                        'pedido-camera-upload__preview--ok': ['valid', 'uploaded'].includes(item.status),
                        'pedido-camera-upload__preview--error': item.status === 'error',
                        'pedido-camera-upload__preview--checking': item.status === 'checking',
                    }"
                >
                    <div class="pedido-camera-upload__media">
                        <template x-if="item.previewUrl">
                            <img
                                :src="item.previewUrl"
                                :alt="item.name"
                            />
                        </template>

                        <template x-if="! item.previewUrl">
                            <div class="pedido-camera-upload__empty-media">
                                Sem preview
                            </div>
                        </template>

                        <span
                            class="pedido-camera-upload__status"
                            :class="{
                                'pedido-camera-upload__status--ok': ['valid', 'uploaded'].includes(item.status),
                                'pedido-camera-upload__status--error': item.status === 'error',
                                'pedido-camera-upload__status--checking': item.status === 'checking',
                            }"
                        >
                            <span x-show="['valid', 'uploaded'].includes(item.status)">&#10003;</span>
                            <span x-show="item.status === 'error'">&times;</span>
                            <span x-show="item.status === 'checking'" class="pedido-camera-upload__spinner"></span>
                        </span>
                    </div>

                    <div class="pedido-camera-upload__body">
                        <div class="pedido-camera-upload__meta">
                            <span class="pedido-camera-upload__name" x-text="item.name"></span>
                            <span class="pedido-camera-upload__size" x-text="item.sizeLabel"></span>
                        </div>

                        <p
                            class="pedido-camera-upload__message"
                            :class="{
                                'pedido-camera-upload__message--ok': ['valid', 'uploaded'].includes(item.status),
                                'pedido-camera-upload__message--error': item.status === 'error',
                                'pedido-camera-upload__message--checking': item.status === 'checking',
                            }"
                            x-text="item.message"
                        ></p>
                    </div>
                </div>
            </template>
        </div>
    </template>
</div>

<script>
    window.pedidoCameraUpload = function (target, maxSizeBytes, acceptedMimeTypes, maxFiles) {
        return {
            acceptedExtensions: ['jpeg', 'jpg', 'png', 'webp'],
            hasCamera: false,
            isDragging: false,
            items: [],
            nextItemId: 1,

            async init() {
                this.configureUploadValidationMessage();
                this.concealNativeUpload();

                if (!this.supportsNativeCapture()) {
                    this.hasCamera = this.isLikelyCameraDevice();

                    return;
                }

                if (!navigator.mediaDevices?.enumerateDevices) {
                    this.hasCamera = this.isLikelyCameraDevice();

                    return;
                }

                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoInputs = devices.filter((device) => device.kind === 'videoinput');

                    this.hasCamera = videoInputs.length > 0 || this.isLikelyCameraDevice();
                } catch (error) {
                    this.hasCamera = this.isLikelyCameraDevice();
                }
            },

            supportsNativeCapture() {
                const input = document.createElement('input');
                input.type = 'file';

                return 'capture' in input;
            },

            isLikelyCameraDevice() {
                return navigator.maxTouchPoints > 0 || window.matchMedia('(pointer: coarse)').matches;
            },

            configureUploadValidationMessage() {
                let attempts = 0;

                const configure = () => {
                    const uploadData = this.getUploadData();

                    if (!uploadData?.pond) {
                        if (attempts < 50) {
                            attempts++;
                            setTimeout(configure, 100);
                        }

                        return;
                    }

                    uploadData.pond.on('addfile', (error, fileItem) => {
                        if (!error) {
                            return;
                        }

                        const message = this.makeUploadErrorMessage(fileItem?.file, uploadData);

                        if (message) {
                            uploadData.error = message;
                        }
                    });
                };

                configure();
            },

            concealNativeUpload() {
                let attempts = 0;

                const conceal = () => {
                    const uploadElement = this.getUploadElement();

                    if (!uploadElement) {
                        if (attempts < 50) {
                            attempts++;
                            setTimeout(conceal, 100);
                        }

                        return;
                    }

                    uploadElement.classList.add('pedido-camera-upload-host--hidden');
                };

                conceal();
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

            resetNativeInput(event) {
                event.target.value = '';
            },

            makeUploadErrorMessage(file, uploadData = null) {
                if (!file) {
                    return null;
                }

                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const mimeType = (file.type || '').toLowerCase();
                const hasInvalidFormat = !this.acceptedExtensions.includes(extension)
                    || !acceptedMimeTypes.includes(mimeType);
                const hasInvalidSize = file.size > maxSizeBytes;
                const pondFiles = uploadData?.pond?.getFiles?.().length || 0;
                const pendingFiles = this.items.filter((item) => ['valid', 'checking'].includes(item.status)).length;
                const hasTooManyFiles = (pondFiles + pendingFiles) > maxFiles;

                if (!hasInvalidFormat && !hasInvalidSize && !hasTooManyFiles) {
                    return null;
                }

                if (hasTooManyFiles) {
                    return `A foto "${file.name}" nao foi enviada: o limite e de ${maxFiles} imagens por envio.`;
                }

                if (hasInvalidFormat && hasInvalidSize) {
                    return `A foto "${file.name}" nao foi enviada: formato incorreto e tamanho acima de 5 MB. Use JPEG, JPG, PNG ou WEBP com ate 5 MB.`;
                }

                if (hasInvalidFormat) {
                    return `A foto "${file.name}" nao foi enviada: formato incorreto. Use JPEG, JPG, PNG ou WEBP.`;
                }

                return `A foto "${file.name}" nao foi enviada: tamanho acima de 5 MB. Envie uma imagem com ate 5 MB.`;
            },

            fileSizeLabel(file) {
                const size = file?.size || 0;

                if (size >= 1024 * 1024) {
                    return `${(size / 1024 / 1024).toFixed(1).replace('.', ',')} MB`;
                }

                return `${Math.max(1, Math.round(size / 1024))} KB`;
            },

            makePreviewItem(file, source) {
                const previewUrl = file.type?.startsWith('image/') ? URL.createObjectURL(file) : null;

                return {
                    id: this.nextItemId++,
                    name: file.name || (source === 'camera' ? 'foto-camera.jpg' : 'imagem.jpg'),
                    sizeLabel: this.fileSizeLabel(file),
                    previewUrl,
                    status: 'checking',
                    message: 'Verificando imagem...',
                };
            },

            async handleFiles(event, source) {
                const files = Array.from(event.target.files || []);
                event.target.value = '';

                if (files.length === 0) {
                    return;
                }

                for (const file of files) {
                    await this.queueFile(file, source);
                }
            },

            async handleDrop(event) {
                this.isDragging = false;

                const files = Array.from(event.dataTransfer?.files || []);

                if (files.length === 0) {
                    return;
                }

                for (const file of files) {
                    await this.queueFile(file, 'drop');
                }
            },

            async queueFile(file, source) {
                const uploadData = this.getUploadData();
                const item = this.makePreviewItem(file, source);

                this.items.unshift(item);

                await new Promise((resolve) => setTimeout(resolve, 120));

                const validationMessage = this.makeUploadErrorMessage(file, uploadData);

                if (validationMessage) {
                    item.status = 'error';
                    item.message = validationMessage;

                    if (uploadData) {
                        uploadData.error = validationMessage;
                    }

                    return;
                }

                if (!uploadData?.pond) {
                    item.status = 'error';
                    item.message = 'Nao foi possivel localizar o campo de upload. Tente novamente.';

                    return;
                }

                item.status = 'valid';
                item.message = 'Imagem valida. Enviando para o pedido...';

                try {
                    await uploadData.pond.addFile(file);
                    item.status = 'uploaded';
                    item.message = 'Imagem carregada com sucesso.';
                } catch (error) {
                    item.status = 'error';
                    item.message = this.makeUploadErrorMessage(file, uploadData)
                        || 'Nao foi possivel carregar esta imagem. Tente novamente.';
                }
            },
        };
    };
</script>
