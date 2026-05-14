<?php

namespace App\Services;

use App\Models\Enums\TipoItem;
use App\Models\Enums\UnidadeMedida;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ItemService
{
    public function __construct(
        protected UserService $userService
    ) {}

    public function configurarTabela(Table $table, ?User $user): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
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
            TextColumn::make('codigo')
                ->label('Codigo')
                ->badge()
                ->color('primary')
                ->sortable()
                ->searchable()
                ->copyable()
                ->copyMessage('Codigo copiado')
                ->copyMessageDuration(1500)
                ->tooltip('Clique para copiar'),

            TextColumn::make('nome')
                ->label('Nome')
                ->sortable()
                ->searchable(),

            TextColumn::make('tipo_item')
                ->label('Tipo')
                ->badge()
                ->formatStateUsing(fn ($state) => $state instanceof TipoItem ? $state->label() : TipoItem::tryFrom($state)?->label() ?? $state)
                ->sortable(),

            TextColumn::make('unidade_medida')
                ->label('Unidade de Medida')
                ->sortable(),

            TextColumn::make('descricao')
                ->label('Descricao')
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
                ->visible(fn () => $this->userService->podeExcluirItens(Auth::user())),
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
                ->visible(fn () => $this->userService->podeExcluirItensEmMassa(Auth::user())),
        ];
    }

    public static function configurarFormulario(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificacao do Item')
                    ->description('O codigo UUID e gerado automaticamente e usado em buscas, contratos e importacoes.')
                    ->schema([
                        TextInput::make('codigo')
                            ->label('Codigo')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (?string $operation): bool => $operation !== 'create')
                            ->helperText('Gerado automaticamente ao criar o item.')
                            ->maxLength(255)
                            ->columnSpanFull(),

                        TextInput::make('nome')
                            ->label('Nome')
                            ->helperText('Use um nome claro para facilitar localizacao e vinculacao em contratos.')
                            ->required()
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),

                Section::make('Classificacao')
                    ->description('Defina como o item sera agrupado e exibido em relatorios e listagens.')
                    ->schema([
                        Grid::make(2)
                            ->schema([
                                Select::make('tipo_item')
                                    ->label('Tipo do Item')
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->options(
                                        collect(TipoItem::cases())
                                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                            ->toArray()
                                    ),

                                Select::make('unidade_medida')
                                    ->label('Unidade de Medida')
                                    ->required()
                                    ->native(false)
                                    ->searchable()
                                    ->options(
                                        collect(UnidadeMedida::cases())
                                            ->mapWithKeys(fn ($case) => [$case->value => $case->label()])
                                            ->toArray()
                                    ),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Detalhes Adicionais')
                    ->description('Campo opcional para observacoes curtas sobre apresentacao, uso ou identificacao.')
                    ->schema([
                        Textarea::make('descricao')
                            ->label('Descricao')
                            ->helperText('Exemplo: embalagem, especificacao ou observacao util para a equipe.')
                            ->maxLength(100)
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
