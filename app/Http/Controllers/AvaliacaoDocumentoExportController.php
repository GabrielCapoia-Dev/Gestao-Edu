<?php

namespace App\Http\Controllers;

use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AvaliacaoDocumentoExportController extends Controller
{
    public function exportar(Request $request, AvaliacaoDocumentoExportService $service): Response
    {
        abort_unless($request->user()?->hasPermissionLike('exportar avaliacoes') ?? false, 403);

        return $service->exportar($this->validarParametros($request), $request->user());
    }

    public function exportarCsv(Request $request, AvaliacaoDocumentoExportService $service): Response
    {
        abort_unless($request->user()?->hasPermissionLike('exportar avaliacoes') ?? false, 403);

        return $service->exportarCsv($this->validarParametros($request), $request->user());
    }

    /**
     * @return array<string, mixed>
     */
    private function validarParametros(Request $request): array
    {
        return $request->validate([
            'avaliacao_id' => ['required', 'integer', 'exists:avaliacoes,id'],
            'escopo' => ['required', 'string', 'in:aluno,turma,escola'],
            'escola_id' => ['required_if:escopo,escola', 'nullable', 'integer', 'exists:escolas,id'],
            'turma_id' => ['required_if:escopo,turma', 'nullable', 'integer', 'exists:turmas,id'],
            'aluno_id' => ['required_if:escopo,aluno', 'nullable', 'integer', 'exists:alunos,id'],
        ]);
    }
}
