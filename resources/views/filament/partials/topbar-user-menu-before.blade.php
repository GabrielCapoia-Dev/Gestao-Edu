@if ($showOnlineUsers)
    <livewire:online-users-topbar />
@endif

@if ($showNotifications)
    @include('livewire.topbar-notifications-hook')
@endif
