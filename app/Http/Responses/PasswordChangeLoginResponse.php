<?php

namespace App\Http\Responses;

use App\Models\User;
use Filament\Auth\Http\Responses\Contracts\LoginResponse as Responsable;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

class PasswordChangeLoginResponse implements Responsable
{
    public function toResponse($request): RedirectResponse | Redirector
    {
        $user = Filament::auth()->user() ?: $request->user();

        if ($user instanceof User && $user->must_change_password) {
            return redirect()->route('auth.force-password.edit');
        }

        return redirect()->intended(Filament::getUrl());
    }
}
