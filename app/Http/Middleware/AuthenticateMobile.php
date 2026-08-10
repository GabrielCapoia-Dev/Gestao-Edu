<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMobile
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->guest(route('mobile.login'));
        }

        $user = $request->user();

        if (! $user?->canAuthenticate()) {
            Auth::logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->route('mobile.login')
                ->withErrors([
                    'email' => 'Seu cadastro de servidor está inativo. Entre em contato com o administrador.',
                ]);
        }

        return $next($request);
    }
}
