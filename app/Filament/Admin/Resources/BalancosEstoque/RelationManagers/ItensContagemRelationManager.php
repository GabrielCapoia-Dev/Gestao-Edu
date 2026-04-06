<?php

namespace App\Filament\Admin\Resources\BalancosEstoque\RelationManagers;

use App\Filament\Admin\Resources\BalancosEstoque\BalancoEstoqueResource;
use App\Models\BalancoEstoque;
use App\Models\BalancoEstoqueItem;
use App\Services\Estoque\BalancoEstoqueService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class ItensContagemRelationManager extends RelationManager
{
    protected static string $relationship = 'itensContagem';

    protected static ?string $title = 'Itens do Balanco';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof BalancoEstoque
            && ! $ownerRecord->isAgendado()
            && BalancoEstoqueResource::canView($ownerRecord)
            && is_subclass_of($pageClass, ViewRecord::class);
    }

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('item.nome')
                    ->label('Item')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('item.unidade_medida')
                    ->label('Unidade')
                    ->formatStateUsing(fn ($state) => strtoupper($state?->value ?? (string) $state)),
                TextColumn::make('saldo_sistema_antes')
                    ->label('Saldo antes')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('quantidade_contada')
                    ->label('Quantidade real')
                    ->placeholder('Pendente')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('diferenca')
                    ->label('Divergencia')
                    ->placeholder('-')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.')
                    ->color(fn (BalancoEstoqueItem $record): string => (float) ($record->diferenca ?? 0) === 0.0 ? 'gray' : 'warning'),
                TextColumn::make('valor_unitario_referencia')
                    ->label('Valor unitario')
                    ->money('BRL')
                    ->placeholder('-'),
                TextColumn::make('valor_impacto')
                    ->label('Impacto financeiro')
                    ->money('BRL')
                    ->placeholder('-')
                    ->color(fn (BalancoEstoqueItem $record): string => (float) ($record->valor_impacto ?? 0) > 0 ? 'success' : ((float) ($record->valor_impacto ?? 0) < 0 ? 'danger' : 'gray')),
                TextColumn::make('saldo_final')
                    ->label('Saldo final')
                    ->placeholder('-')
                    ->numeric(decimalPlaces: 3, decimalSeparator: ',', thousandsSeparator: '.'),
                TextColumn::make('status_contagem_label')
                    ->label('Status')
                    ->badge()
                    ->color(fn (BalancoEstoqueItem $record): string => $record->status_contagem_color),
                TextColumn::make('contadoPor.name')
                    ->label('Contado por')
                    ->placeholder('-'),
                TextColumn::make('observacao_contagem')
                    ->label('Observacao')
                    ->limit(60)
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('registrarContagem')
                    ->label(fn (BalancoEstoqueItem $record): string => $record->quantidade_contada === null ? 'Registrar contagem' : 'Atualizar contagem')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->visible(fn (): bool => $this->getOwnerRecord()->isEmAndamento() && (Auth::user()?->hasPermissionTo('Registrar Contagem de Balanços de Estoque') ?? false))
                    ->fillForm(fn (BalancoEstoqueItem $record): array => [
                        'quantidade_contada' => $record->quantidade_contada,
                        'observacao_contagem' => $record->observacao_contagem,
                    ])
                    ->schema([
                        TextInput::make('quantidade_contada')
                            ->label('Quantidade real')
                            ->numeric()
                            ->required()
                            ->minValue(0)
                            ->step('0.001'),
                        Textarea::make('observacao_contagem')
                            ->label('Observacao da contagem')
                            ->rows(4)
                            ->maxLength(1500),
                    ])
                    ->action(function (BalancoEstoqueItem $record, array $data): void {
                        try {
                            app(BalancoEstoqueService::class)->registrarContagem(
                                $record,
                                (float) $data['quantidade_contada'],
                                $data['observacao_contagem'] ?? null,
                                Auth::user(),
                            );

                            Notification::make()
                                ->title('Contagem registrada com sucesso.')
                                ->success()
                                ->send();
                        } catch (\DomainException $exception) {
                            Notification::make()
                                ->title($exception->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }
}
