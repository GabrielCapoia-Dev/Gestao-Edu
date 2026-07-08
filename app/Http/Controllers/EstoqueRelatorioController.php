<?php

namespace App\Http\Controllers;

use App\Models\Estoque;
use App\Models\User;
use App\Services\Exports\ExportRequestService;
use App\Services\Relatorios\EstoqueRelatorioService;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class EstoqueRelatorioController extends Controller
{
    public function __construct(
        protected EstoqueRelatorioService $service,
        protected ExportRequestService $exports,
    ) {}

    public function exportarPdf(Request $request): Response
    {
        $this->autorizarExportacao();

        if ($request->boolean('async')) {
            return $this->queueExport('estoque_geral', 'pdf', $request->all(), 'Relatório geral de estoque', $request);
        }

        $this->prepararExecucao();

        return $this->service->gerarPdfGeral($request->all(), Auth::user());
    }

    public function exportarXlsx(Request $request): Response
    {
        $this->autorizarExportacao();

        if ($request->boolean('async')) {
            return $this->queueExport('estoque_geral', 'xlsx', $request->all(), 'Relatório geral de estoque', $request);
        }

        $this->prepararExecucao();

        return $this->service->gerarXlsxGeral($request->all(), Auth::user());
    }

    public function exportarItemPdf(Request $request, Estoque $estoque): Response
    {
        $this->autorizarExportacao();

        if ($request->boolean('async')) {
            return $this->queueExport('estoque_item', 'pdf', ['estoque_id' => $estoque->getKey()], 'Relatório individual de estoque', $request);
        }

        $this->prepararExecucao();

        return $this->service->gerarPdfItem($estoque, Auth::user());
    }

    public function exportarItemXlsx(Request $request, Estoque $estoque): Response
    {
        $this->autorizarExportacao();

        if ($request->boolean('async')) {
            return $this->queueExport('estoque_item', 'xlsx', ['estoque_id' => $estoque->getKey()], 'Relatório individual de estoque', $request);
        }

        $this->prepararExecucao();

        return $this->service->gerarXlsxItem($estoque, Auth::user());
    }

    protected function prepararExecucao(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
    }

    protected function autorizarExportacao(): void
    {
        $this->authorize('exportReports', User::class);
    }

    /**
     * @param array<string, mixed> $filters
     */
    private function queueExport(
        string $type,
        string $format,
        array $filters,
        string $label,
        Request $request,
    ): Response {
        unset($filters['async']);

        try {
            $exportRequest = $this->exports->queue(
                user: Auth::user(),
                type: $type,
                format: $format,
                filters: $filters,
                label: $label,
                metadata: ['route' => $request->route()?->getName()],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                ->body('Acompanhe o progresso em Minhas Exportacoes.')
                ->success()
                ->send();

            return redirect()->route('filament.admin.pages.minhas-exportacoes', [
                'download' => $exportRequest->getKey(),
            ]);
        } catch (Throwable $e) {
            Log::error('Falha ao enfileirar exportação de estoque.', [
                'exception' => $e,
                'user_id' => Auth::id(),
                'type' => $type,
                'format' => $format,
            ]);

            Notification::make()
                ->title('Não foi possível iniciar a exportação')
                ->body('Tente novamente em alguns instantes.')
                ->danger()
                ->send();

            return redirect()->back();
        }
    }
}
