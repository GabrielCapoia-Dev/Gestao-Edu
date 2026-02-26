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
        x-on:open-modal.window="
            if ($event.detail.id === '{{ $id }}') {
                $wire.markAllAsRead()
            }
        "
        x-on:click="$dispatch('open-modal', { id: '{{ $id }}' })">

        <x-heroicon-o-bell style="width: 20px; height: 20px;" />

        @if($unread > 0)
        <span class="notification-badge">
            {{ $unread > 99 ? '99+' : $unread }}
        </span>
        @endif
    </button>

    <x-filament::modal :id="$id" width="4xl">
        <x-slot name="heading">
            Notificações
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

                <div class="flex-1">
                    <p class="text-sm font-medium">
                        {{ $data['titulo'] ?? '' }}
                    </p>

                    <p class="text-sm mt-1">
                        {{ $data['mensagem'] ?? '' }}
                    </p>

                    <p class="text-xs mt-2">
                        {{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}
                    </p>
                </div>
            </div>

            @empty
            <div class="flex flex-col items-center justify-center py-8 text-center">
                <x-heroicon-o-bell class="w-12 h-12 text-gray-400 dark:text-gray-600 mb-2" />
                <p class="text-gray-600 dark:text-gray-400">
                    Nenhuma notificação
                </p>
            </div>
            @endforelse

        </div>
    </x-filament::modal>
</div>

<script>
    setInterval(() => {
        Livewire.dispatch('refresh-notifications');
    }, 1000);
</script>