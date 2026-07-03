<?php

namespace App\Filament\Admin\Resources\Pedidos\Pages;

use App\Filament\Admin\Resources\Pedidos\PedidoResource;
use App\Models\TipoStatus;
use App\Services\PedidoService;
use App\Services\ProfilePreviewService;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditPedido extends EditRecord
{
    protected static string $resource = PedidoResource::class;

    private ?string $observacaoStatus = null;
    private ?int $statusAnteriorId = null;
    private ?int $novoStatusId = null;
    private ?int $statusEncaminhadoId = null;
    private ?int $empresaContratadaId = null;

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
        $user = $this->usuarioEfetivo();

        $this->statusAnteriorId = $this->record->tipo_status_id;
        $this->observacaoStatus = $data['descricao_alteracao'] ?? null;
        $this->novoStatusId = $data['novo_status_id'] ?? null;
        $this->statusEncaminhadoId = null;
        $this->empresaContratadaId = null;

        unset($data['descricao_alteracao'], $data['novo_status_id']);

        $statusAberto = $service->statusPorNome('Em Aberto');
        $statusAnalise = $service->statusPorNome('Em Análise');
        $statusEncaminhado = $service->statusPorNome('Encaminhado ao Setor');
        $statusEnviadoEmpresa = $service->statusPorNome('Enviado para Empresa');
        $statusCancelado = $service->statusPorNome('Cancelado');

        if ($this->novoStatusId && $statusEncaminhado?->id === (int) $this->novoStatusId) {
            if (! $service->podeEncaminharRegistro($this->record, $user, (int) ($data['setor_id'] ?? 0))) {
                throw ValidationException::withMessages([
                    'setor_id' => 'Seu setor não possui autorização para encaminhar este pedido ao setor selecionado.',
                ]);
            }

            $this->statusEncaminhadoId = $statusEncaminhado->id;
            $data['tipo_status_id'] = $statusEncaminhado->id;

            return $data;
        }

        if ($this->novoStatusId && $statusCancelado?->id === (int) $this->novoStatusId) {
            if (! $service->podeCancelarRegistro($this->record, $user)) {
                throw ValidationException::withMessages([
                    'novo_status_id' => 'Seu setor não possui autorização para cancelar este pedido.',
                ]);
            }
        } elseif (! $service->podeGerenciarRegistro($this->record, $user)) {
            throw ValidationException::withMessages([
                'pedido' => 'Seu setor não possui autorização para editar este pedido.',
            ]);
        }

        if ($this->novoStatusId && $statusEnviadoEmpresa?->id === (int) $this->novoStatusId) {
            if (! $service->podeEnviarParaEmpresa($user)) {
                unset($data['empresa_contratada_id']);
                $this->novoStatusId = null;
            } else {
                if (blank($data['empresa_contratada_id'] ?? null)) {
                    throw ValidationException::withMessages([
                        'empresa_contratada_id' => 'Informe a empresa responsável.',
                    ]);
                }

                $empresa = $service->empresaContratadaDisponivelParaEnvio(
                    (int) $data['empresa_contratada_id'],
                    $user,
                );
                $this->empresaContratadaId = (int) $empresa->id;

                unset($data['empresa_contratada_id']);

                return $data;
            }
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
        $user = $this->usuarioEfetivo();
        $service = app(PedidoService::class);
        $statusNovoId = $record->tipo_status_id;

        if ($this->statusEncaminhadoId) {
            $setorNome = $record->setor?->nome ?? 'setor destino';

            $service->registrarHistorico(
                $record,
                $this->statusAnteriorId,
                $this->statusEncaminhadoId,
                $user,
                $this->observacaoStatus ?: "Pedido encaminhado para {$setorNome}.",
            );

            $this->atualizarComentarioGestorComObservacao($service, $user);

            return;
        }

        if ($this->empresaContratadaId) {
            $service->enviarParaEmpresa(
                $record,
                $this->empresaContratadaId,
                $user,
                $this->observacaoStatus,
            );

            $this->atualizarComentarioGestorComObservacao($service, $user);

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

        $this->atualizarComentarioGestorComObservacao($service, $user);
    }

    protected function getRedirectUrl(): string
    {
        return $this->previousUrl ?? $this->getResource()::getUrl('index');
    }

    private function usuarioEfetivo(): ?\App\Models\User
    {
        return app(ProfilePreviewService::class)->effectiveUser();
    }

    private function atualizarComentarioGestorComObservacao(PedidoService $service, ?\App\Models\User $user): void
    {
        if (! $user || blank($this->observacaoStatus)) {
            return;
        }

        $service->atualizarComentarioGestorPorAlteracao(
            $this->record->refresh(),
            $user,
            $this->observacaoStatus,
        );
    }
}
