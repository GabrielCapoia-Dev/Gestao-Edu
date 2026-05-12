<?php

namespace App\Http\Controllers;

use App\Services\Avaliacoes\AvaliacaoDocumentoExportService;
use App\Services\Exports\ExportRequestService;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class AvaliacaoDocumentoExportController extends Controller
{
    public function exportar(
        Request $request,
        AvaliacaoDocumentoExportService $service,
        ExportRequestService $exports,
    ): Response
    {
        abort_unless($request->user()?->hasPermissionLike('exportar avaliacoes') ?? false, 403);

        $params = $this->validarParametros($request);

        if ($request->boolean('async')) {
            return $this->queueExport($request, $exports, $params, 'pdf');
        }

        return $service->exportar($params, $request->user());
    }

    public function exportarCsv(
        Request $request,
        AvaliacaoDocumentoExportService $service,
        ExportRequestService $exports,
    ): Response
    {
        abort_unless($request->user()?->hasPermissionLike('exportar avaliacoes') ?? false, 403);

        $params = $this->validarParametros($request);

        if ($request->boolean('async')) {
            return $this->queueExport($request, $exports, $params, 'csv');
        }

        return $service->exportarCsv($params, $request->user());
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

    /**
     * @param array<string, mixed> $params
     */
    private function queueExport(
        Request $request,
        ExportRequestService $exports,
        array $params,
        string $format,
    ): Response {
        try {
            $exportRequest = $exports->queue(
                user: $request->user(),
                type: 'avaliacao_documento',
                format: $format,
                filters: $params,
                label: 'Documento de avaliacao',
                metadata: ['route' => $request->route()?->getName()],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportacao enviada para a fila' : 'Exportacao ja esta em andamento')
                ->body('Acompanhe o progresso em Minhas Exportacoes.')
                ->success()
                ->send();

            return redirect()->route('filament.admin.pages.minhas-exportacoes', [
                'download' => $exportRequest->getKey(),
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao enfileirar exportacao de avaliacao.', [
                'exception' => $e,
                'user_id' => $request->user()?->getKey(),
            ]);

            Notification::make()
                ->title('Nao foi possivel iniciar a exportacao')
                ->body('Tente novamente em alguns instantes.')
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
