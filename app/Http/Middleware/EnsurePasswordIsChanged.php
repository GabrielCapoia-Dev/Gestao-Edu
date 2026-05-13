<?php

namespace App\Http\Middleware;

use App\Filament\Admin\Pages\ForcePasswordChange;
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

        return redirect()->to(ForcePasswordChange::getUrl());
    }

    private function rotaPermitida(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if ($routeName === ForcePasswordChange::getRouteName()) {
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
