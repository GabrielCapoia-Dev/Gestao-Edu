<?php

namespace App\Filament\Resources\PedidoResource\Pages;

use App\Filament\Resources\PedidoResource;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Auth;

class EditPedido extends EditRecord
{
    protected static string $resource = PedidoResource::class;

    private ?string $observacaoStatus = null;
    private ?int $statusAnteriorId = null;
    private ?int $novoStatusId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn() => route('pedidos.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->statusAnteriorId   = $this->record->tipo_status_id;
        $this->observacaoStatus   = $data['descricao_alteracao'] ?? null;
        $this->novoStatusId       = $data['novo_status_id'] ?? null;

        unset($data['descricao_alteracao']);
        unset($data['novo_status_id']);

        $statusEmAbertoId = TipoStatus::where('nome', 'Em Aberto')->value('id');
        $statusEmAnaliseId     = TipoStatus::where('nome', 'Em Análise')->value('id');

        // 🔵 Se usuário escolheu manualmente → usa o escolhido
        if ($this->novoStatusId) {
            $data['tipo_status_id'] = $this->novoStatusId;
        }
        // 🔵 Se NÃO escolheu e estava Em Aberto → vira Em Analise
        elseif ($this->statusAnteriorId === $statusEmAbertoId) {
            $data['tipo_status_id'] = $statusEmAnaliseId;
        }
        // 🔵 Caso contrário → mantém o status atual

        return $data;
    }


    protected function afterSave(): void
    {
        $record  = $this->record->refresh();
        $user    = Auth::user();
        $service = app(PedidoService::class);

        $statusNovoId = $record->tipo_status_id;

        $service->registrarHistorico(
            $record,
            $this->statusAnteriorId,
            $statusNovoId,
            $user,
            $this->observacaoStatus,
        );

        $status = TipoStatus::find($statusNovoId);

        if ($status?->finaliza_pedido && !$record->data_entrega) {
            $record->update(['data_entrega' => now()]);
        }
    }



    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
