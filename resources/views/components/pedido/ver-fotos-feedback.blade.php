<!-- resources\views\components\pedido\ver-fotos-feedback.blade.php -->
@props([
'feedback',
])

@php
$fotos = $feedback->fotos;
@endphp

@php
$fotos = $pedido->fotos;
$id = 'modal-fotos-' . $pedido->id;
@endphp

<style>
    /* ===== MODAL DE FOTOS - ESTILO PROFISSIONAL ===== */

    .modal-fotos-container {
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
    }

    /* ===== IMAGEM PRINCIPAL ===== */
    .modal-fotos-image-wrapper {
        display: flex;
        justify-content: center;
        align-items: center;
        background: linear-gradient(135deg, #f8f9fa 0%, #f0f2f5 100%);
        border-radius: 12px;
        padding: 2rem;
        min-height: 450px;
        position: relative;
        overflow: hidden;
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.05);
    }

    .dark .modal-fotos-image-wrapper {
        background: linear-gradient(135deg, #1f2937 0%, #111827 100%);
        box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.3);
    }

    .modal-fotos-image-wrapper img {
        max-height: 70vh;
        max-width: 100%;
        object-fit: contain;
        border-radius: 8px;
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.15);
        transition: transform 0.3s ease, box-shadow 0.3s ease;
    }

    .dark .modal-fotos-image-wrapper img {
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.5);
    }

    .modal-fotos-image-wrapper img:hover {
        transform: scale(1.02);
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.2);
    }

    .dark .modal-fotos-image-wrapper img:hover {
        box-shadow: 0 15px 40px rgba(0, 0, 0, 0.6);
    }

    /* ===== CONTROLES (BOTÕES) ===== */
    .modal-fotos-controls {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 1.5rem;
        padding: 1rem 0;
    }

    .modal-fotos-counter {
        font-size: 0.875rem;
        font-weight: 500;
        color: #6b7280;
        background: #f3f4f6;
        padding: 0.5rem 1rem;
        border-radius: 20px;
        min-width: 80px;
        text-align: center;
        transition: all 0.3s ease;
    }

    .dark .modal-fotos-counter {
        color: #9ca3af;
        background: #374151;
    }

    .modal-fotos-counter:hover {
        background: #e5e7eb;
    }

    .dark .modal-fotos-counter:hover {
        background: #4b5563;
    }

    .modal-fotos-counter span {
        font-weight: 600;
        color: #1f2937;
    }

    .dark .modal-fotos-counter span {
        color: #f3f4f6;
    }

    /* ===== BOTÕES DE NAVEGAÇÃO ===== */
    .modal-fotos-btn {
        display: inline-flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.625rem 1.25rem;
        background: white;
        border: 1.5px solid #e5e7eb;
        border-radius: 8px;
        font-size: 0.875rem;
        font-weight: 500;
        color: #374151;
        cursor: pointer;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
    }

    .dark .modal-fotos-btn {
        background: #374151;
        border-color: #4b5563;
        color: #f3f4f6;
    }

    .modal-fotos-btn:hover {
        background: #f3f4f6;
        border-color: #d1d5db;
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    }

    .dark .modal-fotos-btn:hover {
        background: #4b5563;
        border-color: #6b7280;
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
    }

    .modal-fotos-btn:active {
        transform: translateY(0);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    /* ===== MINIATURAS ===== */
    .modal-fotos-thumbnails-wrapper {
        padding-top: 1rem;
        border-top: 1px solid #e5e7eb;
    }

    .dark .modal-fotos-thumbnails-wrapper {
        border-top-color: #374151;
    }

    .modal-fotos-thumbnails {
        display: flex;
        gap: 0.75rem;
        overflow-x: auto;
        padding: 0.5rem 0;
        scroll-behavior: smooth;
    }

    /* Scroll suave em todos os navegadores */
    .modal-fotos-thumbnails::-webkit-scrollbar {
        height: 6px;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 10px;
    }

    .dark .modal-fotos-thumbnails::-webkit-scrollbar-track {
        background: #374151;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 10px;
        transition: background 0.3s ease;
    }

    .dark .modal-fotos-thumbnails::-webkit-scrollbar-thumb {
        background: #6b7280;
    }

    .modal-fotos-thumbnails::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .dark .modal-fotos-thumbnails::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .modal-fotos-thumbnail {
        flex: 0 0 auto;
        width: 100px;
        height: 70px;
        object-fit: cover;
        border-radius: 6px;
        cursor: pointer;
        border: 2px solid #e5e7eb;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
    }

    .dark .modal-fotos-thumbnail {
        border-color: #4b5563;
    }

    .modal-fotos-thumbnail:hover {
        transform: scale(1.05);
        border-color: #d1d5db;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
    }

    .dark .modal-fotos-thumbnail:hover {
        border-color: #6b7280;
        box-shadow: 0 4px 8px rgba(0, 0, 0, 0.3);
    }

    .modal-fotos-thumbnail.active {
        border-color: #3b82f6;
        border-width: 2px;
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1), 0 4px 12px rgba(59, 130, 246, 0.2);
        transform: scale(1.1);
    }

    .dark .modal-fotos-thumbnail.active {
        box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2), 0 4px 12px rgba(59, 130, 246, 0.3);
    }

    /* ===== MENSAGEM DE VAZIO ===== */
    .modal-fotos-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 3rem 2rem;
        text-align: center;
        color: #9ca3af;
    }

    .dark .modal-fotos-empty {
        color: #6b7280;
    }

    .modal-fotos-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 1rem;
        opacity: 0.5;
    }

    .modal-fotos-empty p {
        font-size: 0.875rem;
        margin: 0;
    }

    /* ===== RESPONSIVIDADE ===== */
    @media (max-width: 768px) {
        .modal-fotos-image-wrapper {
            min-height: 300px;
            padding: 1rem;
        }

        .modal-fotos-image-wrapper img {
            max-height: 50vh;
        }

        .modal-fotos-controls {
            flex-direction: column;
            gap: 1rem;
            padding: 0.75rem 0;
        }

        .modal-fotos-btn {
            width: 100%;
            justify-content: center;
        }

        .modal-fotos-thumbnail {
            width: 80px;
            height: 60px;
        }

        .modal-fotos-container {
            gap: 1rem;
        }
    }

    @media (max-width: 480px) {
        .modal-fotos-image-wrapper {
            min-height: 250px;
            padding: 0.75rem;
            border-radius: 8px;
        }

        .modal-fotos-image-wrapper img {
            max-height: 40vh;
            border-radius: 6px;
        }

        .modal-fotos-counter {
            padding: 0.375rem 0.75rem;
            font-size: 0.75rem;
            min-width: 70px;
        }

        .modal-fotos-btn {
            padding: 0.5rem 1rem;
            font-size: 0.8125rem;
        }

        .modal-fotos-thumbnail {
            width: 70px;
            height: 50px;
        }

        .modal-fotos-thumbnails {
            gap: 0.5rem;
        }
    }

    /* ===== ANIMAÇÕES ===== */
    @keyframes fadeIn {
        from {
            opacity: 0;
            transform: scale(0.95);
        }

        to {
            opacity: 1;
            transform: scale(1);
        }
    }

    .modal-fotos-image-wrapper img {
        animation: fadeIn 0.3s ease;
    }

    /* ===== ACESSIBILIDADE ===== */
    .modal-fotos-btn:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    .modal-fotos-thumbnail:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
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
            Fotos do Pedido
        </x-slot>

        <div class="modal-fotos-container" x-data="{ index: 0 }">

            @if($fotos->count())

            <!-- IMAGEM PRINCIPAL -->
            <div class="modal-fotos-image-wrapper">
                <img
                    :src="[
                            @foreach($fotos as $foto)
                                '{{ Storage::url($foto->caminho) }}',
                            @endforeach
                        ][index]"
                    alt="Foto do pedido">
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

            @else

            <div class="modal-fotos-empty">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <p>Nenhuma foto cadastrada para este pedido.</p>
            </div>

            @endif

        </div>

    </x-filament::modal>

</div>