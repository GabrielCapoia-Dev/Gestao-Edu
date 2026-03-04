@props([
'fotos' => collect(),
'title' => 'Fotos'
])

@php
$fotos = $fotos ?? collect();
$id = 'modal-fotos-' . uniqid();
@endphp

<style>
    .modal-fotos-container {
        display: flex;
        flex-direction: column;
        gap: 1.25rem;
        height: 100%;
    }

    .modal-fotos-image-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, #f5f7fa 0%, #eef2f7 100%);
        border-radius: 10px;
        padding: 1.5rem;
        height: 420px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
        flex-shrink: 0;
    }

    .dark .modal-fotos-image-wrapper {
        background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.4);
    }

    .modal-fotos-image-wrapper img {
        max-height: 100%;
        max-width: 100%;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.12);
        transition: transform 0.25s cubic-bezier(0.4, 0, 0.2, 1), box-shadow 0.25s ease;
        cursor: pointer;
    }

    .dark .modal-fotos-image-wrapper img {
        box-shadow: 0 8px 24px rgba(0, 0, 0, 0.4);
    }

    .modal-fotos-image-wrapper img:hover {
        transform: scale(1.03);
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.16);
    }

    .dark .modal-fotos-image-wrapper img:hover {
        box-shadow: 0 12px 32px rgba(0, 0, 0, 0.5);
    }

    .modal-fotos-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1rem;
        padding: 0.75rem 0;
        flex-shrink: 0;
    }

    .modal-fotos-counter {
        font-size: 0.8125rem;
        font-weight: 600;
        color: #475569;
        background: #f1f5f9;
        padding: 0.625rem 1rem;
        border-radius: 6px;
        min-width: 75px;
        text-align: center;
        transition: all 0.2s ease;
    }

    .dark .modal-fotos-counter {
        color: #cbd5e1;
        background: #334155;
    }

    .modal-fotos-counter span {
        font-weight: 700;
        color: #1e293b;
        letter-spacing: -0.3px;
    }

    .dark .modal-fotos-counter span {
        color: #f1f5f9;
    }

    .modal-fotos-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.375rem;
        padding: 0.625rem 1.25rem;
        background: white;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        font-size: 0.8125rem;
        font-weight: 600;
        color: #334155;
        cursor: pointer;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
        flex: 1;
    }

    .dark .modal-fotos-btn {
        background: #334155;
        border-color: #475569;
        color: #e2e8f0;
    }

    .modal-fotos-btn:hover {
        background: #f8fafc;
        border-color: #cbd5e1;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.08);
    }

    .dark .modal-fotos-btn:hover {
        background: #475569;
        border-color: #64748b;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .modal-fotos-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .modal-fotos-btn-expand {
        padding: 0.625rem 0.875rem;
        flex: 0;
    }

    .modal-fotos-thumbnails-wrapper {
        padding-top: 0.75rem;
        border-top: 1px solid #e2e8f0;
        flex-shrink: 0;
    }

    .dark .modal-fotos-thumbnails-wrapper {
        border-top-color: #475569;
    }

    .modal-fotos-thumbnails {
        display: flex;
        gap: 0.625rem;
        overflow-x: auto;
        padding: 1rem;
        scroll-behavior: smooth;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar {
        height: 5px;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 10px;
    }

    .dark .modal-fotos-thumbnails::-webkit-scrollbar-track {
        background: #334155;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 10px;
    }

    .dark .modal-fotos-thumbnails::-webkit-scrollbar-thumb {
        background: #64748b;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .dark .modal-fotos-thumbnails::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }

    .modal-fotos-thumbnail {
        flex: 0 0 auto;
        width: 90px;
        height: 68px;
        object-fit: cover;
        border-radius: 6px;
        cursor: pointer;
        border: 2px solid transparent;
        transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.08);
        background: #f8fafc;
    }

    .dark .modal-fotos-thumbnail {
        background: #334155;
    }

    .modal-fotos-thumbnail:hover {
        transform: scale(1.08);
        border-color: #cbd5e1;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.12);
    }

    .dark .modal-fotos-thumbnail:hover {
        border-color: #64748b;
        box-shadow: 0 4px 10px rgba(0, 0, 0, 0.3);
    }

    .modal-fotos-thumbnail.active {
        border-color: #3b82f6;
        box-shadow: 0 0 0 2px #ffffff, 0 0 0 4px #3b82f6, 0 4px 12px rgba(59, 130, 246, 0.3);
        transform: scale(1.12);
    }

    .dark .modal-fotos-thumbnail.active {
        box-shadow: 0 0 0 2px #1e293b, 0 0 0 4px #3b82f6, 0 4px 12px rgba(59, 130, 246, 0.4);
    }

    .modal-fotos-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 420px;
        padding: 2rem;
        text-align: center;
        color: #94a3b8;
    }

    .dark .modal-fotos-empty {
        color: #64748b;
    }

    .modal-fotos-empty svg {
        width: 56px;
        height: 56px;
        margin-bottom: 1rem;
        opacity: 0.4;
    }

    .modal-fotos-empty p {
        font-size: 0.875rem;
        margin: 0;
        color: inherit;
    }

    /* Modal Fullscreen */
    .modal-fotos-fullscreen {
        position: fixed;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(0, 0, 0, 0.95);
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        padding: 1rem;
        animation: fadeIn 0.2s ease;
    }

    .modal-fotos-fullscreen-content {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        width: 100%;
        height: 100%;
        max-width: 95vw;
        max-height: 95vh;
    }

    .modal-fotos-fullscreen img {
        max-width: 100%;
        max-height: 90vh;
        object-fit: contain;
        border-radius: 8px;
    }

    .modal-fotos-fullscreen-close {
        position: absolute;
        top: 1.5rem;
        right: 1.5rem;
        background: rgba(255, 255, 255, 0.2);
        border: none;
        color: white;
        font-size: 1.75rem;
        width: 44px;
        height: 44px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        z-index: 10000;
    }

    .modal-fotos-fullscreen-close:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    @keyframes fadeIn {
        from {
            opacity: 0;
        }
        to {
            opacity: 1;
        }
    }

    @media (max-width: 768px) {
        .modal-fotos-container {
            gap: 1rem;
        }

        .modal-fotos-image-wrapper {
            height: 320px;
            padding: 1rem;
        }

        .modal-fotos-controls {
            flex-direction: row;
            gap: 0.75rem;
        }

        .modal-fotos-btn {
            padding: 0.5625rem 1rem;
            font-size: 0.75rem;
        }

        .modal-fotos-thumbnail {
            width: 75px;
            height: 56px;
        }

        .modal-fotos-thumbnails {
            gap: 0.5rem;
        }

        .modal-fotos-counter {
            min-width: 60px;
            padding: 0.5rem 0.75rem;
            font-size: 0.7rem;
        }

        .modal-fotos-empty {
            height: 320px;
            padding: 1.5rem;
        }
    }

    @media (max-width: 640px) {
        :root {
            --modal-max-width: 95vw !important;
        }

        .modal-fotos-image-wrapper {
            height: 280px;
            padding: 0.875rem;
        }

        .modal-fotos-btn {
            padding: 0.5rem 0.75rem;
            font-size: 0.6875rem;
        }

        .modal-fotos-counter {
            min-width: 55px;
            padding: 0.4375rem 0.625rem;
            font-size: 0.65rem;
        }

        .modal-fotos-thumbnail {
            width: 65px;
            height: 48px;
        }

        .modal-fotos-empty {
            height: 280px;
            padding: 1.25rem;
        }

        .modal-fotos-empty svg {
            width: 48px;
            height: 48px;
            margin-bottom: 0.75rem;
        }

        .modal-fotos-empty p {
            font-size: 0.8rem;
        }
    }

    @media (max-width: 480px) {
        .modal-fotos-container {
            gap: 0.875rem;
        }

        .modal-fotos-image-wrapper {
            height: 240px;
            padding: 0.75rem;
        }

        .modal-fotos-image-wrapper img {
            border-radius: 6px;
        }

        .modal-fotos-controls {
            gap: 0.5rem;
            padding: 0.375rem 0;
        }

        .modal-fotos-counter {
            padding: 0.375rem 0.5rem;
            font-size: 0.6rem;
            min-width: 50px;
        }

        .modal-fotos-btn {
            padding: 0.4375rem 0.625rem;
            font-size: 0.65rem;
        }

        .modal-fotos-btn-expand {
            padding: 0.4375rem 0.5rem;
        }

        .modal-fotos-thumbnail {
            width: 60px;
            height: 44px;
        }

        .modal-fotos-thumbnails {
            gap: 0.375rem;
        }

        .modal-fotos-thumbnails-wrapper {
            padding-top: 0.5rem;
        }

        .modal-fotos-empty {
            height: 240px;
            padding: 1rem;
        }

        .modal-fotos-empty svg {
            width: 40px;
            height: 40px;
            margin-bottom: 0.5rem;
        }

        .modal-fotos-empty p {
            font-size: 0.75rem;
        }

        .modal-fotos-fullscreen-close {
            top: 0.75rem;
            right: 0.75rem;
            width: 36px;
            height: 36px;
            font-size: 1.5rem;
        }
    }

    .modal-fotos-btn:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    .modal-fotos-thumbnail:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    /* Override Filament Modal Width on Mobile */
    @media (max-width: 768px) {
        :where([data-dialog-panel]) {
            --dialog-panel-max-inline-size: 95vw !important;
            max-width: 95vw !important;
        }
    }

    @media (max-width: 480px) {
        :where([data-dialog-panel]) {
            --dialog-panel-max-inline-size: 100vw !important;
            max-width: 100vw !important;
            margin: 0 !important;
            border-radius: 0 !important;
        }
    }
