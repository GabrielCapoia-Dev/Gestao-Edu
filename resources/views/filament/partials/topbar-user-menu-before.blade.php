@if (filled($userName))
    <span
        class="topbar-user-name {{ $showOnlineUsers ? 'topbar-user-name--with-online-users' : '' }}"
        title="{{ $userName }}"
        aria-label="Usuário conectado: {{ $userName }}"
    >
        <span class="topbar-user-name__value">{{ $userName }}</span>
    </span>
@endif

@if ($missingCpf ?? false)
    <span
        class="topbar-profile-cpf-alert"
        title="CPF não informado"
        aria-label="CPF não informado. Acesse seu perfil para preencher."
    >!</span>
@endif

@if ($showOnlineUsers)
    <livewire:online-users-topbar />
@endif

<livewire:export-queue-topbar />

@if ($showNotifications)
    @include('livewire.topbar-notifications-hook')
@endif
