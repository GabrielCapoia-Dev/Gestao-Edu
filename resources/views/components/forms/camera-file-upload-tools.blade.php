@php
    $target = $target ?? 'pedido-fotos';
    $maxSizeBytes = $maxSizeBytes ?? \App\Support\PedidoFotoUpload::MAX_SIZE_BYTES;
    $acceptedMimeTypes = $acceptedMimeTypes ?? \App\Support\PedidoFotoUpload::acceptedMimeTypes();
@endphp

<div
    x-data="pedidoCameraUpload(@js($target), @js($maxSizeBytes), @js($acceptedMimeTypes))"
    x-init="init()"
    x-show="hasCamera"
    x-cloak
    class="fi-camera-upload-tools -mt-2 mb-2 flex flex-wrap items-center gap-2"
>
    <input
        x-ref="cameraInput"
        type="file"
        accept="image/*"
        capture="environment"
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
        x-on:change="handleCameraFile($event)"
    />

    <input
        x-ref="galleryInput"
        type="file"
        accept="image/jpeg,image/jpg,image/png,image/webp"
        multiple
        class="sr-only"
        tabindex="-1"
        aria-hidden="true"
        x-on:change="handleGalleryFiles($event)"
    />

    <div class="flex w-full flex-wrap gap-2 sm:w-auto">
        <button
            type="button"
            x-on:click="openCameraApp()"
            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 sm:flex-none"
        >
            <x-heroicon-o-camera class="h-5 w-5" />
            Tirar foto
        </button>

        <button
            type="button"
            x-on:click="openGalleryApp()"
            class="inline-flex min-h-10 flex-1 items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800 sm:flex-none"
        >
            <x-heroicon-o-photo class="h-5 w-5" />
            Galeria
        </button>
    </div>
</div>

<script>
    window.pedidoCameraUpload = window.pedidoCameraUpload || function (target, maxSizeBytes, acceptedMimeTypes) {
        return {
            acceptedExtensions: ['jpeg', 'jpg', 'png', 'webp'],
            hasCamera: false,

            async init() {
                this.configureUploadValidationMessage();

                if (!this.supportsNativeCapture()) {
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

                        const message = this.makeUploadErrorMessage(fileItem?.file);

                        if (message) {
                            uploadData.error = message;
                        }
                    });
                };

                configure();
            },

            getUploadData() {
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
                        return window.Alpine ? window.Alpine.$data(scopedUpload) : null;
                    }
                }

                const uploadElement = document.querySelector(targetSelector);

                return uploadElement && window.Alpine ? window.Alpine.$data(uploadElement) : null;
            },

            makeUploadErrorMessage(file) {
                if (!file) {
                    return null;
                }

                const extension = (file.name.split('.').pop() || '').toLowerCase();
                const mimeType = (file.type || '').toLowerCase();
                const hasInvalidFormat = !this.acceptedExtensions.includes(extension)
                    || !acceptedMimeTypes.includes(mimeType);
                const hasInvalidSize = file.size > maxSizeBytes;

                if (!hasInvalidFormat && !hasInvalidSize) {
                    return null;
                }

                if (hasInvalidFormat && hasInvalidSize) {
                    return `A foto "${file.name}" nao foi enviada: formato incorreto e tamanho acima de 5 MB. Use JPEG, JPG, PNG ou WEBP com ate 5 MB.`;
                }

                if (hasInvalidFormat) {
                    return `A foto "${file.name}" nao foi enviada: formato incorreto. Use JPEG, JPG, PNG ou WEBP.`;
                }

                return `A foto "${file.name}" nao foi enviada: tamanho acima de 5 MB. Envie uma imagem com ate 5 MB.`;
            },

            openCameraApp() {
                this.$refs.cameraInput.value = '';
                this.$refs.cameraInput.click();
            },

            openGalleryApp() {
                this.$refs.galleryInput.value = '';
                this.$refs.galleryInput.click();
            },

            async handleCameraFile(event) {
                const file = event.target.files?.[0];

                if (!file) {
                    return;
                }

                const uploadData = this.getUploadData();
                const validationMessage = this.makeUploadErrorMessage(file);

                if (validationMessage) {
                    if (uploadData) {
                        uploadData.error = validationMessage;
                    }

                    return;
                }

                if (!uploadData?.pond) {
                    return;
                }

                await uploadData.pond.addFile(file);
            },

            async handleGalleryFiles(event) {
                const files = Array.from(event.target.files || []);

                if (files.length === 0) {
                    return;
                }

                const uploadData = this.getUploadData();

                if (!uploadData?.pond) {
                    return;
                }

                for (const file of files) {
                    const validationMessage = this.makeUploadErrorMessage(file);

                    if (validationMessage) {
                        uploadData.error = validationMessage;

                        continue;
                    }

                    await uploadData.pond.addFile(file);
                }
            },
        };
    };
</script>
