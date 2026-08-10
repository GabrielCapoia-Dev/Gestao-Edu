<?php

namespace App\Livewire;

use App\Models\User;
use App\Services\ProfilePreviewService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProfilePreviewTopbar extends Component
{
    public ?int $targetUserId = null;

    public function mount(ProfilePreviewService $preview): void
    {
        abort_unless($preview->canControl(), 403);
    }

    public function render(ProfilePreviewService $preview): View
    {
        return view('livewire.profile-preview-topbar', [
            'active' => $preview->isActive(),
            'targetUser' => $preview->targetUser(),
            'users' => User::query()
                ->canAuthenticate()
                ->orderBy('name')
                ->limit(250)
                ->get(['id', 'name', 'email']),
        ]);
    }
}
