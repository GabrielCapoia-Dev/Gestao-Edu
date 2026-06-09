<?php

namespace App\Http\Middleware;

use App\Services\ProfilePreviewService;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BlockProfilePreviewWrites
{
    public function handle(Request $request, Closure $next): Response
    {
        $preview = app(ProfilePreviewService::class);

        if (! $preview->isActive() || $request->isMethodSafe() || $this->isAllowedWrite($request)) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json([
                'message' => 'Modo visualizacao ativo. Acoes de escrita estao bloqueadas.',
            ], 423);
        }

        Notification::make()
            ->title('Acao bloqueada no modo visualizacao')
            ->body('Nada foi salvo. Volte a normalidade para executar alteracoes reais.')
            ->warning()
            ->send();

        return redirect()->back();
    }

    private function isAllowedWrite(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (in_array($routeName, [
            'profile-preview.start',
            'profile-preview.stop',
            'presence.heartbeat',
            'livewire.update',
        ], true)) {
            return true;
        }

        if (str_contains($routeName, 'filament.admin.auth.logout')) {
            app(ProfilePreviewService::class)->stop();

            return true;
        }

        return false;
    }
}
