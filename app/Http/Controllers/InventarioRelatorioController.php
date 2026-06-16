<?php

namespace App\Http\Controllers;

use App\Models\Inventario;
use App\Models\InventarioEstoque;
use App\Services\Exports\ExportRequestService;
use App\Services\Inventario\InventarioContextService;
use App\Services\Relatorios\InventarioRelatorioService;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class InventarioRelatorioController extends Controller
{
    protected const PERMISSAO_EXPORTAR_RELATORIOS = "Exportar Relat\xC3\xB3rios";

    public function __construct(
        protected InventarioRelatorioService $service,
        protected InventarioContextService $contextService,
        protected ExportRequestService $exports,
    ) {}

    public function exportarPdf(Request $request, Inventario $inventario): Response
    {
        $this->autorizarExportacao($inventario);

        if ($request->boolean('async')) {
            return $this->queueExport(
                'inventario_geral',
                'pdf',
                ['inventario_id' => $inventario->getKey()] + $request->all(),
                'Relatório geral de inventário',
                $request,
            );
        }

        $this->prepararExecucao();

        return $this->service->gerarPdfGeral($inventario, $request->all(), Auth::user());
    }

    public function exportarRedePdf(Request $request): Response
    {
        $this->autorizarExportacaoRede();

        if ($request->boolean('async')) {
            return $this->queueExport('inventario_rede', 'pdf', $request->all(), 'Relatório de envios por escola', $request);
        }

        $this->prepararExecucao();

        return $this->service->gerarPdfEnviosEscolas($request->all(), Auth::user());
    }

    public function exportarXlsx(Request $request, Inventario $inventario): Response
    {
        $this->autorizarExportacao($inventario);

        if ($request->boolean('async')) {
            return $this->queueExport(
                'inventario_geral',
                'xlsx',
                ['inventario_id' => $inventario->getKey()] + $request->all(),
                'Relatório geral de inventário',
                $request,
            );
        }

        $this->prepararExecucao();

        return $this->service->gerarXlsxGeral($inventario, $request->all(), Auth::user());
    }

    public function exportarRedeXlsx(Request $request): Response
    {
        $this->autorizarExportacaoRede();

        if ($request->boolean('async')) {
            return $this->queueExport('inventario_rede', 'xlsx', $request->all(), 'Relatório de envios por escola', $request);
        }

        $this->prepararExecucao();

        return $this->service->gerarXlsxEnviosEscolas($request->all(), Auth::user());
    }

    public function exportarItemPdf(Request $request, InventarioEstoque $estoque): Response
    {
        $estoque->loadMissing('inventario');
        $this->autorizarExportacao($estoque->inventario);

        if ($request->boolean('async')) {
            return $this->queueExport(
                'inventario_item',
                'pdf',
                ['inventario_estoque_id' => $estoque->getKey()],
                'Relatório individual de inventário',
                $request,
            );
        }

        $this->prepararExecucao();

        return $this->service->gerarPdfItem($estoque, Auth::user());
    }

    public function exportarItemXlsx(Request $request, InventarioEstoque $estoque): Response
    {
        $estoque->loadMissing('inventario');
        $this->autorizarExportacao($estoque->inventario);

        if ($request->boolean('async')) {
            return $this->queueExport(
                'inventario_item',
                'xlsx',
                ['inventario_estoque_id' => $estoque->getKey()],
                'Relatório individual de inventário',
                $request,
            );
        }

        $this->prepararExecucao();

        return $this->service->gerarXlsxItem($estoque, Auth::user());
    }

    protected function prepararExecucao(): void
    {
        ini_set('memory_limit', '512M');
        set_time_limit(180);
    }

    protected function autorizarExportacao(?Inventario $inventario): void
    {
        $user = Auth::user();

        abort_unless($this->usuarioPodeExportarRelatorios($user), 403);
        abort_unless($inventario !== null, 404);

        if ($this->contextService->ehGestorGeral($user)) {
            return;
        }

        abort_unless(in_array((int) $inventario->escola_id, $user?->idsEscolasVinculadas() ?? [], true), 403);
    }

    protected function autorizarExportacaoRede(): void
    {
        $user = Auth::user();

        abort_unless($this->usuarioPodeExportarRelatorios($user), 403);
        abort_unless($this->contextService->ehGestorGeral($user), 403);
    }

    protected function usuarioPodeExportarRelatorios($user): bool
    {
        return $user?->hasPermissionTo(self::PERMISSAO_EXPORTAR_RELATORIOS) ?? false;
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
            Log::error('Falha ao enfileirar exportação de inventário.', [
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
