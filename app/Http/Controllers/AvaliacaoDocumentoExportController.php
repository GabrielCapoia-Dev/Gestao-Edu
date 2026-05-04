<?php

namespace App\Http\Controllers;

use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AvaliacaoDocumentoExportController extends Controller
{
    public function exportar(Request $request, AvaliacaoDocumentoExportService $service): Response
    {
        abort_unless($request->user()?->hasPermissionTo('Exportar Avaliações') ?? false, 403);

        $data = $request->validate([
            'avaliacao_id' => ['required', 'integer', 'exists:avaliacoes,id'],
            'escopo' => ['required', 'string', 'in:aluno,turma,escola'],
            'escola_id' => ['required_if:escopo,escola', 'nullable', 'integer', 'exists:escolas,id'],
            'turma_id' => ['required_if:escopo,turma', 'nullable', 'integer', 'exists:turmas,id'],
            'aluno_id' => ['required_if:escopo,aluno', 'nullable', 'integer', 'exists:alunos,id'],
        ]);

        return $service->exportar($data, $request->user());
    }
}
