<?php

namespace App\Filament\Admin\Resources\Roles;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Filament\Admin\Resources\Roles\Pages\ManageRoles;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleService;
use App\Services\UserService;
use App\Services\UserSetorAccessService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Spatie\Permission\PermissionRegistrar;
use UnitEnum;

class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::ShieldCheck;

    protected static ?string $navigationParentItem = 'Servidores';

    public static ?string $modelLabel = 'Nível de acesso';

    public static ?string $label = 'Níveis de acesso';

    protected static string|UnitEnum|null $navigationGroup = 'Acesso';

    public static ?string $pluralLabel = 'Níveis de acesso';

    public static ?string $navigationLabel = 'Níveis de acesso';

    public static ?string $pluralModelLabel = 'Níveis de acesso';

    public static ?string $slug = 'niveis-de-acesso';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Components\TextInput::make('name')
                    ->columnSpanFull()
                    ->label('Nível de acesso')
                    ->required()
                    ->disabled(fn ($record, $context) => app(RoleService::class)->bloquearCampo($record, $context))
                    ->unique(ignoreRecord: true),

                Components\Select::make('setor_id')
                    ->columnSpanFull()
                    ->label('Setor legado')
                    ->helperText('Mantido apenas para compatibilidade. O escopo operacional novo vem do setor vinculado ao usuário.')
                    ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->disabled()
                    ->dehydrated(false),

                Section::make('Permissões')
                    ->description('Selecione as permissões para este nível de acesso.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        Components\TextInput::make('search_permissions')
                            ->label('Buscar permissões')
                            ->placeholder('Digite para filtrar as permissões...')
                            ->live(debounce: 300)
                            ->dehydrated(false),

                        Group::make()
                            ->schema(function (?Role $record, Get $get) {
                                $todasPermissoes = app(RoleService::class)
                                    ->permissoesDisponiveisPara(Auth::user());
                                $busca = $get('search_permissions');

                                // Filtra permissões se houver busca
                                if ($busca) {
                                    $todasPermissoes = $todasPermissoes->filter(function ($perm) use ($busca) {
                                        return str_contains(
                                            strtolower($perm->name),
                                            strtolower($busca)
                                        );
                                    });
                                }

                                // Permissões já atribuídas a esta role
                                $permissoesAtribuidas = $record?->permissions
                                    ->pluck('name')
                                    ->toArray() ?? [];

                                // Agrupa pelo prefixo (primeira palavra)
                                $agrupadas = $todasPermissoes->groupBy(function ($perm) {
                                    return explode(' ', $perm->name)[0];
                                });

                                $schema = [];

                                foreach ($agrupadas as $grupo => $permissoes) {
                                    $schema[] = Components\CheckboxList::make("permissions_{$grupo}")
                                        ->label($grupo)
                                        ->options(
                                            $permissoes->pluck('name', 'name')->toArray()
                                        )
                                        ->columns(3)
                                        ->afterStateHydrated(function (callable $set) use (
                                            $grupo,
                                            $permissoes,
                                            $permissoesAtribuidas
                                        ) {
                                            $valoresMarcados = collect($permissoesAtribuidas)
                                                ->intersect($permissoes->pluck('name'))
                                                ->values()
                                                ->toArray();

                                            $set("permissions_{$grupo}", $valoresMarcados);
                                        })
                                        ->dehydrated(true);
                                }

                                return $schema;
                            }),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nível de acesso')
                    ->searchable(),
                Tables\Columns\TextColumn::make('setor.nome')
                    ->label('Setor legado')
                    ->badge()
                    ->placeholder('-')
                    ->sortable()
                    ->toggleable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Actions\Action::make('editar')
                    ->label('Editar')
                    ->icon('heroicon-o-pencil-square')
                    ->color('primary')
                    ->slideOver()
                    ->visible(fn (): bool => Gate::allows('applyPermissionsAny', User::class)
                        && Gate::allows('updateAny', Role::class))
                    ->disabled(
                        fn ($record) => app(RoleService::class)->bloquearCampoEdit($record, 'edit')
                    )
                    ->modalHeading(
                        fn ($record) => 'Editar nível de acesso'
                    )
                    ->modalDescription(
                        fn ($record) => $record->name
                    )
                    ->schema(function (Role $record) {

                        return [

                            Components\TextInput::make('name')
                                ->label('Nível de acesso')
                                ->required()
                                ->disabled(
                                    fn ($record) => app(RoleService::class)->bloquearCampo($record, 'edit')
                                )
                                ->default($record->name),

                            Components\Select::make('setor_id')
                                ->label('Setor legado')
                                ->helperText('Mantido apenas para compatibilidade. O escopo operacional novo vem do usuário.')
                                ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect(Auth::user()))
                                ->searchable()
                                ->preload()
                                ->nullable()
                                ->default($record->setor_id)
                                ->disabled()
                                ->dehydrated(false),

                            Components\TextInput::make('search_permissions')
                                ->label('Buscar permissões')
                                ->live(debounce: 30)
                                ->dehydrated(false)
                                ->extraInputAttributes([
                                    'onkeydown' => 'if(event.key === "Enter") event.preventDefault()',
                                ]),

                            Components\Hidden::make('permissions_state')
                                ->default(
                                    fn (Role $record) => $record->permissions->pluck('name')->toArray()
                                )
                                ->dehydrated(true),

                            Group::make()
                                ->schema(function (Get $get) {

                                    $todasPermissoes = app(RoleService::class)
                                        ->permissoesDisponiveisPara(Auth::user());
                                    $busca = strtolower($get('search_permissions') ?? '');

                                    $porGrupo = $todasPermissoes->groupBy(
                                        fn ($p) => explode(' ', $p->name)[0]
                                    );

                                    $schema = [];

                                    foreach ($porGrupo as $grupo => $permissoes) {

                                        $filtradas = $permissoes
                                            ->when(
                                                $busca,
                                                fn ($collection) => $collection->filter(
                                                    fn ($perm) => str_contains(strtolower($perm->name), $busca)
                                                )
                                            );

                                        // 🔥 Se não tiver nenhuma permissão visível, pula o grupo
                                        if ($filtradas->isEmpty()) {
                                            continue;
                                        }

                                        $schema[] =
                                            Components\CheckboxList::make("permissions_{$grupo}")
                                                ->label($grupo)
                                                ->options(
                                                    $filtradas->pluck('name', 'name')->toArray()
                                                )
                                                ->columns(3)
                                                ->default(
                                                    fn (Get $get) => collect($get('permissions_state') ?? [])
                                                        ->intersect($permissoes->pluck('name'))
                                                        ->values()
                                                        ->toArray()
                                                )
                                                ->afterStateUpdated(function ($state, Get $get, callable $set) use ($permissoes) {

                                                    $atual = collect($get('permissions_state') ?? []);

                                                    // remove permissões desse grupo
                                                    $atual = $atual->diff($permissoes->pluck('name'));

                                                    // adiciona as novas selecionadas
                                                    $atual = $atual->merge($state ?? []);

                                                    $set('permissions_state', $atual->unique()->values()->toArray());
                                                });
                                    }

                                    return $schema;
                                }),
                        ];
                    })

                    ->action(function (Role $record, array $data): void {
                        app(RoleService::class)->atualizarRole($record, $data, Auth::user());

                        Notification::make()
                            ->title('Nível de acesso atualizado')
                            ->success()
                            ->send();
                    }),

                Actions\DeleteAction::make()
                    ->disabled(
                        fn ($record) => app(RoleService::class)->bloquearExclusao($record)
                    ),
            ])

            ->groupedBulkActions([
                VincularSetorBulkAction::make(
                    recordsLabel: 'níveis selecionados',
                    updateRecord: function (Role $record, int $setorId): void {
                        $record->update(['setor_id' => $setorId]);
                        app(PermissionRegistrar::class)->forgetCachedPermissions();
                    },
                    visible: fn (): bool => Gate::allows('applyPermissionsAny', User::class)
                        && Gate::allows('updateAny', Role::class),
                    name: 'vincular_setor_legado',
                ),

                Actions\DeleteBulkAction::make()
                    ->visible(function () {
                        $user = Auth::user();

                        return app(UserService::class)->ehAdmin($user);
                    }),
            ])
            ->checkIfRecordIsSelectableUsing(fn ($record) => app(RoleService::class)->bloquearSelecaoBulkActions($record));
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRoles::route('/'),
        ];
    }
}
