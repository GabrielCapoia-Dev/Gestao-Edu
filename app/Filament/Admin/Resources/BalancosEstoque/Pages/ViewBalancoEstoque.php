<?php

namespace App\Filament\Admin\Resources\BalancosEstoque\Pages;

use App\Filament\Admin\Resources\BalancosEstoque\BalancoEstoqueResource;
use App\Models\BalancoEstoque;
use App\Services\Estoque\BalancoEstoqueService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ViewBalancoEstoque extends ViewRecord
{
    protected static string $resource = BalancoEstoqueResource::class;

    protected ?Collection $itensInicioCache = null;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('iniciar')
                ->label('Iniciar')
                ->icon('heroicon-o-play')
                ->color('warning')
                ->visible(fn (): bool => $this->getRecord()->isAgendado() && $this->pode('Iniciar Balanços de Estoque'))
                ->disabled(fn (): bool => $this->itensDisponiveisParaInicio()->where('bloqueado', false)->isEmpty())
                ->schema([
                    CheckboxList::make('item_ids')
                        ->label('Itens do balanço')
                        ->options(fn (): array => $this->itensDisponiveisParaInicio()->mapWithKeys(
                            fn (array $item) => [
                                $item['item_id'] => $item['item']->nome,
                            ]
                        )->toArray())
                        ->descriptions(fn (): array => $this->itensDisponiveisParaInicio()->mapWithKeys(
                            fn (array $item) => [
                                $item['item_id'] => $this->descricaoItemInicio($item),
                            ]
                        )->toArray())
                        ->disableOptionWhen(fn (string $value): bool => $this->itemInicioBloqueado((int) $value))
                        ->default(fn (): array => $this->itensDisponiveisParaInicio()
                            ->where('bloqueado', false)
                            ->pluck('item_id')
                            ->all())
                        ->bulkToggleable()
                        ->columns(2)
                        ->required()
                        ->helperText('Os itens não marcados serão registrados como fora deste balanço.'),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->iniciar($this->getRecord(), $data['item_ids'] ?? [], Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço iniciado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('adiar')
                ->label('Adiar')
                ->icon('heroicon-o-clock')
                ->color('info')
                ->visible(fn (): bool => $this->getRecord()->isAgendado() && $this->pode('Adiar Balanços de Estoque'))
                ->schema([
                    DateTimePicker::make('data_agendada')
                        ->label('Nova data agendada')
                        ->required()
                        ->seconds(false)
                        ->default(fn (): ?string => $this->getRecord()->data_agendada?->format('Y-m-d H:i:s'))
                        ->native(false),
                    Textarea::make('motivo')
                        ->label('Motivo do adiamento')
                        ->required()
                        ->rows(4)
                        ->maxLength(1500),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->adiar($this->getRecord(), $data['data_agendada'], (string) $data['motivo'], Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço adiado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('cancelar')
                ->label('Cancelar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => ! $this->getRecord()->isConcluido() && ! $this->getRecord()->isCancelado() && $this->pode('Cancelar Balanços de Estoque'))
                ->schema([
                    Textarea::make('motivo')
                        ->label('Motivo do cancelamento')
                        ->required()
                        ->rows(4)
                        ->maxLength(1500),
                ])
                ->action(function (array $data): void {
                    try {
                        $this->service()->cancelar($this->getRecord(), (string) $data['motivo'], Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço cancelado com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),

            Action::make('concluir')
                ->label('Concluir')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Concluir balanço')
                ->modalDescription('As quantidades contadas serão aplicadas no estoque e gerarão movimentações de reajuste.')
                ->visible(fn (): bool => $this->getRecord()->isEmAndamento() && $this->pode('Concluir Balanços de Estoque'))
                ->action(function (): void {
                    try {
                        $this->service()->concluir($this->getRecord(), Auth::user());
                        $this->refreshRecordState();

                        Notification::make()
                            ->title('Balanço concluído com sucesso.')
                            ->success()
                            ->send();
                    } catch (\DomainException $exception) {
                        Notification::make()
                            ->title($exception->getMessage())
                            ->danger()
                            ->send();
                    }
                }),
        ];
    }

    protected function service(): BalancoEstoqueService
    {
        return app(BalancoEstoqueService::class);
    }

    protected function refreshRecordState(): void
    {
        /** @var BalancoEstoque $record */
        $record = BalancoEstoqueResource::getEloquentQuery()->findOrFail($this->getRecord()->getKey());
        $this->record = $record;
        $this->itensInicioCache = null;
    }

    protected function itensDisponiveisParaInicio(): Collection
    {
        if ($this->itensInicioCache instanceof Collection) {
            return $this->itensInicioCache;
        }

        /** @var BalancoEstoque $record */
        $record = $this->getRecord();

        return $this->itensInicioCache = $this->service()->itensDisponiveisParaInicio($record);
    }

    protected function descricaoItemInicio(array $item): string
    {
        $descricao = 'Saldo atual: ' . number_format((float) $item['saldo_sistema'], 3, ',', '.');
        $unidade = strtoupper($item['item']->unidade_medida?->value ?? '');

        if ($unidade !== '') {
            $descricao .= ' | Unidade: ' . $unidade;
        }

        if ($item['bloqueado']) {
            $descricao .= ' | Bloqueado em ' . ($item['balanco_codigo'] ?? 'outro balanço');
        }

        return $descricao;
    }

    protected function itemInicioBloqueado(int $itemId): bool
    {
        $item = $this->itensDisponiveisParaInicio()->firstWhere('item_id', $itemId);

        return (bool) ($item['bloqueado'] ?? false);
    }

    protected function pode(string $permissao): bool
    {
        return Auth::user()?->hasPermissionTo($permissao) ?? false;
    }
}
