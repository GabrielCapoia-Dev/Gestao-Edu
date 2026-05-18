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
    <button
        type="button"
        x-on:click="openCamera()"
        class="inline-flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
    >
        <x-heroicon-o-camera class="h-5 w-5" />
        Tirar foto agora
    </button>

    <div
        x-show="isOpen"
        x-cloak
        x-on:keydown.escape.window="closeCamera()"
        class="fixed inset-0 z-50 flex items-center justify-center bg-gray-950/75 p-4"
    >
        <div class="w-full max-w-2xl rounded-xl bg-white p-4 shadow-xl dark:bg-gray-900">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Tirar foto</h2>

                <button
                    type="button"
                    x-on:click="closeCamera()"
                    class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-gray-800 dark:hover:text-gray-200"
                    aria-label="Fechar camera"
                >
                    <x-heroicon-o-x-mark class="h-5 w-5" />
                </button>
            </div>

            <video
                x-ref="video"
                autoplay
                playsinline
                muted
                class="aspect-video w-full rounded-lg bg-black object-contain"
            ></video>

            <p
                x-show="error"
                x-text="error"
                class="mt-3 rounded-lg bg-danger-50 px-3 py-2 text-sm text-danger-700 dark:bg-danger-500/10 dark:text-danger-300"
            ></p>

            <div class="mt-4 flex flex-wrap justify-end gap-2">
                <button
                    type="button"
                    x-on:click="closeCamera()"
                    class="rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-medium text-gray-700 transition hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-900 dark:text-gray-200 dark:hover:bg-gray-800"
                >
                    Cancelar
                </button>

                <button
                    type="button"
                    x-on:click="capturePhoto()"
                    class="inline-flex items-center justify-center gap-2 rounded-lg bg-primary-600 px-3 py-2 text-sm font-medium text-white transition hover:bg-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-500 focus:ring-offset-2"
                >
                    <x-heroicon-o-camera class="h-5 w-5" />
                    Usar foto
                </button>
            </div>
        </div>
    </div>
</div>

<script>
    window.pedidoCameraUpload = window.pedidoCameraUpload || function (target, maxSizeBytes, acceptedMimeTypes) {
        return {
            acceptedExtensions: ['jpeg', 'jpg', 'png', 'webp'],
            error: null,
            hasCamera: false,
            isOpen: false,
            stream: null,

            async init() {
                this.configureUploadValidationMessage();

                if (!navigator.mediaDevices?.getUserMedia) {
                    return;
                }

                this.hasCamera = true;

                if (!navigator.mediaDevices?.enumerateDevices) {
                    return;
                }

                try {
                    const devices = await navigator.mediaDevices.enumerateDevices();
                    const videoInputs = devices.filter((device) => device.kind === 'videoinput');

                    this.hasCamera = videoInputs.length > 0;
                } catch (error) {
                    this.hasCamera = true;
                }
            },

            configureUploadValidationMessage() {
                let attempts = 0;

                const configure = () => {
                    const uploadElement = document.querySelector(`[data-camera-upload-target="${target}"]`);
                    const uploadData = uploadElement && window.Alpine ? window.Alpine.$data(uploadElement) : null;

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

            async openCamera() {
                this.error = null;
                this.isOpen = true;

                try {
                    this.stream = await navigator.mediaDevices.getUserMedia({
                        video: {
                            facingMode: { ideal: 'environment' },
                            width: { ideal: 1280 },
                            height: { ideal: 720 },
                        },
                        audio: false,
                    });

                    this.$refs.video.srcObject = this.stream;
                    await this.$refs.video.play();
                } catch (error) {
                    this.error = 'Nao foi possivel acessar a camera deste dispositivo.';
                    this.stopStream();
                }
            },

            closeCamera() {
                this.isOpen = false;
                this.error = null;
                this.stopStream();
            },

            stopStream() {
                if (!this.stream) {
                    return;
                }

                this.stream.getTracks().forEach((track) => track.stop());
                this.stream = null;

                if (this.$refs.video) {
                    this.$refs.video.srcObject = null;
                }
            },

            async capturePhoto() {
                const video = this.$refs.video;

                if (!video?.videoWidth || !video?.videoHeight) {
                    this.error = 'A camera ainda nao esta pronta para capturar a foto.';

                    return;
                }

                const canvas = document.createElement('canvas');
                canvas.width = video.videoWidth;
                canvas.height = video.videoHeight;

                canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.92));

                if (!blob) {
                    this.error = 'Nao foi possivel gerar a foto.';

                    return;
                }

                if (!acceptedMimeTypes.includes(blob.type)) {
                    this.error = 'Formato incorreto. Use JPEG, JPG, PNG ou WEBP.';

                    return;
                }

                if (blob.size > maxSizeBytes) {
                    this.error = 'Tamanho acima de 5 MB. Tente tirar a foto novamente.';

                    return;
                }

                const uploadElement = document.querySelector(`[data-camera-upload-target="${target}"]`);
                const uploadData = uploadElement && window.Alpine ? window.Alpine.$data(uploadElement) : null;

                if (!uploadData?.pond) {
                    this.error = 'Nao foi possivel anexar a foto ao pedido.';

                    return;
                }

                const file = new File([blob], `foto-${new Date().toISOString().replace(/[:.]/g, '-')}.jpg`, {
                    type: 'image/jpeg',
                });

                await uploadData.pond.addFile(file);
                this.closeCamera();
            },
        };
    };
</script>
