<?php

namespace App\Http\Middleware;

use App\Models\Aluno;
use App\Services\AlunoTransferenciaPendenteService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class BloquearProfessorPendenciaTransferencia
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Aluno|null $alunoOrigem */
        $alunoOrigem = app(AlunoTransferenciaPendenteService::class)
            ->pendenciaAtivaParaProfessor($request->user());

        if (! $alunoOrigem) {
            return $next($request);
        }

        if ($this->rotaPermitida($request)) {
            return $next($request);
        }

        return redirect()->to(route('filament.admin.resources.alunos.index', [
            'pendencia_cgm' => $alunoOrigem->cgm,
        ]));
    }

    private function rotaPermitida(Request $request): bool
    {
        $routeName = (string) ($request->route()?->getName() ?? '');

        if (str_contains($routeName, 'filament.admin.auth.logout')) {
            return true;
        }

        if ($routeName === 'auth.force-password.edit') {
            return true;
        }

        if ($routeName === 'filament.admin.resources.alunos.index') {
            return true;
        }

        if ($request->is('livewire/*')) {
            $refererPath = parse_url((string) $request->headers->get('referer'), PHP_URL_PATH) ?: '';

            if (str_starts_with($refererPath, '/admin/alterar-senha-obrigatoria')) {
                return true;
            }

            return str_starts_with($refererPath, '/admin/alunos');
        }

        return false;
    }
}
