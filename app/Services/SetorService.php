<?php

namespace App\Services;

use App\Models\Enums\SetorAccessCapability;
use App\Models\Setor;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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

            TextColumn::make('resumo_acessos')
                ->label('Acessos externos')
                ->state(function (Setor $record): string {
                    $acessos = $record->acessosConcedidos;

                    return collect([
                        'L '.$acessos->where('pode_listar', true)->count(),
                        'E '.$acessos->where('pode_editar', true)->count(),
                        'C '.$acessos->where('pode_cancelar', true)->count(),
                        'Enc '.$acessos->where('pode_encaminhar', true)->count(),
                    ])->join(' | ');
                })
                ->toggleable(isToggledHiddenByDefault: true),
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
                            ->helperText('Setores inativos deixam de aparecer em novos vinculos, mas continuam nos históricos.'),
                    ]),
                ]),

            Section::make('Acesso a outros setores')
                ->description('As permissões gerais do usuário continuam obrigatórias. O próprio setor recebe listar, editar e cancelar automaticamente.')
                ->columnSpanFull()
                ->visible(fn (?Setor $record): bool => $record?->exists && $this->podeConfigurarMatriz(auth()->user()))
                ->schema([
                    Grid::make(2)->schema([
                        $this->selectCapacidade(
                            relation: 'setoresListaveis',
                            label: 'Pode listar pedidos dos setores',
                            capability: SetorAccessCapability::LISTAR,
                        ),
                        $this->selectCapacidade(
                            relation: 'setoresEditaveis',
                            label: 'Pode editar pedidos dos setores',
                            capability: SetorAccessCapability::EDITAR,
                        ),
                        $this->selectCapacidade(
                            relation: 'setoresCancelaveis',
                            label: 'Pode cancelar pedidos dos setores',
                            capability: SetorAccessCapability::CANCELAR,
                        ),
                        $this->selectCapacidade(
                            relation: 'setoresEncaminhaveis',
                            label: 'Pode encaminhar pedidos para os setores',
                            capability: SetorAccessCapability::ENCAMINHAR,
                        ),
                    ]),
                ]),
        ]);
    }

    public function podeConfigurarMatriz(?User $user): bool
    {
        return (bool) ($user?->hasPermissionTo('Editar Setores')
            && app(UserSetorAccessService::class)->hasGlobalAccess($user));
    }

    private function selectCapacidade(
        string $relation,
        string $label,
        SetorAccessCapability $capability,
    ): Select {
        return Select::make($relation)
            ->label($label)
            ->multiple()
            ->relationship(
                name: $relation,
                titleAttribute: 'nome',
                modifyQueryUsing: fn ($query, ?Setor $record) => $query
                    ->where('ativo', true)
                    ->when($record?->exists, fn ($builder) => $builder->whereKeyNot($record->id))
                    ->orderedTree(),
            )
            ->getOptionLabelFromRecordUsing(fn (Setor $record): string => $record->nome_completo)
            ->saveRelationshipsUsing(function (Setor $record, mixed $state) use ($capability): void {
                app(SetorPedidoAccessService::class)->syncCapability(
                    $record,
                    $capability,
                    is_array($state) ? $state : [],
                );
            })
            ->searchable()
            ->preload();
    }
}
