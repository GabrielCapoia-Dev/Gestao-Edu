<?php

namespace App\Services;

use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use App\Services\UserService;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Schema;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\DeleteAction;
use App\Models\Enums\UnidadeMedida;
use App\Models\Enums\TipoItem;


class ItemService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->columns($this->colunasTabela())
            ->recordActions($this->acoesTabela($user))
            ->toolbarActions($this->acoesEmMassa($user))
            ->filters($this->filtrosTabela())
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public function colunasTabela(): array
    {
        return [
            TextColumn::make('nome')
                ->label('Nome')
                ->sortable()
                ->searchable(),

            TextColumn::make('tipo_item')
                ->label('Tipo')
                ->badge()
                ->formatStateUsing(fn($state) => $state instanceof TipoItem ? $state->label() : TipoItem::tryFrom($state)?->label() ?? $state)
                ->sortable(),

            TextColumn::make('unidade_medida')
                ->label('Unidade de Medida')
                ->sortable(),

            TextColumn::make('descricao')
                ->label('Descrição')
                ->sortable()
                ->searchable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->sortable()
                ->dateTime()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public function acoesTabela(?User $user): array
    {
        return [
            EditAction::make(),
            DeleteAction::make()
                ->visible(fn() => $this->userService->podeExcluirItens(Auth::user())),
        ];
    }

    private function filtrosTabela(): array
    {
        return [];
    }

    private function acoesEmMassa(?User $user): array
    {
        return [
            DeleteBulkAction::make()
                ->visible(fn() => $this->userService->podeExcluirItensEmMassa(Auth::user())),
        ];
    }

    public static function configurarFormulario(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome')
                    ->helperText('Digite o nome do Item')
                    ->required(),

                Select::make('tipo_item')
                    ->label('Tipo do Item')
                    ->required()
                    ->native(false)
                    ->options(
                        collect(TipoItem::cases())
                            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                            ->toArray()
                    ),

                Select::make('unidade_medida')
                    ->label('Unidade de Medida')
                    ->required()
                    ->native(false)
                    ->options(
                        collect(UnidadeMedida::cases())
                            ->mapWithKeys(fn($case) => [$case->value => $case->label()])
                            ->toArray()
                    ),

                Textarea::make('descricao')
                    ->label('Descrição')
                    ->helperText('Digite a descrição do Item')
                    ->maxLength(100)
                    ->columnSpanFull(),
            ]);
    }
}