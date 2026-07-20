<?php

namespace App\Filament\Admin\Resources\DominioEmails;

use App\Filament\Admin\Resources\DominioEmails\Pages\ManageDominioEmails;
use App\Models\DominioEmail;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\DominioEmailResource\Pages;
use App\Filament\Resources\DominioEmailResource\RelationManagers;
use Filament\Forms;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Illuminate\Support\Facades\Gate;
use UnitEnum;


class DominioEmailResource extends Resource
{
    protected static ?string $model = DominioEmail::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Envelope;

    public static ?string $label = 'Domínio permitido';

    public static ?string $pluralLabel = 'Domínios permitidos';

    protected static ?string $navigationParentItem = 'Servidores';

    public static ?string $modelLabel = 'Domínio permitido';

    protected static string | UnitEnum | null $navigationGroup = 'Acesso';

    public static ?string $navigationLabel = 'Domínios permitidos';

    public static ?string $pluralModelLabel = 'Domínios permitidos';

    public static ?string $slug = 'dominio-emails';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('dominio_email')
                    ->label('Domínio permitido')
                    ->required()
                    ->unique(ignoreRecord: true)
                    ->rule('regex:/^[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/')
                    ->helperText('Digite o domínio sem @. Exemplo: dominio.com.br.')
                    ->placeholder('dominio.com.br'),

                TextInput::make('nome')
                    ->label('Nome:')
                    ->required()
                    ->minLength(3)
                    ->maxLength(100)
                    ->rule('regex:/^\p{L}+(?:\s\p{L}+)*$/u')
                    ->validationMessages([
                        'regex' => 'Use apenas letras, sem caracteres especiais.',
                    ]),

                Toggle::make('status')
                    ->label('Status')
                    ->onColor('success')
                    ->offColor('danger')
                    ->onIcon('heroicon-s-check')
                    ->offIcon('heroicon-s-x-mark')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('dominio_email')
                    ->label('Domínio de e-mail')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('setor')
                    ->label('Setor')
                    ->searchable()
                    ->sortable(),
                ToggleColumn::make('status')
                    ->label('Status')
                    ->inline(false)
                    ->onColor('success')
                    ->offColor('danger')
                    ->onIcon('heroicon-s-check')
                    ->offIcon('heroicon-s-x-mark')
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status')
                    ->trueLabel('Ativos')
                    ->falseLabel('Inativos')
                    ->placeholder('Todos os domínios'),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => Gate::allows('deleteAny', DominioEmail::class)),
                ]),

            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageDominioEmails::route('/'),
        ];
    }
}
