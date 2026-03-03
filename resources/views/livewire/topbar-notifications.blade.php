@php
$id = 'modal-notificacoes';
@endphp

<div class="flex items-center">

    <button
        type="button"
        style="
            position: relative;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
            background-color: #111827;
            color: #f3f4f6;
            border-radius: 50%;
            transition: background-color 0.2s ease;
            cursor: pointer;
            border: none;
        "
        x-on:click="$dispatch('open-modal', { id: '{{ $id }}' })">

        <x-heroicon-o-bell style="width: 20px; height: 20px;" />

        @if($unread > 0)
        <span class="notification-badge">
            {{ $unread > 99 ? '99+' : $unread }}
        </span>
        @endif
    </button>

    <style>
        .btn-loading {
            pointer-events: none;
            opacity: 0.7;
        }

        .spinner {
            width: 16px;
            height: 16px;
            border: 2px solid #d1d5db;
            border-top: 2px solid #374151;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>

    <x-filament::modal :id="$id" width="4xl">
        <x-slot name="heading">
            <div class="flex items-center justify-between w-full">

                <div class="flex items-center gap-3">

                    <span class="text-base font-semibold">
                        Notificações
                    </span>

                    @if($unread > 0)
                    <span
                        class="inline-flex items-center justify-center min-w-[26px] h-[26px] px-2
                           text-xs font-bold text-white
                           bg-gradient-to-r from-red-500 to-pink-500
                           rounded-full shadow-md
                           animate-pulse">
                        {{ $unread }}
                    </span>
                    @endif

                </div>

                @if($unread > 0)
                <span class="text-xs text-gray-500 dark:text-gray-400">
                    {{ $unread === 1 ? '1 não lida' : $unread . ' não lidas' }}
                </span>
                @endif

            </div>
        </x-slot>

        <div class="modal-notificacoes-container space-y-3 pr-2 card-notification">

            @forelse($notifications as $notification)
            @php
            $data = json_decode($notification->data, true) ?? $notification->data;
            $isUnread = is_null($notification->read_at);
            @endphp

            <div class="flex items-start gap-3 p-3 rounded-lg border
                    {{ $isUnread ? 'bg-gray-50 dark:bg-gray-900' : 'bg-white dark:bg-gray-800 opacity-70' }}">

                @if($isUnread)
                <div class="w-2 h-2 mt-2 bg-blue-500 rounded-full"></div>
                @endif

                <div class="flex-1 relative">
                    <p class="text-sm font-bold">
                        {{ $data['titulo'] ?? '' }}
                    </p>

                    <p class="text-sm mt-1">
                        {{ $data['mensagem'] ?? '' }}
                    </p>

                    <a href="{{ $data['url'] ?? '' }}"
                        target="_blank"
                        style="
                                display: inline-flex;
                                align-items: center;
                                gap: 0.25rem;
                                font-size: 0.875rem;
                                font-weight: 500;
                                color: #2563eb;
                                transition: color 0.2s ease;
                                text-decoration: none;
                                outline: none;
                                cursor: pointer;
                        "
                        onmouseover="this.style.color = '#1d4ed8'; this.style.textDecoration = 'underline';"
                        onmouseout="this.style.color = '#2563eb'; this.style.textDecoration = 'none';">
                        {{ $data['label'] ?? 'Ver detalhes' }}
                        <x-heroicon-o-arrow-top-right-on-square style="width: 14px; height: 14px;" />
                    </a>

                    @if($isUnread)
                    <button
                        type="button"
                        onclick="handleNotificationClick(event, '{{ $notification->id }}')"
                        title="Marcar como lida"
                        style="
                                position: absolute;
                                top: 12px;
                                right: 12px;
                                display: inline-flex;
                                align-items: center;
                                justify-content: center;
                                width: 28px;
                                height: 28px;
                                border-radius: 50%;
                                border: none;
                                cursor: pointer;
                                background-color: #e5e7eb;
                                transition: background-color 0.2s ease;
                            "
                        onmouseover="this.style.backgroundColor='#d1d5db'"
                        onmouseout="this.style.backgroundColor='#e5e7eb'">
                        <x-heroicon-o-check style="width:16px;height:16px;color:#374151;" />
                    </button>
                    @endif

                    <p class="text-xs mt-2">
                        {{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}
                    </p>
                </div>
            </div>

            @empty
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <div style="width: 120px;">
                    <x-heroicon-o-bell class="w-12 h-12 text-gray-400 dark:text-gray-600 mb-2" />
                </div>
                <p class="text-gray-600 dark:text-gray-400">
                    Nenhuma notificação
                </p>
            </div>
            @endforelse

        </div>
    </x-filament::modal>
</div>

<script>
    function handleNotificationClick(event, id) {

        event.preventDefault();

        const button = event.currentTarget;
        const originalContent = button.innerHTML;

        // 🔥 transforma em loading
        button.classList.add('btn-loading');
        button.innerHTML = '<div class="spinner"></div>';

        const componentRoot = button.closest('[wire\\:id]');
        if (!componentRoot) return;

        const component = Livewire.find(componentRoot.getAttribute('wire:id'));
        if (!component) return;

        component.call('markAsRead', id).then(() => {

            // 🔥 restaura botão
            button.classList.remove('btn-loading');
            button.innerHTML = originalContent;

            // se for link, abre
            if (button.tagName.toLowerCase() === 'a') {
                window.open(button.href, '_blank');
            }

        }).catch(() => {

            // restaura mesmo se der erro
            button.classList.remove('btn-loading');
            button.innerHTML = originalContent;

        });
    }

    setInterval(() => {
        Livewire.dispatch('refresh-notifications');
    }, 10000);
</script>