</style>

<div class="inline">

    <!-- BOTÃO -->
    <x-filament::button
        {{ $attributes }}
        size="xs"
        type="button"
        x-on:click="$dispatch('open-modal', { id: '{{ $id }}' })">
        Ver Fotos ({{ $fotos->count() }})
    </x-filament::button>

    <!-- MODAL FILAMENT -->
    <x-filament::modal
        :id="$id"
        width="5xl">

        <x-slot name="heading">
            {{ $title }}
        </x-slot>

        <div class="modal-fotos-container" x-data="{ index: 0, fullscreen: false }">

            @if($fotos->count())

            <!-- IMAGEM PRINCIPAL -->
            <div class="modal-fotos-image-wrapper">
                <img
                    :src="[
                            @foreach($fotos as $foto)
                                '{{ Storage::url($foto->caminho) }}',
                            @endforeach
                        ][index]"
                    alt="Foto"
                    x-on:click="fullscreen = true"
                    @click="fullscreen = true">
            </div>

            <!-- CONTROLES -->
            <div class="modal-fotos-controls">

                <button
                    type="button"
                    class="modal-fotos-btn"
                    x-on:click="index = (index - 1 + {{ $fotos->count() }}) % {{ $fotos->count() }}">
                    ← Anterior
                </button>

                <span class="modal-fotos-counter">
                    <span x-text="index + 1"></span>
                    / {{ $fotos->count() }}
                </span>

                <button
                    type="button"
                    class="modal-fotos-btn"
                    x-on:click="index = (index + 1) % {{ $fotos->count() }}">
                    Próxima →
                </button>

                <button
                    type="button"
                    class="modal-fotos-btn modal-fotos-btn-expand"
                    x-on:click="fullscreen = true"
                    title="Expandir imagem">
                    ⛶
                </button>

            </div>

            <!-- MINIATURAS -->
            <div class="modal-fotos-thumbnails-wrapper">
                <div class="modal-fotos-thumbnails">
                    @foreach($fotos as $i => $foto)
                    <img
                        src="{{ Storage::url($foto->caminho) }}"
                        alt="Miniatura {{ $i + 1 }}"
                        class="modal-fotos-thumbnail"
                        x-on:click="index = {{ $i }}"
                        :class="index === {{ $i }} ? 'active' : ''">
                    @endforeach
                </div>
            </div>

            <!-- FULLSCREEN OVERLAY -->
            <template x-if="fullscreen">
                <div class="modal-fotos-fullscreen" x-on:click="fullscreen = false" x-transition>
                    <button
                        type="button"
                        class="modal-fotos-fullscreen-close"
                        x-on:click.stop="fullscreen = false"
                        aria-label="Fechar">
                        ✕
                    </button>
                    <div class="modal-fotos-fullscreen-content" x-on:click.stop>
                        <img
                            :src="[
                                    @foreach($fotos as $foto)
                                        '{{ Storage::url($foto->caminho) }}',
                                    @endforeach
                                ][index]"
                            alt="Foto em tela cheia">
                    </div>
                </div>
            </template>

            @else

            <div class="modal-fotos-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p>Nenhuma foto cadastrada.</p>
            </div>

            @endif

        </div>

    </x-filament::modal>

</div>