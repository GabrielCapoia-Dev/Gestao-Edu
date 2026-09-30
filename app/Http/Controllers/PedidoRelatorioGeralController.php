<?php

namespace App\Http\Controllers;

use App\Models\Pedido;
use App\Services\Exports\ExportRequestService;
use App\Services\ProfilePreviewService;
use App\Services\Relatorios\PedidoRelatorioGeralService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

class PedidoRelatorioGeralController extends Controller
{
    public function __construct(
        protected PedidoRelatorioGeralService $service,
        protected ExportRequestService $exports,
    ) {}

    public function exportar(Request $request): RedirectResponse
    {
        return $this->queue($request, 'pedido_relatorio_geral', 'Relatório analítico de pedidos');
    }

    public function exportarListagem(Request $request): RedirectResponse
    {
        return $this->queue($request, 'pedido_relatorio_listagem', 'Listagem de pedidos');
    }

    private function queue(Request $request, string $type, string $label): RedirectResponse
    {
        $user = app(ProfilePreviewService::class)->effectiveUser();

        Gate::forUser($user)->authorize('exportReports', Pedido::class);

        try {
            $filtros = $request->only([
                'data_inicio',
                'data_fim',
                'escola_id',
                'tipo_status_id',
                'tipo_manutencao_id',
                'nivel_prioridade',
                'tipo_registro',
            ]);

            $exportRequest = $this->exports->queue(
                user: $user,
                type: $type,
                format: 'pdf',
                filters: $filtros,
                label: $label,
                metadata: ['route' => $request->route()?->getName()],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportação enviada para a fila' : 'Exportação já está em andamento')
                ->body('Acompanhe o progresso pelo ícone de downloads no topo.')
                ->success()
                ->send();

            return redirect()->back();

        } catch (Throwable $e) {
            Log::error('Falha ao enfileirar relatório geral de pedidos.', [
                'exception' => $e,
                'user_id' => $user?->id,
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
