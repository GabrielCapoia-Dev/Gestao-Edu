<?php

namespace App\Http\Middleware;

use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceAbsoluteSessionLifetime
{
    private const SESSION_KEY = 'auth.login_at';

    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return $next($request);
        }

        $lifetimeMinutes = (int) config('session.absolute_lifetime_minutes', 60);

        if ($lifetimeMinutes < 1) {
            return $next($request);
        }

        $loginAt = $this->loginTimestamp($request);

        if (now()->timestamp - $loginAt < ($lifetimeMinutes * 60)) {
            return $next($request);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Sessão expirada. Faça login novamente.',
            ], 401);
        }

        return redirect()->to(Filament::getLoginUrl())
            ->with('session_expired', 'Sua sessão expirou. Faça login novamente.');
    }

    private function loginTimestamp(Request $request): int
    {
        $loginAt = $request->session()->get(self::SESSION_KEY);

        if (is_numeric($loginAt)) {
            return (int) $loginAt;
        }

        $timestamp = now()->timestamp;
        $request->session()->put(self::SESSION_KEY, $timestamp);

        return $timestamp;
    }
}
