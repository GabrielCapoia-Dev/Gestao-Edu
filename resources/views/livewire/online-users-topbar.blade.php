@php
    $onlineUsersPollSeconds = $open
        ? (int) config('performance.livewire_polling.online_users', 30)
        : (int) config('performance.livewire_polling.online_users_closed', 120);
@endphp

<div class="online-users-wrapper" wire:poll.visible.{{ $onlineUsersPollSeconds }}s="$refresh">
    <button
        type="button"
        class="online-users-card"
        wire:click="togglePanel"
        aria-expanded="{{ $open ? 'true' : 'false' }}"
        aria-label="Ver usuários online"
        title="Usuários online"
    >
        <span class="online-users-dot" aria-hidden="true"></span>
        <span class="online-users-count">{{ $onlineCount }}</span>
        <span class="online-users-label">online</span>
    </button>

    @if ($open)
        <div class="online-users-popover" wire:click.outside="closePanel">
            <div class="online-users-popover-header">
                <div>
                    <strong>Usuários</strong>
                    <span>{{ $onlineCount }} de {{ $totalUsers }} online</span>
                </div>

                <button
                    type="button"
                    wire:click="closePanel"
                    aria-label="Fechar lista de usuários"
                >
                    <x-heroicon-o-x-mark />
                </button>
            </div>

            <div class="online-users-list">
                <section>
                    <h3>Online agora</h3>

                    @forelse ($onlineUsers as $user)
                        <div class="online-user-row is-online" wire:key="online-user-{{ $user->id }}">
                            <span class="online-user-status" aria-hidden="true"></span>
                            <div>
                                <strong>{{ $user->name }}</strong>
                                <span>{{ $user->email }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="online-users-empty">Nenhum usuário online.</p>
                    @endforelse
                </section>

                <section>
                    <h3>Ultimos acessos offline</h3>

                    @forelse ($offlineUsers as $user)
                        <div class="online-user-row is-offline" wire:key="offline-user-{{ $user->id }}">
                            <span class="online-user-status" aria-hidden="true"></span>
                            <div>
                                <strong>{{ $user->name }}</strong>
                                <span>{{ $this->formatLastLogin($user->last_login_at) }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="online-users-empty">Nenhum usuário offline.</p>
                    @endforelse
                </section>
            </div>
        </div>
    @endif
</div>
