<?php

namespace App\Livewire;

use Livewire\Component;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class TopbarNotifications extends Component
{
    public $unread = 0;
    public $notifications = [];

    public function mount(): void
    {
        $this->fetchNotifications();
    }

    public function refresh(): void  // <- wire:poll chama este
    {
        $this->fetchNotifications();
    }

    #[\Livewire\Attributes\On('refresh-notifications')]
    public function onRefreshNotifications(): void
    {
        $this->fetchNotifications();
    }

    private function fetchNotifications(): void  // <- private, não exposto
    {
        $user = Auth::user();

        $this->notifications = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->orderBy('created_at', 'desc')
            ->limit(50)
            ->get()
            ->map(function ($notification) {
                $notification->created_at = Carbon::parse($notification->created_at);
                return $notification;
            })
            ->toArray();

        $this->unread = DB::table('notifications')
            ->where('notifiable_id', $user->id)
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->count();
    }

    public function markAllAsRead(): void
    {
        DB::table('notifications')
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', User::class)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        $this->fetchNotifications();
    }

    public function markAsRead($id): void
    {
        DB::table('notifications')
            ->where('id', $id)
            ->where('notifiable_id', Auth::id())
            ->where('notifiable_type', User::class)
            ->update(['read_at' => now()]);

        $this->fetchNotifications();
    }
    public function render()
    {
        return view('livewire.topbar-notifications');
    }
}
