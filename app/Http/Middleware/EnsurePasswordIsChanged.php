<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\ProfilePreviewService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordIsChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $preview = app(ProfilePreviewService::class);
        $user = $request->user();
        $controlUser = $preview->controlUser() ?: $user;

        if ($controlUser instanceof User && ! $controlUser->canAuthenticate()) {
            return $this->encerrarSessao(
                $request,
                'Esta conta está inativa ou arquivada. Entre em contato com o administrador.',
            );
        }

        if (
            $controlUser instanceof User
            && (int) $request->session()->get('auth_version', 0) !== (int) $controlUser->auth_version
        ) {
            return $this->encerrarSessao(
                $request,
                'Sua sessão foi encerrada por uma alteração de segurança. Entre novamente.',
            );
        }

        if ($preview->isActive()) {
            return $next($request);
        }

        if (! $user instanceof User || ! $user->must_change_password) {
            return $next($request);
        }

        if ($this->rotaPermitida($request)) {
            return $next($request);
        }

        return redirect()->route('auth.force-password.edit');
    }

    private function encerrarSessao(Request $request, string $mensagem): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()
            ->route('filament.admin.auth.login')
            ->withErrors(['email' => $mensagem]);
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
