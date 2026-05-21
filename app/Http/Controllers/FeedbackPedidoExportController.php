<?php

namespace App\Http\Controllers;

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
        abort_unless(
            Auth::user()?->hasPermissionTo('Visualizar Feedback de Pedidos')
            && (Auth::user()?->hasPermissionLike('exportar relatorios') ?? false),
            403
        );

        try {
            $filters = $this->analytics->normalizeFilters(array_merge(
                $request->only([
                    'data_inicio',
                    'data_fim',
                    'valor',
                    'nivel_prioridade',
                    'tipo_manutencao_id',
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
                ->title($exportRequest->wasRecentlyCreated ? 'Exportacao enviada para a fila' : 'Exportacao ja esta em andamento')
                ->body('Acompanhe o progresso em Minhas Exportacoes.')
                ->success()
                ->send();

            return redirect()->route('filament.admin.pages.minhas-exportacoes', [
                'download' => $exportRequest->getKey(),
            ]);
        } catch (Throwable $exception) {
            Log::warning('Falha ao enfileirar relatorio de feedback.', [
                'exception' => $exception,
                'user_id' => Auth::id(),
                'report_type' => $reportType,
            ]);

            Notification::make()
                ->title('Nao foi possivel iniciar a exportacao')
                ->body($exception->getMessage())
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
