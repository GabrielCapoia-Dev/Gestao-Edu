<?php

namespace App\Http\Controllers;

use App\Exceptions\ResponsaveisParecerInvalidosException;
use App\Models\Avaliacao;
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
        $this->authorize('export', Avaliacao::class);

        $params = $this->validarParametros($request);

        if ($request->boolean('async')) {
            return $this->queueExport($request, $service, $exports, $params, 'pdf');
        }

        try {
            return $service->exportar($params, $request->user());
        } catch (ResponsaveisParecerInvalidosException $exception) {
            Notification::make()
                ->title('Não foi possível gerar o parecer')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return redirect()->back();
        }
    }

    public function exportarCsv(
        Request $request,
        AvaliacaoDocumentoExportService $service,
        ExportRequestService $exports,
    ): Response
    {
        $this->authorize('export', Avaliacao::class);

        $params = $this->validarParametros($request);

        if ($request->boolean('async')) {
            return $this->queueExport($request, $service, $exports, $params, 'csv');
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
        AvaliacaoDocumentoExportService $documentoService,
        ExportRequestService $exports,
        array $params,
        string $format,
    ): Response {
        try {
            if ($format === 'pdf') {
                $documentoService->prepararSnapshotsParecer($params, $request->user());
            }

            $exportRequest = $exports->queue(
                user: $request->user(),
                type: 'avaliacao_documento',
                format: $format,
                filters: $params,
                label: 'Documento de avaliação',
                metadata: ['route' => $request->route()?->getName()],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                ->body('Acompanhe o progresso em Minhas Exportacoes.')
                ->success()
                ->send();

            return redirect()->back();
        } catch (Throwable $e) {
            Log::error('Falha ao enfileirar exportação de avaliação.', [
                'exception' => $e,
                'user_id' => $request->user()?->getKey(),
            ]);

            Notification::make()
                ->title('Não foi possível iniciar a exportação')
                ->body($e instanceof ResponsaveisParecerInvalidosException
                    ? $e->getMessage()
                    : 'Tente novamente em alguns instantes.')
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
