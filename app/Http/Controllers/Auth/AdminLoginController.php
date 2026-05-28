<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    public function show(Request $request): View | RedirectResponse
    {
        $panel = $this->setAdminPanel();

        if ($panel->auth()->check()) {
            return $this->redirectAuthenticatedUser($request);
        }

        return view('auth.admin-login', [
            'googleLoginUrl' => route('google.redirect'),
            'loginAction' => route('admin.login.store'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $panel = $this->setAdminPanel();

        if ($panel->auth()->check()) {
            return $this->redirectAuthenticatedUser($request);
        }

        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable'],
        ], [
            'email.required' => 'Informe o email.',
            'email.email' => 'Informe um email valido.',
            'password.required' => 'Informe a senha.',
        ]);

        $throttleKey = $this->throttleKey($request, $data['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            throw ValidationException::withMessages([
                'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
            ]);
        }

        $credentials = [
            'email' => Str::lower($data['email']),
            'password' => $data['password'],
        ];

        $remember = $request->boolean('remember');

        if (! $panel->auth()->attemptWhen($credentials, function ($user) use ($panel): bool {
            if (! $user instanceof FilamentUser) {
                return true;
            }

            return $user->canAccessPanel($panel);
        }, $remember)) {
            RateLimiter::hit($throttleKey, 60);

            throw ValidationException::withMessages([
                'email' => 'As credenciais informadas nao conferem.',
            ]);
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        /** @var User|null $user */
        $user = $panel->auth()->user();

        if ($user?->must_change_password) {
            return redirect()->route('auth.force-password.edit');
        }

        return redirect()->intended($panel->getUrl());
    }

    private function redirectAuthenticatedUser(Request $request): RedirectResponse
    {
        $panel = $this->setAdminPanel();

        /** @var User|null $user */
        $user = $panel->auth()->user();

        if ($user?->must_change_password) {
            return redirect()->route('auth.force-password.edit');
        }

        return redirect()->intended($panel->getUrl());
    }

    private function setAdminPanel(): \Filament\Panel
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        return $panel;
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'admin-login:' . sha1(Str::lower($email) . '|' . $request->ip());
    }
}
