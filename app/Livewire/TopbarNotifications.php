<?php

namespace App\Livewire;

use App\Filament\Admin\Pages\CentralNotificacoes;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\On;
use Livewire\Component;

class TopbarNotifications extends Component
{
    public int $unread = 0;

    public function mount(): void
    {
        $this->fetchUnreadCount();
    }

    public function refresh(): void
    {
        $this->fetchUnreadCount();
    }

    #[On('refresh-notifications')]
    public function onRefreshNotifications(): void
    {
        $this->fetchUnreadCount();
    }

    private function fetchUnreadCount(): void
    {
        $user = Auth::user();

        if (! $user) {
            $this->unread = 0;

            return;
        }

        $this->unread = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();
    }

    public function render()
    {
        return view('livewire.topbar-notifications', [
            'centralUrl' => CentralNotificacoes::getUrl(),
        ]);
    }
}
