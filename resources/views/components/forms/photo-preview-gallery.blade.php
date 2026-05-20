@props([
    'target' => 'pedido-fotos',
    'columns' => 3,
])

@once
    <style>
        .photo-gallery {
            margin-top: 0.75rem;
        }

        .photo-gallery__label {
            font-size: 0.8125rem;
            font-weight: 600;
            color: #334155;
            margin-bottom: 0.5rem;
            display: block;
        }

        .dark .photo-gallery__label {
            color: #cbd5e1;
        }

        .photo-gallery__grid {
            display: grid;
            gap: 0.625rem;
            grid-template-columns: repeat(var(--photo-gallery-cols, 3), minmax(0, 1fr));
        }

        .photo-gallery__item {
            position: relative;
            border-radius: 8px;
            overflow: hidden;
            cursor: pointer;
            aspect-ratio: 4 / 3;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            transition: transform 0.2s ease, box-shadow 0.2s ease;
        }

        .dark .photo-gallery__item {
            background: #1e293b;
            border-color: #334155;
        }

        .photo-gallery__item:hover {
            transform: scale(1.03);
            box-shadow: 0 8px 20px rgba(0, 0, 0, 0.15);
        }

        .photo-gallery__item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .photo-gallery__remove {
            position: absolute;
            top: 4px;
            right: 4px;
            width: 24px;
            height: 24px;
            border-radius: 50%;
            border: none;
            background: rgba(0, 0, 0, 0.55);
            color: #fff;
            font-size: 14px;
            line-height: 24px;
            text-align: center;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
            padding: 0;
            z-index: 2;
        }

        .photo-gallery__remove:hover {
            background: rgba(220, 38, 38, 0.85);
        }

        .photo-gallery__remove svg {
            width: 14px;
            height: 14px;
        }

        .photo-gallery__fullscreen {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.94);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            z-index: 9999;
            padding: 1rem;
            animation: fadeIn 0.18s ease;
        }

        .photo-gallery__fullscreen img {
            max-width: 94vw;
            max-height: 88vh;
            object-fit: contain;
            border-radius: 6px;
        }

        .photo-gallery__fullscreen-close {
            position: absolute;
            top: 1.25rem;
            right: 1.25rem;
            width: 40px;
            height: 40px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: #fff;
            font-size: 1.5rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
            z-index: 10000;
        }

        .photo-gallery__fullscreen-close:hover {
            background: rgba(255, 255, 255, 0.25);
        }

        .photo-gallery__fullscreen-nav {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            width: 44px;
            height: 44px;
            border-radius: 8px;
            border: none;
            background: rgba(255, 255, 255, 0.12);
            color: #fff;
            font-size: 1.25rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.15s ease;
        }

        .photo-gallery__fullscreen-nav:hover {
            background: rgba(255, 255, 255, 0.22);
        }

        .photo-gallery__fullscreen-nav--prev {
            left: 1.25rem;
        }

        .photo-gallery__fullscreen-nav--next {
            right: 1.25rem;
        }

        .photo-gallery__fullscreen-counter {
            position: absolute;
            bottom: 1.25rem;
            left: 50%;
            transform: translateX(-50%);
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.8125rem;
            font-weight: 600;
            background: rgba(0, 0, 0, 0.5);
            padding: 0.4rem 0.9rem;
            border-radius: 6px;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        @media (max-width: 768px) {
            .photo-gallery__grid {
                --photo-gallery-cols: 2;
            }

            .photo-gallery__fullscreen-nav {
                width: 36px;
                height: 36px;
                font-size: 1rem;
            }

            .photo-gallery__fullscreen-nav--prev { left: 0.75rem; }
            .photo-gallery__fullscreen-nav--next { right: 0.75rem; }
        }

        @media (max-width: 480px) {
            .photo-gallery__grid {
                --photo-gallery-cols: 2;
                gap: 0.375rem;
            }
        }
    </style>
@endonce

<div
    x-data="photoPreviewGallery(@js($target))"
    x-init="init()"
    x-show="files.length > 0"
    x-cloak
    class="photo-gallery"
>
    <span class="photo-gallery__label">Pré-visualização das fotos</span>

    <div class="photo-gallery__grid">
        <template x-for="(file, index) in files" :key="file.id">
            <div class="photo-gallery__item">
                <img :src="file.url" :alt="file.name" x-on:click="openFullscreen(index)">
                <button
                    type="button"
                    class="photo-gallery__remove"
                    x-on:click.stop="removeFile(index)"
                    title="Remover foto"
                >
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </template>
    </div>

    <template x-if="fullscreenIndex !== null">
        <div class="photo-gallery__fullscreen" x-on:click="closeFullscreen()">
            <button
                type="button"
                class="photo-gallery__fullscreen-close"
                x-on:click.stop="closeFullscreen()"
                aria-label="Fechar"
            >✕</button>

            <button
                type="button"
                class="photo-gallery__fullscreen-nav photo-gallery__fullscreen-nav--prev"
                x-on:click.stop="prevFullscreen()"
                aria-label="Anterior"
            >←</button>

            <div x-on:click.stop>
                <img :src="files[fullscreenIndex]?.url" :alt="files[fullscreenIndex]?.name">
            </div>

            <button
                type="button"
                class="photo-gallery__fullscreen-nav photo-gallery__fullscreen-nav--next"
                x-on:click.stop="nextFullscreen()"
                aria-label="Próxima"
            >→</button>

            <span class="photo-gallery__fullscreen-counter" x-text="`${fullscreenIndex + 1} / ${files.length}`"></span>
        </div>
    </template>
</div>

<script>
    window.photoPreviewGallery = function (target) {
        return {
            files: [],
            fullscreenIndex: null,
            pond: null,
            _listenersAttached: false,

            init() {
                const tryConnect = () => {
                    const uploadElement = document.querySelector(`[data-camera-upload-target="${target}"]`);
                    if (!uploadElement) { setTimeout(tryConnect, 250); return; }

                    const uploadData = window.Alpine.$data(uploadElement);
                    if (!uploadData?.pond) { setTimeout(tryConnect, 250); return; }

                    this.pond = uploadData.pond;
                    this.attachListeners();
                    this.syncFromPond();
                };

                setTimeout(tryConnect, 300);
            },

            attachListeners() {
                if (this._listenersAttached) return;
                this._listenersAttached = true;

                this.pond.on('addfile', (error, fileItem) => {
                    if (error) return;
                    this.addFileToGallery(fileItem);
                });

                this.pond.on('removefile', (error, fileItem) => {
                    if (error) return;
                    this.removeFileFromGallery(fileItem);
                });
            },

            syncFromPond() {
                this.files = [];
                this.pond.getFiles().forEach(fileItem => {
                    this.addFileToGallery(fileItem);
                });
            },

            async addFileToGallery(fileItem) {
                const url = await fileItem.getFileEncodeDataURL();
                if (this.files.some(f => f.id === fileItem.id)) return;
                this.files.push({
                    id: fileItem.id,
                    url: url,
                    name: fileItem.filename || 'Foto',
                });
            },

            removeFileFromGallery(fileItem) {
                this.files = this.files.filter(f => f.id !== fileItem.id);
            },

            removeFile(index) {
                const file = this.files[index];
                if (!file || !this.pond) return;
                const fileItem = this.pond.getFiles().find(f => f.id === file.id);
                if (fileItem) {
                    this.pond.removeFile(fileItem);
                } else {
                    this.files.splice(index, 1);
                }
            },

            openFullscreen(index) {
                this.fullscreenIndex = index;
            },

            closeFullscreen() {
                this.fullscreenIndex = null;
            },

            prevFullscreen() {
                if (this.files.length === 0) return;
                this.fullscreenIndex = (this.fullscreenIndex - 1 + this.files.length) % this.files.length;
            },

            nextFullscreen() {
                if (this.files.length === 0) return;
                this.fullscreenIndex = (this.fullscreenIndex + 1) % this.files.length;
            },
        };
    };
</script>
