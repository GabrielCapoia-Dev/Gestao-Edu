<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
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
    private ?int $statusEncaminhadoId = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('download_pdf')
                ->label('Baixar PDF')
                ->icon('heroicon-o-arrow-down-tray')
                ->url(fn () => route('pedidos.pdf', $this->record))
                ->openUrlInNewTab(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $service = app(PedidoService::class);
        $user = Auth::user();

        $this->statusAnteriorId = $this->record->tipo_status_id;
        $this->observacaoStatus = $data['descricao_alteracao'] ?? null;
        $this->novoStatusId = $data['novo_status_id'] ?? null;
        $this->statusEncaminhadoId = null;

        unset($data['descricao_alteracao'], $data['novo_status_id']);

        $statusAberto = $service->statusPorNome('Em Aberto');
        $statusAnalise = $service->statusPorNome('Em Análise');
        $statusEncaminhado = $service->statusPorNome('Encaminhado ao Setor');
        $statusEnviadoEmpresa = $service->statusPorNome('Enviado para Empresa');

        if ($this->novoStatusId && $statusEncaminhado?->id === (int) $this->novoStatusId) {
            $this->statusEncaminhadoId = $statusEncaminhado->id;
            $data['tipo_status_id'] = $statusAberto?->id;

            return $data;
        }

        if (
            $this->novoStatusId
            && $statusEnviadoEmpresa?->id === (int) $this->novoStatusId
            && ! $service->podeEnviarParaEmpresa($user)
        ) {
            unset($data['empresa_contratada_id']);
            $this->novoStatusId = null;
        }

        if ($this->novoStatusId) {
            $data['tipo_status_id'] = $this->novoStatusId;
        } elseif ($this->statusAnteriorId === $statusAberto?->id) {
            $data['tipo_status_id'] = $statusAnalise?->id;
        }

        if (! $this->novoStatusId || $statusEnviadoEmpresa?->id !== (int) $this->novoStatusId) {
            unset($data['empresa_contratada_id']);
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $record = $this->record->refresh();
        $user = Auth::user();
        $service = app(PedidoService::class);

        $statusNovoId = $record->tipo_status_id;

        if ($this->statusEncaminhadoId) {
            $setorNome = $record->setor?->nome ?? 'setor destino';

            $service->registrarHistorico(
                $record,
                $this->statusAnteriorId,
                $this->statusEncaminhadoId,
                $user,
                $this->observacaoStatus ?: "Pedido encaminhado para {$setorNome}."
            );

            $service->registrarHistorico(
                $record,
                $this->statusEncaminhadoId,
                $statusNovoId,
                $user,
                "Pedido recebido por {$setorNome} com status Em Aberto."
            );

            return;
        }

        $service->registrarHistorico(
            $record,
            $this->statusAnteriorId,
            $statusNovoId,
            $user,
            $this->observacaoStatus,
        );

        $status = TipoStatus::find($statusNovoId);

        if ($status?->finaliza_pedido && ! $record->data_entrega) {
            $record->update(['data_entrega' => now()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }
}
