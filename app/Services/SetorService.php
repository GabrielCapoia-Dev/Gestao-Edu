<?php

namespace App\Services;

use App\Models\Setor;
use App\Models\User;
use App\Services\UserSetorAccessService;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
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
            ->defaultSort('path')
            ->striped();
    }

    private function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Caminho')
                ->formatStateUsing(fn (Setor $record): string => $record->nome_completo)
                ->searchable()
                ->sortable(),

            TextColumn::make('parent.nome')
                ->label('Setor pai')
                ->formatStateUsing(fn (Setor $record): ?string => $record->parent?->nome_completo)
                ->placeholder('-')
                ->toggleable(),

            TextColumn::make('depth')
                ->label('Nivel')
                ->sortable(),

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

                        Select::make('parent_id')
                            ->label('Setor pai')
                            ->options(function (?Setor $record): array {
                                $options = app(UserSetorAccessService::class)->optionsForSelect(auth()->user());

                                if (! $record?->exists) {
                                    return $options;
                                }

                                foreach ($record->selfAndDescendantIds() as $blockedId) {
                                    unset($options[$blockedId]);
                                }

                                return $options;
                            })
                            ->searchable()
                            ->nullable(),

                        TextInput::make('status')
                            ->label('Status textual')
                            ->default('Ativo')
                            ->maxLength(255),

                        TextInput::make('sort_order')
                            ->label('Ordenacao')
                            ->numeric()
                            ->default(0),

                        Toggle::make('is_default_root')
                            ->label('Setor raiz padrao')
                            ->helperText('Define o setor raiz configuravel usado como fallback em fluxos legados.')
                            ->default(false),

                        Toggle::make('ativo')
                            ->label('Ativo')
                            ->default(true)
                            ->helperText('Setores inativos deixam de aparecer em novos vinculos, mas continuam nos historicos.'),
                    ]),
                ]),
        ]);
    }
}
