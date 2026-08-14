<?php

namespace App\Http\Controllers;

use App\Models\FeedbackPedido;
use App\Services\Exports\ExportRequestService;
use App\Services\Relatorios\FeedbackPedidoAnalyticsService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

class FeedbackPedidoExportController extends Controller
{
    public function __construct(
        private readonly ExportRequestService $exports,
        private readonly FeedbackPedidoAnalyticsService $analytics,
    ) {}

    public function exportarGeral(Request $request): RedirectResponse
    {
        return $this->queueExport($request, FeedbackPedidoAnalyticsService::REPORT_GERAL);
    }

    public function exportarListagem(Request $request): RedirectResponse
    {
        return $this->queueExport($request, FeedbackPedidoAnalyticsService::REPORT_LISTAGEM);
    }

    public function exportarGraficos(Request $request): RedirectResponse
    {
        return $this->queueExport($request, FeedbackPedidoAnalyticsService::REPORT_GERAL);
    }

    public function exportarTerceirizada(Request $request): RedirectResponse
    {
        return $this->queueExport($request, FeedbackPedidoAnalyticsService::REPORT_EMPRESAS);
    }

    private function queueExport(Request $request, string $reportType): RedirectResponse
    {
        $this->authorize('exportReports', FeedbackPedido::class);

        try {
            $filters = $this->analytics->normalizeFilters(array_merge(
                $request->only([
                    'data_inicio',
                    'data_fim',
                    'valor',
                    'nivel_prioridade',
                    'tipo_manutencao_id',
                    'tipo_manutencao_opcao_id',
                    'escola_id',
                    'empresa_contratada_id',
                    'resultado',
                    'reabrir_pedido',
                ]),
                ['report_type' => $reportType],
            ));

            $this->analytics->assertReportFilters($filters);

            $exportRequest = $this->exports->queue(
                user: Auth::user(),
                type: 'feedback_pedido_relatorio',
                format: 'pdf',
                filters: $filters,
                label: 'Feedback - ' . $this->analytics->reportTypeLabel($filters['report_type'] ?? null),
                metadata: ['route' => $request->route()?->getName()],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                ->body('Acompanhe o progresso pelo ícone de downloads no topo.')
                ->success()
                ->send();

            return redirect()->back();
        } catch (Throwable $exception) {
            Log::warning('Falha ao enfileirar relatório de feedback.', [
                'exception' => $exception,
                'user_id' => Auth::id(),
                'report_type' => $reportType,
            ]);

            Notification::make()
                ->title('Não foi possível iniciar a exportação')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
