<?php

namespace App\Http\Controllers;

use App\Services\Exports\ExportRequestService;
use App\Services\Relatorios\PedidoRelatorioGeralService;
use Filament\Notifications\Notification;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        abort_unless(Auth::user()?->hasPermissionLike('exportar relatorios'), 403);

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
                user: Auth::user(),
                type: 'pedido_relatorio_geral',
                format: 'pdf',
                filters: $filtros,
                label: 'Relatorio geral de pedidos',
                metadata: ['route' => 'pedidos.relatorio-geral'],
            );

            Notification::make()
                ->title($exportRequest->wasRecentlyCreated ? 'Exportacao enviada para a fila' : 'Exportacao ja esta em andamento')
                ->body('Acompanhe o progresso em Minhas Exportacoes.')
                ->success()
                ->send();

            return redirect()->route('filament.admin.pages.minhas-exportacoes');

        } catch (Throwable $e) {
            Log::error('Falha ao enfileirar relatorio geral de pedidos.', [
                'exception' => $e,
                'user_id' => Auth::id(),
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
