@if (filled($userName))
    <span
        class="topbar-user-name {{ $showOnlineUsers ? 'topbar-user-name--with-online-users' : '' }}"
        title="{{ $userName }}"
        aria-label="Usuário conectado: {{ $userName }}"
    >
        <span class="topbar-user-name__value">{{ $userName }}</span>
    </span>
@endif

@if ($showOnlineUsers)
    <livewire:online-users-topbar />
@endif

<livewire:export-queue-topbar />

@if ($showNotifications)
    @include('livewire.topbar-notifications-hook')
@endif
