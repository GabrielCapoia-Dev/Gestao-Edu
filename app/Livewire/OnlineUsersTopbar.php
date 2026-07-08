<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\UserPresenceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class OnlineUsersTopbar extends Component
{
    public bool $open = false;

    public function mount(): void
    {
        abort_unless(auth()->user() && Gate::forUser(auth()->user())->allows('viewOnlineUsers', User::class), 403);
    }

    public function togglePanel(): void
    {
        $this->open = ! $this->open;
    }

    public function closePanel(): void
    {
        $this->open = false;
    }

    public function render()
    {
        $presence = app(UserPresenceService::class);
        $onlineCount = $presence->onlineCount();
        $onlineUsers = collect();
        $offlineUsers = collect();

        if ($this->open) {
            $onlineUsers = $presence->onlineUsers();
            $offlineUsers = $presence->offlineUsers();
        }

        return view('livewire.online-users-topbar', [
            'onlineUsers' => $onlineUsers,
            'offlineUsers' => $offlineUsers,
            'onlineCount' => $onlineCount,
            'totalUsers' => $this->open ? $presence->totalUsersCount() : $onlineCount,
        ]);
    }

    public function formatLastLogin(?Carbon $lastLoginAt): string
    {
        return $lastLoginAt?->format('d/m/Y H:i') ?? 'Nunca acessou';
    }
}
