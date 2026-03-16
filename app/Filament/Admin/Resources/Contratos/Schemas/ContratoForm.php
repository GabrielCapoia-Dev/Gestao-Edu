<?php

namespace App\Filament\Admin\Resources\Contratos\Schemas;

use App\Models\Item;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Schemas\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ContratoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do Contrato')
                    ->columns(2)
                    ->columnSpanFull()
                    ->schema([
                        TextInput::make('numero_contrato')
                            ->label('Número do Contrato')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255)
                            ->columnSpan(1),

                        Select::make('id_empresa_contratada')
                            ->label('Empresa Contratada')
                            ->relationship('empresaContratada', 'nome')
                            ->searchable()
                            ->preload()
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('data_inicio')
                            ->label('Data de Início')
                            ->required()
                            ->columnSpan(1),

                        DatePicker::make('data_vencimento')
                            ->label('Data de Vencimento')
                            ->after('data_inicio')
                            ->columnSpan(1),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->rows(3)
                            ->columnSpanFull(),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->columnSpanFull(),
                    ]),

                Section::make('Itens do Contrato')
                    ->columnSpanFull()
                    ->schema([
                        Repeater::make('itens')
                            ->label('')
                            ->relationship('itens')
                            ->schema([
                                Select::make('item_id')
                                    ->label('Item')
                                    ->options(Item::where('ativo', true)->pluck('nome', 'id'))
                                    ->searchable()
                                    ->required()
                                    ->distinct()
                                    ->columnSpan(2),

                                TextInput::make('quantidade')
                                    ->label('Quantidade')
                                    ->numeric()
                                    ->minValue(0.001)
                                    ->required()
                                    ->columnSpan(1),
                            ])
                            ->columns(3)
                            ->addActionLabel('Adicionar Item')
                            ->mutateRelationshipDataBeforeCreateUsing(function (array $data): array {
                                return $data;
                            }),
                    ]),
            ]);
    }
}