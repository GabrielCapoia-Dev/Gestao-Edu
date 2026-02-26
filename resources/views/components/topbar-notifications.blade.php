@php
$unread = 12;
$id = 'modal-notificacoes-' . uniqid();
@endphp

<style>
    .notification-bell {
        position: relative;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        background-color: #081124;
        color: #f3f4f6;
        border-radius: 50%;
        transition: background-color 0.2s ease;
        cursor: pointer;
        border: none;
    }

    .notification-bell:hover {
        background-color: #111827;
    }

    .notification-bell:focus-visible {
        outline: 2px solid #3b82f6;
        outline-offset: 2px;
    }

    .notification-badge {
        position: absolute;
        display: flex;
        align-items: center;
        justify-content: center;
        width: 24px;
        height: 24px;
        top: -8px;
        right: -8px;
        background-color: #ef4444;
        color: #ffffff;
        border-radius: 50%;
        font-size: 11px;
        font-weight: bold;
        box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        transition: all 0.2s ease;
    }

    .modal-notificacoes-container {
        height: 500px;
        overflow-y: auto;
        overflow-x: hidden;
    }

    .modal-notificacoes-container::-webkit-scrollbar {
        width: 6px;
    }

    .modal-notificacoes-container::-webkit-scrollbar-track {
        background: #f3f4f6;
        border-radius: 10px;
    }

    .dark .modal-notificacoes-container::-webkit-scrollbar-track {
        background: #374151;
    }

    .modal-notificacoes-container::-webkit-scrollbar-thumb {
        background: #d1d5db;
        border-radius: 10px;
    }

    .dark .modal-notificacoes-container::-webkit-scrollbar-thumb {
        background: #6b7280;
    }

    .modal-notificacoes-container::-webkit-scrollbar-thumb:hover {
        background: #9ca3af;
    }

    .card-notification {
        padding: 5px;
    }
</style>

<div class="flex items-center">
    <button
        type="button"
        aria-label="Notificações. {{ $unread }} não lidas"
        class="notification-bell"
        x-on:click="$dispatch('open-modal', { id: '{{ $id }}' })">

        <x-heroicon-o-bell style="width: 20px; height: 20px;" />

        @if($unread > 0)
        <span class="notification-badge">
            {{ $unread > 99 ? '99+' : $unread }}
        </span>
        @endif
    </button>

    <!-- MODAL FILAMENT -->
    <x-filament::modal
        :id="$id"
        width="4xl">

        <x-slot name="heading">
            Notificações
        </x-slot>

        <div class="modal-notificacoes-container space-y-3 pr-2 card-notification">
            @forelse(range(1, 50) as $notification)
            <div class="flex items-start gap-3 p-3 bg-gray-50 dark:bg-gray-900 rounded-lg border border-gray-200 dark:border-gray-700 hover:bg-gray-100 dark:hover:bg-gray-800 transition-colors cursor-pointer">
                <div class="flex-shrink-0 w-2 h-2 mt-2 bg-blue-500 rounded-full"></div>
                <div class="flex-1 min-w-0">
                    <p class="text-sm font-medium text-gray-900 dark:text-gray-100">
                        Notificação #{{ $notification }}
                    </p>
                    <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                        Lorem ipsum dolor sit amet, consectetur adipiscing elit.
                    </p>
                    <p class="text-xs text-gray-500 dark:text-gray-500 mt-2">
                        Há 2 horas
                    </p>
                </div>
            </div>
            @empty
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <x-heroicon-o-bell class="w-12 h-12 text-gray-400 dark:text-gray-600 mb-2" />
                <p class="text-gray-600 dark:text-gray-400">Nenhuma notificação</p>
            </div>
            @endforelse
        </div>

    </x-filament::modal>
</div>