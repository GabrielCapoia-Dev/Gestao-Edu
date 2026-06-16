<?php

namespace App\Filament\Admin\Resources\Pedidos\RelationManagers;

use App\Models\Pedido;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Support\Colors\Color;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class PedidosAdicionaisRelationManager extends RelationManager
{
    protected static string $relationship = 'pedidosAdicionais';

    protected static ?string $title = 'Pedidos Adicionais';
    protected static ?string $modelLabel = 'Pedido adicional';
    protected static ?string $pluralModelLabel = 'Pedidos adicionais';

    public static function canViewForRecord(Model $ownerRecord, string $pageClass): bool
    {
        return $ownerRecord instanceof Pedido
            && ! $ownerRecord->is_pedido_adicional;
    }

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with([
                'tipoManutencao',
                'tipoStatus',
                'problemas',
                'feedbackItens',
            ]))
            ->columns([
                Tables\Columns\TextColumn::make('numero_protocolo')
                    ->label('Protocolo')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('tipoManutencao.nome')
                    ->label('Tipo')
                    ->sortable(),

                Tables\Columns\TextColumn::make('tipoStatus.nome')
                    ->label('Status')
                    ->badge()
                    ->color(fn (Pedido $record) => Color::hex($record->tipoStatus?->cor ?? '#64748b')),

                Tables\Columns\TextColumn::make('problemas_badges')
                    ->label('Problemas')
                    ->state(fn (Pedido $record): array => $record->problemas
                        ->pluck('texto_problema')
                        ->filter()
                        ->values()
                        ->all())
                    ->badge()
                    ->color('gray')
                    ->placeholder('Sem problemas'),

                Tables\Columns\TextColumn::make('descricao_pedido')
                    ->label('Descrição')
                    ->limit(80)
                    ->wrap(),

                Tables\Columns\TextColumn::make('data_identificacao_problema')
                    ->label('Identificado em')
                    ->date('d/m/Y')
                    ->sortable()
                    ->placeholder('-'),

                Tables\Columns\TextColumn::make('avaliacao_resumo')
                    ->label('Avaliação')
                    ->state(function (Pedido $record): ?string {
                        $itens = $record->feedbackItens;

                        if ($itens->isEmpty()) {
                            return null;
                        }

                        $nota = number_format((float) $itens->avg('valor'), 1, ',', '.');
                        $resultados = $itens
                            ->map(fn ($item) => $item->resultado?->label() ?? $item->resultado)
                            ->filter()
                            ->unique()
                            ->join(', ');

                        return trim("Nota {$nota}/5" . ($resultados ? " - {$resultados}" : ''));
                    })
                    ->badge()
                    ->color('success')
                    ->placeholder('Sem avaliação'),
            ])
            ->defaultSort('created_at', 'desc')
            ->paginated([5, 10, 25, 50])
            ->defaultPaginationPageOption(5);
    }

    public function isReadOnly(): bool
    {
        return true;
    }
}
