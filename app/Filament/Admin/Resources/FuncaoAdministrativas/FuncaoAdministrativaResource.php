<?php

namespace App\Filament\Admin\Resources\FuncaoAdministrativas;

use App\Filament\Admin\Resources\FuncaoAdministrativas\Pages\ManageFuncaoAdministrativas;
use App\Models\FuncaoAdministrativa;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class FuncaoAdministrativaResource extends Resource
{
    protected static ?string $model = FuncaoAdministrativa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BookmarkSquare;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?int $navigationSort = 2;

    public static ?string $modelLabel = 'Função Administrativa';

    public static ?string $pluralModelLabel = 'Funções Administrativas';

    public static ?string $slug = 'funcoes-administrativas';

    public static function canAccess(): bool
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        return $user->hasPermissionTo('Listar Funções Administrativas');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('nome')
                    ->label('Nome')
                    ->required()
                    ->maxLength(255),

                TextInput::make('codigo')
                    ->label('Código')
                    ->maxLength(255)
                    ->helperText('Identificador técnico gerado automaticamente quando ficar em branco.'),

                Select::make('categoria')
                    ->label('Categoria')
                    ->options(FuncaoAdministrativa::categoriasOptions())
                    ->default(FuncaoAdministrativa::CATEGORIA_GERAL)
                    ->required(),

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
                    ->required(),

                Toggle::make('coordenacao_pedagogica')
                    ->label('Coordenação pedagógica')
                    ->helperText('Usada para preencher o campo de coordenação pedagógica nos documentos de avaliação.')
                    ->default(false)
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

                TextColumn::make('codigo')
                    ->label('Código')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('categoria')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => FuncaoAdministrativa::categoriasOptions()[$state] ?? 'Geral')
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
                    ->visible(function (): bool {
                        /** @var \App\Models\User $user */
                        $user = Auth::user();

                        return $user->hasPermissionTo('Excluir Funções Administrativas em Massa');
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageFuncaoAdministrativas::route('/'),
        ];
    }
}
