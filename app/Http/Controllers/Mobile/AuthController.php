<?php

namespace App\Http\Controllers\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function showInstall(Request $request): View
    {
        $user = $request->user();
        $shareUrl = route('mobile.install.short');

        return view('mobile.auth.install', [
            'shareUrl' => $shareUrl,
            'whatsAppUrl' => 'https://wa.me/?text='.rawurlencode(
                "Instale o Gestão Edu Mobile no seu celular: {$shareUrl}"
            ),
            'entryUrl' => $user ? route('mobile.home') : route('mobile.login'),
            'entryLabel' => $user ? 'Abrir app mobile' : 'Entrar no app mobile',
        ]);
    }

    public function showLogin(Request $request): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('mobile.home');
        }

        return view('mobile.auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->route('mobile.home');
        }

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $credentials['email'] = Str::lower($credentials['email']);
        $throttleKey = $this->throttleKey($request, $credentials['email']);

        if (RateLimiter::tooManyAttempts($throttleKey, 8)) {
            $seconds = RateLimiter::availableIn($throttleKey);

            return back()
                ->withErrors([
                    'email' => "Muitas tentativas. Tente novamente em {$seconds} segundos.",
                ])
                ->onlyInput('email');
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($throttleKey, 60);

            return back()
                ->withErrors([
                    'email' => 'Credenciais invalidas. Confira o e-mail e a senha.',
                ])
                ->onlyInput('email');
        }

        RateLimiter::clear($throttleKey);
        $request->session()->regenerate();

        $user = $request->user();

        if (! $user?->email_approved) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return back()
                ->withErrors([
                    'email' => 'Seu acesso ainda aguarda aprovacao do administrador.',
                ])
                ->onlyInput('email');
        }

        return redirect()->intended(route('mobile.home'));
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('mobile.login');
    }

    private function throttleKey(Request $request, string $email): string
    {
        return 'login:'.sha1(Str::lower($email).'|'.$request->ip());
    }
}
