<?php

namespace App\Http\Controllers\Auth;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AdminLoginController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $panel = $this->setAdminPanel();

        if ($panel->auth()->check()) {
            return $this->redirectAuthenticatedUser($request);
        }

        return view('auth.admin-login', [
            'googleLoginUrl' => route('google.redirect'),
            'loginAction' => route('admin.login.store'),
            'loginNotices' => $this->pullLoginNotices($request),
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
                'email' => 'As credenciais informadas não conferem.',
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

    private function setAdminPanel(): Panel
    {
        $panel = Filament::getPanel('admin');
        Filament::setCurrentPanel($panel);

        return $panel;
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'admin-login:'.sha1(Str::lower($email).'|'.$request->ip());
    }

    /**
     * @return array<int, array{title: string, message: string, type: string}>
     */
    private function pullLoginNotices(Request $request): array
    {
        $notices = collect($request->session()->pull('filament.notifications', []))
            ->map(function (mixed $notification): ?array {
                if (! is_array($notification)) {
                    return null;
                }

                $title = trim(strip_tags((string) ($notification['title'] ?? '')));
                $message = trim(strip_tags((string) ($notification['body'] ?? '')));

                if ($title === '' && $message === '') {
                    return null;
                }

                return [
                    'title' => $title !== '' ? $title : $this->defaultNoticeTitle($notification['status'] ?? null),
                    'message' => $message,
                    'type' => $this->normalizeNoticeType($notification['status'] ?? null),
                ];
            })
            ->filter()
            ->values();

        foreach ($this->flashNoticeDefinitions() as $key => $definition) {
            $message = $request->session()->pull($key);

            foreach (Arr::wrap($message) as $item) {
                if (! is_scalar($item)) {
                    continue;
                }

                $text = trim(strip_tags((string) $item));

                if ($text === '') {
                    continue;
                }

                $notices->push([
                    'title' => $definition['title'],
                    'message' => $text,
                    'type' => $definition['type'],
                ]);
            }
        }

        return $notices
            ->unique(fn (array $notice): string => implode('|', $notice))
            ->values()
            ->all();
    }

    /**
     * @return array<string, array{title: string, type: string}>
     */
    private function flashNoticeDefinitions(): array
    {
        return [
            'status' => ['title' => 'Tudo certo', 'type' => 'success'],
            'success' => ['title' => 'Tudo certo', 'type' => 'success'],
            'warning' => ['title' => 'Atenção', 'type' => 'warning'],
            'error' => ['title' => 'Não foi possível continuar', 'type' => 'danger'],
            'message' => ['title' => 'Informação', 'type' => 'info'],
            'session_expired' => ['title' => 'Sessão expirada', 'type' => 'warning'],
        ];
    }

    private function normalizeNoticeType(mixed $status): string
    {
        $status = Str::lower((string) $status);

        return match ($status) {
            'success', 'warning', 'danger', 'info' => $status,
            'error' => 'danger',
            default => 'info',
        };
    }

    private function defaultNoticeTitle(mixed $status): string
    {
        return match ($this->normalizeNoticeType($status)) {
            'success' => 'Tudo certo',
            'warning' => 'Atenção',
            'danger' => 'Não foi possível continuar',
            default => 'Informação',
        };
    }
}
