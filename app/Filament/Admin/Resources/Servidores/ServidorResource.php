<?php

namespace App\Filament\Admin\Resources\Servidores;

use App\Filament\Admin\Resources\Servidores\Pages\ManageServidores;
use App\Models\FuncaoAdministrativa;
use App\Models\Servidor;
use App\Models\ServidorFuncaoAdministrativa;
use App\Models\User;
use App\Services\ServidorService;
use App\Services\UserService;
use App\Services\UserSetorAccessService;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ServidorResource extends Resource
{
    protected static ?string $model = Servidor::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Briefcase;

    protected static string|UnitEnum|null $navigationGroup = 'Cadastros';

    protected static ?string $navigationLabel = 'Servidores';

    protected static ?string $pluralModelLabel = 'Servidores';

    protected static ?string $modelLabel = 'Servidor';

    protected static ?string $slug = 'servidores';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Dados do servidor')
                    ->schema([
                        TextInput::make('nome')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('matricula')
                            ->label('Matrícula')
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->maxLength(255),

                        TextInput::make('telefone')
                            ->label('Telefone')
                            ->tel()
                            ->mask('(99) 99999-9999')
                            ->maxLength(255),

                        Select::make('status')
                            ->label('Status')
                            ->options(Servidor::statusOptions())
                            ->default(Servidor::STATUS_ATIVO)
                            ->required(),
                    ])
                    ->columns(2),

                Section::make('Vínculos funcionais')
                    ->schema([
                        Select::make('id_escola')
                            ->label('Escola')
                            ->options(fn (): array => app(UserService::class)->opcoesDeEscolasParaCampo(Auth::user()))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('setor_id')
                            ->label('Setor')
                            ->options(fn (): array => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                            ->searchable()
                            ->preload()
                            ->nullable(),

                        Select::make('user_id')
                            ->label('Usuário de acesso')
                            ->options(fn (): array => app(UserService::class)
                                ->listarUsuariosQuery(User::query(), Auth::user())
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->toArray())
                            ->searchable()
                            ->preload()
                            ->nullable()
                            ->helperText('A função do servidor não concede acesso ao sistema automaticamente.'),

                        Select::make('funcao_administrativa_ids')
                            ->label('Funções do servidor')
                            ->options(fn (): array => FuncaoAdministrativa::query()
                                ->where('ativo', true)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->toArray())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->required()
                            ->helperText('Selecione Professor apenas quando este servidor também precisar existir no cadastro pedagógico.')
                            ->afterStateHydrated(function (Select $component, ?Servidor $record): void {
                                if (! $record) {
                                    return;
                                }

                                $component->state(
                                    $record->funcoesAtivas()
                                        ->pluck('funcao_administrativa.id')
                                        ->map(fn ($id): int => (int) $id)
                                        ->all()
                                );
                            }),

                        Textarea::make('observacoes')
                            ->label('Observações')
                            ->maxLength(2000)
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'escola:id,nome,setor_id',
                'setor:id,nome',
                'user:id,name,email',
                'funcoesAtivas:id,nome,codigo,categoria',
            ]))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
            ->columns([
                TextColumn::make('escola.nome')
                    ->label('Escola')
                    ->searchable()
                    ->sortable()
                    ->wrap(),

                TextColumn::make('setor.nome')
                    ->label('Setor')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('matricula')
                    ->label('Matrícula')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('nome')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->wrap()
                    ->copyable(),

                TextColumn::make('funcoes_ativas')
                    ->label('Funções')
                    ->getStateUsing(fn (Servidor $record): array => $record->funcoesAtivas->pluck('nome')->sort()->values()->all())
                    ->badge()
                    ->separator(',')
                    ->wrap(),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado')
                    ->color(fn (?string $state): string => match ($state) {
                        Servidor::STATUS_ATIVO => 'success',
                        Servidor::STATUS_INATIVO => 'gray',
                        default => 'warning',
                    })
                    ->sortable(),

                TextColumn::make('user.name')
                    ->label('Usuário')
                    ->placeholder('Sem acesso')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('funcao_administrativa_id')
                    ->label('Função')
                    ->options(fn (): array => FuncaoAdministrativa::query()
                        ->where('ativo', true)
                        ->orderBy('nome')
                        ->pluck('nome', 'id')
                        ->toArray())
                    ->query(function (Builder $query, array $data): Builder {
                        if (! filled($data['value'] ?? null)) {
                            return $query;
                        }

                        return $query->whereHas('servidorFuncoes', function (Builder $funcoes) use ($data): void {
                            $funcoes
                                ->where('funcao_administrativa_id', (int) $data['value'])
                                ->where('status', ServidorFuncaoAdministrativa::STATUS_ATIVO);
                        });
                    }),

                SelectFilter::make('id_escola')
                    ->label('Escola')
                    ->relationship('escola', 'nome', modifyQueryUsing: fn (Builder $query): Builder => $query
                        ->where('ativo', true)
                        ->orderBy('nome'))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('setor_id')
                    ->label('Setor')
                    ->options(fn (): array => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                    ->searchable()
                    ->preload(),

                SelectFilter::make('status')
                    ->label('Status')
                    ->options(Servidor::statusOptions()),

                TernaryFilter::make('user_id')
                    ->label('Usuário vinculado')
                    ->trueLabel('Com usuário')
                    ->falseLabel('Sem usuário')
                    ->placeholder('Todos')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereNotNull('user_id'),
                        false: fn (Builder $query): Builder => $query->whereNull('user_id'),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->modalHeading(fn (Servidor $record): string => "Detalhes - {$record->nome}")
                    ->modalWidth('3xl')
                    ->schema([
                        Section::make('Informações do servidor')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('nome')->label('Nome'),
                                \Filament\Infolists\Components\TextEntry::make('matricula')->label('Matrícula')->placeholder('Não informada'),
                                \Filament\Infolists\Components\TextEntry::make('escola.nome')->label('Escola')->placeholder('Não vinculada'),
                                \Filament\Infolists\Components\TextEntry::make('setor.nome')->label('Setor')->placeholder('Não vinculado'),
                                \Filament\Infolists\Components\TextEntry::make('user.name')->label('Usuário')->placeholder('Sem acesso'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Status')
                                    ->formatStateUsing(fn (?string $state): string => Servidor::statusOptions()[$state] ?? 'Não informado'),
                            ])
                            ->columns(2),

                        Section::make('Funções')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('funcoes_lista')
                                    ->label('Funções ativas')
                                    ->getStateUsing(fn (Servidor $record): array => $record->funcoesAtivas()->pluck('nome')->sort()->values()->all())
                                    ->badge()
                                    ->separator(','),
                            ]),
                    ]),

                EditAction::make()
                    ->using(function (Servidor $record, array $data): Servidor {
                        $funcaoIds = $data['funcao_administrativa_ids'] ?? [];
                        unset($data['funcao_administrativa_ids']);

                        return app(ServidorService::class)->atualizarServidorComFuncoes($record, $data, $funcaoIds);
                    }),

                DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageServidores::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return app(ServidorService::class)->aplicarEscopoVisibilidade(
            parent::getEloquentQuery(),
            Auth::user(),
        );
    }
}
