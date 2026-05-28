<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->rotaPermitida($request)) {
            return $next($request);
        }

        return redirect()->route('auth.force-password.edit');
    }

    private function rotaPermitida(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === 'auth.force-password.edit') {
            return true;
        }

        if ($routeName === 'auth.force-password.update') {
            return true;
        }

        if ($request->is('admin/alterar-senha-obrigatoria') || $request->is('admin/alterar-senha-obrigatoria/salvar')) {
            return true;
        }

        if (str_contains($routeName, 'filament.admin.auth.logout')) {
            return true;
        }

        if ($request->is('livewire/*')) {
            $refererPath = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH) ?: '';

            return str_starts_with($refererPath, '/admin/alterar-senha-obrigatoria');
        }

        return false;
    }
}
