<?php

namespace App\Filament\Admin\Resources\InventarioPedidos\Tables;

use App\Models\Enums\InventarioPedidoStatus;
use App\Services\Inventario\InventarioContextService;
use App\Services\Inventario\InventarioPedidoService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\Auth;

class InventarioPedidosTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn ($record): string => \App\Filament\Admin\Resources\InventarioPedidos\InventarioPedidoResource::getUrl('view', ['record' => $record]))
            ->columns(static::columns())
            ->filters(static::filters())
            ->groupedBulkActions([
                BulkAction::make('gerarRomaneio')
                    ->label('Gerar Romaneio')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (): bool => (Auth::user()?->hasPermissionTo('Gerar Romaneios de Inventário') ?? false)
                        && app(InventarioContextService::class)->ehGestorGeral(Auth::user()))
                    ->action(function (EloquentCollection $records) {
                        try {
                            $romaneio = app(InventarioPedidoService::class)->gerarRomaneio(
                                $records->pluck('id')->all(),
                                null,
                                Auth::user(),
                            );

                            Notification::make()
                                ->title('Romaneio gerado com sucesso.')
                                ->success()
                                ->send();

                            return redirect()->to(route('inventario-romaneios.relatorio.pdf', ['romaneio' => $romaneio]));
                        } catch (\DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();

                            return null;
                        }
                    }),
            ]);
    }

    public static function columns(): array
    {
        return [
            TextColumn::make('id')
                ->label('#')
                ->sortable(),

            TextColumn::make('escola.nome')
                ->label('Escola')
                ->searchable()
                ->sortable()
                ->weight('bold'),

            TextColumn::make('status')
                ->label('Status')
                ->badge()
                ->formatStateUsing(fn ($state) => $state?->label() ?? $state)
                ->color(fn ($state) => $state?->color() ?? 'gray'),

            TextColumn::make('itens_count')
                ->label('Itens')
                ->sortable(),

            TextColumn::make('romaneio.codigo')
                ->label('Romaneio')
                ->placeholder('—')
                ->sortable(),

            TextColumn::make('solicitadoPor.name')
                ->label('Solicitado por')
                ->placeholder('—')
                ->toggleable(),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime('d/m/Y H:i')
                ->sortable(),

            TextColumn::make('entregue_em')
                ->label('Entregue em')
                ->dateTime('d/m/Y H:i')
                ->placeholder('—')
                ->sortable(),
        ];
    }

    public static function filters(): array
    {
        return [
            SelectFilter::make('status')
                ->label('Status')
                ->options(
                    collect(InventarioPedidoStatus::cases())
                        ->mapWithKeys(fn (InventarioPedidoStatus $status) => [$status->value => $status->label()])
                        ->toArray()
                ),
            SelectFilter::make('escola_id')
                ->label('Escola')
                ->relationship('escola', 'nome'),
        ];
    }
}
