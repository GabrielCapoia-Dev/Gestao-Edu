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
    class="fi-camera-upload-tools -mt-2 mb-2"
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

    <button
        type="button"
        x-on:click="openCameraApp()"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
    >
        <x-heroicon-o-camera class="h-5 w-5" />
        Tirar foto agora
    </button>
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
                const uploadElement = document.querySelector(`[data-camera-upload-target="${target}"]`);

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
        };
    };
</script>
