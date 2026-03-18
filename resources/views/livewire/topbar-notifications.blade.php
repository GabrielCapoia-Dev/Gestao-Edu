@php
$id = 'modal-notificacoes';
@endphp

<style>
    .notif-wrapper {
        display: inline-flex;
        align-items: center;
    }

    .notif-bell-btn {
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

    .notif-bell-btn:hover {
        background-color: #0f1f3d;
    }

    .notification-badge {
        position: absolute;
        top: -4px;
        right: -4px;
        min-width: 16px;
        height: 16px;
        padding: 0 3px;
        background: #ef4444;
        color: #fff;
        font-size: 10px;
        font-weight: 700;
        border-radius: 9999px;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        line-height: 1;
    }

    .notif-heading {
        display: flex;
        align-items: center;
        justify-content: space-between;
        width: 100%;
    }

    .notif-heading-left {
        display: flex;
        align-items: center;
        gap: 0.75rem;
    }

    .notif-title {
        font-size: 1rem;
        font-weight: 600;
        color: #111827;
    }

    .notif-count-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 26px;
        height: 26px;
        padding: 0 8px;
        font-size: 0.75rem;
        font-weight: 700;
        color: #fff;
        background: linear-gradient(to right, #ef4444, #ec4899);
        border-radius: 9999px;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.2);
        animation: notif-pulse 1.5s ease-in-out infinite;
    }

    @keyframes notif-pulse {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.6; }
    }

    .notif-mark-all-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 28px;
        height: 28px;
        border-radius: 50%;
        border: none;
        cursor: pointer;
        background: #e5e7eb;
        transition: background 0.2s;
    }

    .notif-mark-all-btn:hover {
        background: #d1d5db;
    }

    .notif-unread-label {
        font-size: 0.75rem;
        color: #6b7280;
    }

    .notif-list {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
        padding-right: 0.5rem;
        max-height: 500px;
        overflow-y: auto;
    }

    .notif-item {
        display: flex;
        align-items: flex-start;
        gap: 0.75rem;
        padding: 0.75rem;
        border-radius: 0.5rem;
        border: 1px solid #e5e7eb;
    }

    .notif-item.unread {
        background-color: #f9fafb;
    }

    .notif-item.read {
        background-color: #fff;
        opacity: 0.7;
    }

    .notif-dot {
        width: 8px;
        height: 8px;
        min-width: 8px;
        margin-top: 6px;
        background-color: #3b82f6;
        border-radius: 50%;
    }

    .notif-body {
        flex: 1;
        position: relative;
    }

    .notif-body-title {
        font-size: 0.875rem;
        font-weight: 700;
        color: #111827;
        margin: 0 0 4px 0;
    }

    .notif-body-msg {
        font-size: 0.875rem;
        color: #374151;
        margin: 0 0 6px 0;
    }

    .notif-link {
        display: inline-flex;
        align-items: center;
        gap: 0.25rem;
        font-size: 0.875rem;
        font-weight: 500;
        color: #2563eb;
        text-decoration: none;
        transition: color 0.2s ease;
    }

    .notif-link:hover {
        color: #1d4ed8;
        text-decoration: underline;
    }

    .notif-check-btn {
        position: absolute;
        top: 0;
        right: 0;
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
    }

    .notif-check-btn:hover {
        background-color: #d1d5db;
    }

    .notif-time {
        font-size: 0.75rem;
        color: #9ca3af;
        margin: 6px 0 0 0;
    }

    .notif-empty {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 2rem 1rem;
        text-align: center;
        color: #9ca3af;
        font-size: 0.875rem;
    }

    .notif-empty svg {
        width: 48px;
        height: 48px;
        margin-bottom: 0.5rem;
        color: #d1d5db;
    }

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
        to { transform: rotate(360deg); }
    }
</style>

<div class="notif-wrapper" wire:poll.10s="loadNotifications">

    <button
        type="button"
        class="notif-bell-btn"
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
            <div class="notif-heading">
                <div class="notif-heading-left">
                    <span class="notif-title">Notificações</span>

                    @if($unread > 0)
                        <span class="notif-count-badge">{{ $unread }}</span>

                        {{-- Botão: marcar TODAS como lidas --}}
                        <button
                            type="button"
                            class="notif-mark-all-btn"
                            wire:click="markAllAsRead"
                            wire:loading.class="btn-loading"
                            title="Marcar todas como lidas">
                            <span wire:loading.remove wire:target="markAllAsRead">
                                <x-heroicon-o-check-badge style="width:16px;height:16px;color:#374151;" />
                            </span>
                            <span wire:loading wire:target="markAllAsRead">
                                <div class="spinner"></div>
                            </span>
                        </button>
                    @endif
                </div>

                @if($unread > 0)
                    <span class="notif-unread-label">
                        {{ $unread === 1 ? '1 não lida' : $unread . ' não lidas' }}
                    </span>
                @endif
            </div>
        </x-slot>

        <div class="notif-list">

            @forelse($notifications as $notification)
                @php
                    $data = json_decode($notification->data, true) ?? $notification->data;
                    $isUnread = is_null($notification->read_at);
                @endphp

                <div wire:key="notif-{{ $notification->id }}" class="notif-item {{ $isUnread ? 'unread' : 'read' }}">

                    @if($isUnread)
                        <div class="notif-dot"></div>
                    @endif

                    <div class="notif-body">
                        <p class="notif-body-title">{{ $data['titulo'] ?? '' }}</p>
                        <p class="notif-body-msg">{{ $data['mensagem'] ?? '' }}</p>

                        <a href="{{ $data['url'] ?? '' }}" target="_blank" class="notif-link">
                            {{ $data['label'] ?? 'Ver detalhes' }}
                            <x-heroicon-o-arrow-top-right-on-square style="width:14px;height:14px;" />
                        </a>

                        @if($isUnread)
                            {{-- Botão: marcar INDIVIDUAL como lida --}}
                            <button
                                type="button"
                                class="notif-check-btn"
                                wire:click="markAsRead('{{ $notification->id }}')"
                                wire:loading.class="btn-loading"
                                title="Marcar como lida">
                                <span wire:loading.remove wire:target="markAsRead('{{ $notification->id }}')">
                                    <x-heroicon-o-check style="width:16px;height:16px;color:#374151;" />
                                </span>
                                <span wire:loading wire:target="markAsRead('{{ $notification->id }}')">
                                    <div class="spinner"></div>
                                </span>
                            </button>
                        @endif

                        <p class="notif-time">
                            {{ \Carbon\Carbon::parse($notification->created_at)->diffForHumans() }}
                        </p>
                    </div>
                </div>

            @empty
                <div class="notif-empty">
                    <x-heroicon-o-bell />
                    <p>Nenhuma notificação</p>
                </div>
            @endforelse

        </div>
    </x-filament::modal>
</div>