<?php

namespace App\Services;

use App\Models\Setor;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SetorService
{
    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela())
            ->toolbarActions($this->acoesEmMassa())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->searchable()
                ->sortable(),

            IconColumn::make('recebe_pedidos_iniciais')
                ->label('Setor Geral')
                ->boolean(),

            TextColumn::make('encaminha_pedido_para_setor_ids')
                ->label('Encaminha Para')
                ->formatStateUsing(fn (Setor $record) => $record->setores_destino->pluck('nome')->implode(', '))
                ->placeholder('Nao se aplica')
                ->wrap()
                ->toggleable(),

            TextColumn::make('status')
                ->label('Status')
                ->sortable(),

            TextColumn::make('alterado_por')
                ->label('Alterado por')
                ->toggleable(),

            IconColumn::make('ativo')
                ->label('Ativo')
                ->boolean(),

            TextColumn::make('updated_at')
                ->label('Atualizado')
                ->since()
                ->sortable(),
        ];
    }

    private function acoesTabela(): array
    {
        return [
            EditAction::make(),
            DeleteAction::make(),
        ];
    }

    private function acoesEmMassa(): array
    {
        return [
            DeleteBulkAction::make(),
        ];
    }

    public function configurarFormulario(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Dados Gerais')
                ->columnSpanFull()
                ->schema([
                    Grid::make(2)->schema([
                        TextInput::make('nome')
                            ->required()
                            ->minLength(3)
                            ->maxLength(255),

                        Select::make('status')
                            ->required()
                            ->options([
                                'Ativo' => 'Ativo',
                                'Inativo' => 'Inativo',
                            ]),

                        Toggle::make('recebe_pedidos_iniciais')
                            ->label('Recebe os pedidos iniciais')
                            ->helperText('Somente um setor pode ficar marcado como responsavel geral.')
                            ->live(),

                        Select::make('encaminha_pedido_para_setor_ids')
                            ->label('Encaminha para outros setores')
                            ->options(fn (?Setor $record) => Setor::query()
                                ->where('ativo', true)
                                ->when($record?->exists, fn ($query) => $query->whereKeyNot($record->getKey()))
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->toArray())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->required(fn (Get $get) => ! $get('recebe_pedidos_iniciais'))
                            ->disabled(fn (Get $get) => (bool) $get('recebe_pedidos_iniciais'))
                            ->dehydrated(fn (Get $get) => ! $get('recebe_pedidos_iniciais'))
                            ->helperText('Selecione um ou mais setores para onde este setor pode encaminhar pedidos.'),

                        Placeholder::make('fluxo_resumo')
                            ->label('Resumo do fluxo')
                            ->content(fn (Get $get): string => $get('recebe_pedidos_iniciais')
                                ? 'Este setor sera a porta de entrada dos pedidos e podera gerenciar todos os demais.'
                                : 'Este setor podera encaminhar pedidos para um ou mais setores selecionados acima.')
                            ->columnSpanFull(),
                    ]),
                ]),
        ]);
    }
}
