<?php

namespace App\Filament\Admin\Resources\FuncaoAdministrativas;

use App\Filament\Admin\Resources\FuncaoAdministrativas\Pages\ManageFuncaoAdministrativas;
use App\Models\FuncaoAdministrativa;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;
use UnitEnum;

class FuncaoAdministrativaResource extends Resource
{
    protected static ?string $model = FuncaoAdministrativa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookmarkSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 2;

    protected static bool $shouldRegisterNavigation = false;

    public static ?string $modelLabel = 'Função Administrativa';

    public static ?string $pluralModelLabel = 'Funções Administrativas';

    public static ?string $slug = 'funcoes-administrativas';

    public static function canAccess(): bool
    {
        return Gate::allows('viewAny', FuncaoAdministrativa::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                Hidden::make('criando_nova_categoria')
                    ->default(false)
                    ->dehydrated(false),

                Select::make('categoria')
                    ->label('Categoria')
                    ->options(fn (Get $get): array => FuncaoAdministrativa::categoriasOptions($get('categoria')))
                    ->required()
                    ->searchable()
                    ->hidden(fn (Get $get): bool => (bool) $get('criando_nova_categoria'))
                    ->suffixAction(
                        Action::make('adicionar_categoria')
                            ->label('Adicionar categoria')
                            ->tooltip('Adicionar categoria')
                            ->icon(Heroicon::Plus)
                            ->color('gray')
                            ->action(function (Set $set): void {
                                $set('nova_categoria', null);
                                $set('criando_nova_categoria', true);
                            }),
                        isInline: true,
                    ),

                TextInput::make('nova_categoria')
                    ->label('Nova categoria')
                    ->placeholder('Informe a nova categoria')
                    ->maxLength(255)
                    ->live()
                    ->dehydrated(false)
                    ->required(fn (Get $get): bool => (bool) $get('criando_nova_categoria'))
                    ->visible(fn (Get $get): bool => (bool) $get('criando_nova_categoria'))
                    ->suffixActions([
                        Action::make('confirmar_categoria')
                            ->label('Confirmar categoria')
                            ->tooltip('Confirmar categoria')
                            ->icon(Heroicon::Check)
                            ->color('success')
                            ->disabled(fn (Get $get): bool => FuncaoAdministrativa::normalizarCategoria($get('nova_categoria')) === '')
                            ->action(function (Get $get, Set $set): void {
                                $categoria = FuncaoAdministrativa::normalizarCategoria($get('nova_categoria'));

                                if ($categoria === '') {
                                    return;
                                }

                                $set('categoria', $categoria);
                                $set('nova_categoria', null);
                                $set('criando_nova_categoria', false);
                            }),

                        Action::make('cancelar_categoria')
                            ->label('Cancelar')
                            ->tooltip('Cancelar')
                            ->icon(Heroicon::XMark)
                            ->color('gray')
                            ->action(function (Set $set): void {
                                $set('nova_categoria', null);
                                $set('criando_nova_categoria', false);
                            }),
                    ], isInline: true),

                Toggle::make('ativo')
                    ->label('Ativa')
                    ->default(true)
                    ->required(),

                Toggle::make('exige_professor')
                    ->label('Exige vínculo com professor')
                    ->helperText('Use apenas para funções que precisam criar ou vincular um registro pedagógico de professor.')
                    ->default(false)
                    ->required(),

                Toggle::make('tem_relacao_turma')
                    ->label('Tem relação com turmas')
                    ->default(false)
                    ->required(),

                Toggle::make('direcao_escolar')
                    ->label('Direção escolar')
                    ->helperText('Usada para preencher o campo de diretor(a) nos documentos de avaliação.')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (?bool $state, Set $set): void {
                        if ($state) {
                            $set('coordenacao_pedagogica', false);
                            $set('secretaria_escolar', false);
                            $set('tem_relacao_turma', false);
                        }
                    })
                    ->required(),

                Toggle::make('coordenacao_pedagogica')
                    ->label('Coordenação pedagógica')
                    ->helperText('Usada para preencher o campo de coordenação pedagógica nos documentos de avaliação.')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (?bool $state, Set $set): void {
                        if ($state) {
                            $set('direcao_escolar', false);
                            $set('secretaria_escolar', false);
                            $set('tem_relacao_turma', true);
                        }
                    })
                    ->required(),

                Toggle::make('secretaria_escolar')
                    ->label('Secretaria escolar')
                    ->helperText('Identifica explicitamente a função de Secretário da Equipe Gestora.')
                    ->default(false)
                    ->live()
                    ->afterStateUpdated(function (?bool $state, Set $set): void {
                        if ($state) {
                            $set('direcao_escolar', false);
                            $set('coordenacao_pedagogica', false);
                            $set('tem_relacao_turma', false);
                        }
                    })
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable(),

                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FuncaoAdministrativa::categoriaLabel($state))
                    ->sortable(),

                IconColumn::make('ativo')
                    ->label('Ativa')
                    ->boolean(),

                IconColumn::make('tem_relacao_turma')
                    ->label('Relação com turmas')
                    ->boolean(),

                IconColumn::make('exige_professor')
                    ->label('Exige professor')
                    ->boolean(),

                IconColumn::make('direcao_escolar')
                    ->label('Direção escolar')
                    ->boolean(),

                IconColumn::make('coordenacao_pedagogica')
                    ->label('Coordenação pedagógica')
                    ->boolean(),

                IconColumn::make('secretaria_escolar')
                    ->label('Secretaria escolar')
                    ->boolean(),

                TextColumn::make('updated_at')
                    ->label('Atualizada')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                DeleteBulkAction::make()
                    ->visible(fn (): bool => Gate::allows('deleteAny', FuncaoAdministrativa::class)),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFuncaoAdministrativas::route('/'),
        ];
    }
}
