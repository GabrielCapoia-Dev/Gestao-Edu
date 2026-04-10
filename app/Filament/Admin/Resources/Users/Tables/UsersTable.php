<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\PermissionRegistrar;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        /** @var \App\Models\User */
        $user = Auth::user();
        $service = app(UserService::class);

        return $table
            ->modifyQueryUsing(fn(Builder $query) => $service->listarUsuariosQuery($query, $user))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->checkIfRecordIsSelectableUsing(fn(User $record) => $service->podeSelecionarRegistro($user, $record))
            ->columns(self::columns($service, $user))
            ->recordActions(self::recordActions($service, $user))
            ->groupedBulkActions(self::bulkActions($service, $user))
            ->defaultSort('updated_at', 'desc')
            ->striped();
    }

    // -------------------------------------------------------------------------
    // Colunas
    // -------------------------------------------------------------------------

    private static function columns(UserService $service, User $user): array
    {
        return [

            \Filament\Tables\Columns\TextColumn::make('setor.nome')
                ->label('Setor')
                ->sortable()
                ->toggleable()
                ->visible(fn() => $service->podeVisualizarSetor($user)),

            \Filament\Tables\Columns\TextColumn::make('escola.nome')
                ->label('Escola')
                ->wrap()
                ->sortable()
                ->grow(false)
                ->searchable(),

            \Filament\Tables\Columns\TextColumn::make('name')
                ->label('Nome de usuário')
                ->wrap()
                ->sortable()
                ->grow(false)
                ->searchable(),

            \Filament\Tables\Columns\TextColumn::make('email')
                ->label('E-mail')
                ->wrap()
                ->copyable()
                ->alignCenter()
                ->grow(false)
                ->searchable(),

            \Filament\Tables\Columns\ToggleColumn::make('email_approved')
                ->label('Verificação')
                ->sortable()
                ->alignCenter()
                ->grow(false)
                ->disabled(
                    fn(User $record) =>
                    $service->desabilitarToggleAprovacaoEmail(Auth::user(), $record)
                )
                ->visible(fn() => $service->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table'))
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->columnSpan(1),

            \Filament\Tables\Columns\TextColumn::make('email_verified_at')
                ->label('Verificado em')
                ->grow(false)
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true)
                ->formatStateUsing(function ($state, User $record) {
                    if (! $record->email_approved) {
                        return '--/--/-- --:--:--';
                    }
                    return $state ? $state->format('d/m/Y H:i:s') : '-';
                }),

            \Filament\Tables\Columns\TextColumn::make('roles')
                ->label('Niveis de acesso')
                ->grow(false)
                ->wrap()
                ->getStateUsing(fn(User $record) => $record->roles->pluck('name')->join(', ') ?: '-')
                ->toggleable(isToggledHiddenByDefault: false),

            \Filament\Tables\Columns\TextColumn::make('created_at')
                ->label('Criado em')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            \Filament\Tables\Columns\TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    // -------------------------------------------------------------------------
    // Ações de linha (record actions)
    // -------------------------------------------------------------------------

    private static function recordActions(UserService $service, User $user): array
    {
        return [

            Action::make('permissoes')
                ->label('Permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->slideOver()
                ->modalSubmitActionLabel('Salvar')
                ->modalSubmitAction(fn(Action $action) => $action->color('primary'))
                ->visible(function (User $record) use ($service, $user) {
                    if ($record->id === $user->id) return false;
                    if ($record->hasRole('Admin')) return false;
                    return $user->hasPermissionTo('Aplicar Permissoes');
                })
                ->modalHeading(fn(User $record) => 'Permissões do usuário')
                ->modalDescription(fn(User $record) => "{$record->name} • {$record->email}")
                ->modalIcon('heroicon-o-key')
                ->schema(function (User $record) use ($service, $user) {
                    return [
                        TextInput::make('buscar_permissao')
                            ->label('Pesquisar permissão')
                            ->placeholder('Ex: listar, editar, excluir...')
                            ->live(debounce: 30)
                            ->extraInputAttributes([
                                'onkeydown' => 'if(event.key === "Enter" || event.keyCode === 13) event.preventDefault()',
                            ])
                            ->dehydrated(false),

                        Components\Group::make()
                            ->schema(function (Get $get) use ($service, $record, $user) {
                                return $service->checkboxesPermissoesComEstado($record, $get, $user);
                            }),
                    ];
                })
                ->action(function (User $record, array $data) {
                    $permissoesSelecionadas = collect($data)
                        ->filter(fn($_, $key) => str_starts_with($key, 'permissions_'))
                        ->flatten()
                        ->unique()
                        ->values();

                    $permissoesAtuais = $record->getDirectPermissions()->pluck('name');

                    $permissoesDaRole = $record->roles
                        ->flatMap(fn($role) => $role->permissions)
                        ->pluck('name')
                        ->toArray();

                    $paraRemover = $permissoesAtuais->diff($permissoesSelecionadas);

                    $paraAdicionar = $permissoesSelecionadas
                        ->diff($permissoesAtuais)
                        ->diff($permissoesDaRole);

                    if ($paraRemover->isNotEmpty()) {
                        $record->revokePermissionTo($paraRemover->toArray());
                    }

                    if ($paraAdicionar->isNotEmpty()) {
                        $record->givePermissionTo($paraAdicionar->toArray());
                    }

                    if ($paraRemover->isNotEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Permissões removidas')
                            ->body($paraRemover->map(fn($p) => "• {$p}")->implode('<br>'))
                            ->danger()
                            ->icon('heroicon-s-x-mark')
                            ->send();
                    }

                    if ($paraAdicionar->isNotEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Permissões adicionadas')
                            ->body($paraAdicionar->map(fn($p) => "• {$p}")->implode('<br>'))
                            ->success()
                            ->icon('heroicon-s-check')
                            ->send();
                    }

                    if ($paraRemover->isEmpty() && $paraAdicionar->isEmpty()) {
                        \Filament\Notifications\Notification::make()
                            ->title('Nenhuma alteração foi realizada')
                            ->info()
                            ->send();
                    }
                }),

            EditAction::make(),

            \Filament\Actions\DeleteAction::make()
                ->before(function (User $record, \Filament\Actions\DeleteAction $action) use ($service, $user) {
                    if (! $service->podeDeletar($user, $record)) {
                        $action->failure();
                        $action->halt();
                    }
                })
                ->disabled(fn(User $record) => ($record->id === 1) || (Auth::id() === $record->id))
                ->visible(fn() => $service->ehAdmin(Auth::user())),
        ];
    }

    // -------------------------------------------------------------------------
    // Ações em massa (bulk actions)
    // -------------------------------------------------------------------------

    private static function bulkActions(UserService $service, User $user): array
    {
        return [

            Action::make('verificacao_em_massa')
                ->label('Verificacao de acesso')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->slideOver()
                ->visible(fn() => $service->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn(Action $action) => $action->label('Fechar'))
                ->modalHeading('Verificacao de acesso em massa')
                ->modalDescription('Aprove ou desaprove o acesso dos usuarios selecionados. O Admin do sistema sempre sera ignorado.')
                ->modalIcon('heroicon-o-check-badge')
                ->schema(fn() => [
                    Select::make('acao_verificacao')
                        ->label('Acao')
                        ->options([
                            'approve' => 'Aprovar acesso',
                            'disapprove' => 'Desaprovar acesso',
                        ])
                        ->default('approve')
                        ->selectablePlaceholder(false)
                        ->required(),
                ])
                ->action(function ($records, array $data) {
                    $acao = $data['acao_verificacao'] ?? 'approve';
                    $aprovar = $acao === 'approve';

                    $afetados = 0;
                    $ignorados = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof User) {
                            continue;
                        }

                        if ($record->id === 1 || $record->hasRole('Admin')) {
                            $ignorados++;
                            continue;
                        }

                        $record->update([
                            'email_approved' => $aprovar,
                        ]);

                        $afetados++;
                    }

                    Notification::make()
                        ->title($aprovar ? 'Acessos aprovados' : 'Acessos desaprovados')
                        ->body(
                            $ignorados > 0
                                ? "{$afetados} usuario(s) atualizados. {$ignorados} admin(s) ignorado(s)."
                                : "{$afetados} usuario(s) atualizados."
                        )
                        ->success()
                        ->send();
                }),

            Action::make('niveis_em_massa')
                ->label('Editar niveis')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->slideOver()
                ->visible(fn() => $user->hasPermissionTo('Aplicar Permissoes'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn(Action $action) => $action->label('Fechar'))
                ->modalHeading('Editar niveis em massa')
                ->modalDescription('Adicione, substitua ou remova niveis de acesso dos usuarios selecionados.')
                ->modalIcon('heroicon-o-shield-check')
                ->schema(fn() => [
                    Select::make('modo_roles')
                        ->label('Como aplicar')
                        ->options([
                            'add' => 'Adicionar niveis',
                            'replace' => 'Substituir niveis atuais',
                            'remove' => 'Remover niveis selecionados',
                        ])
                        ->default('add')
                        ->selectablePlaceholder(false)
                        ->required(),

                    Select::make('roles')
                        ->label('Niveis de acesso')
                        ->helperText('Selecione um ou mais niveis para os usuarios escolhidos.')
                        ->options(fn() => $service->opcoesDeRolesParaSelect($user))
                        ->multiple()
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function ($records, array $data) use ($service) {
                    $roleIds = $service->idsDeRolesSelecionadas($data);

                    if (empty($roleIds)) {
                        return;
                    }

                    $modo = $data['modo_roles'] ?? 'add';

                    $afetados = 0;

                    foreach ($records as $record) {
                        if ($record->hasRole('Admin')) {
                            continue;
                        }

                        $roleIdsAtuais = $record->roles()->pluck('id')->map(fn($roleId) => (int) $roleId);

                        $novosRoleIds = match ($modo) {
                            'replace' => collect($roleIds)->values(),
                            'remove' => $roleIdsAtuais->diff($roleIds)->values(),
                            default => $roleIdsAtuais->merge($roleIds)->unique()->values(),
                        };

                        $record->syncRoles(
                            Role::query()->whereIn('id', $novosRoleIds->all())->get()
                        );

                        $afetados++;
                    }

                    app(PermissionRegistrar::class)->forgetCachedPermissions();

                    Notification::make()
                        ->title('Niveis de acesso atualizados')
                        ->body("{$afetados} usuario(s) atualizados.")
                        ->success()
                        ->send();
                }),

            Action::make('permissoes_em_massa')
                ->label('Editar permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->slideOver()
                ->visible(fn() => $user->hasPermissionTo('Aplicar Permissoes'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn(Action $action) => $action->label('Fechar'))
                ->modalHeading('Editar permissões em massa')
                ->modalDescription('As permissões selecionadas serão aplicadas aos usuários escolhidos.')
                ->modalIcon('heroicon-o-key')
                ->schema(fn() => [
                    Select::make('modo_permissoes')
                        ->label('Como aplicar')
                        ->options([
                            'add' => 'Adicionar permissoes',
                            'replace' => 'Substituir permissoes diretas',
                            'remove' => 'Remover permissoes diretas',
                        ])
                        ->default('add')
                        ->selectablePlaceholder(false)
                        ->required(),

                    Components\Section::make('Permissões')
                        ->collapsible()
                        ->schema(fn(Get $get) => [
                            TextInput::make('buscar_permissao')
                                ->label('Pesquisar permissão')
                                ->placeholder('Ex: listar, editar, excluir...')
                                ->live(debounce: 100)
                                ->extraInputAttributes([
                                    'onkeydown' => 'if(event.key === "Enter" || event.keyCode === 13) event.preventDefault()',
                                ])
                                ->dehydrated(false),

                            ...$service->checkboxesPermissoesEmMassa($get, $user),
                        ]),
                ])
                ->action(function ($records, array $data) use ($service) {
                    $modo = $data['modo_permissoes'] ?? 'add';
                    $permissoesSelecionadas = $service->permissoesSelecionadas($data);

                    if ($permissoesSelecionadas->isEmpty()) {
                        return;
                    }

                    $afetados = 0;

                    foreach ($records as $record) {
                        if ($record->hasRole('Admin')) {
                            continue;
                        }

                        $record->load('roles.permissions');

                        $permissoesHerdadas = $record->roles
                            ->flatMap(fn($role) => $role->permissions->pluck('name'))
                            ->unique()
                            ->values();

                        $permissoesDiretas = $record->getDirectPermissions()->pluck('name');

                        if ($modo === 'replace') {
                            $record->syncPermissions(
                                $permissoesSelecionadas->diff($permissoesHerdadas)->values()->all()
                            );
                        } elseif ($modo === 'remove') {
                            $paraRemover = $permissoesDiretas
                                ->intersect($permissoesSelecionadas)
                                ->values()
                                ->all();

                            if (! empty($paraRemover)) {
                                $record->revokePermissionTo($paraRemover);
                            }
                        } else {
                            $paraAdicionar = $permissoesSelecionadas
                                ->diff($permissoesHerdadas)
                                ->diff($permissoesDiretas)
                                ->values()
                                ->all();

                            if (! empty($paraAdicionar)) {
                                $record->givePermissionTo($paraAdicionar);
                            }
                        }

                        $afetados++;
                    }

                    app(PermissionRegistrar::class)->forgetCachedPermissions();

                    Notification::make()
                        ->title('Permissoes atualizadas')
                        ->body("{$afetados} usuario(s) atualizados.")
                        ->success()
                        ->send();
                }),

            DeleteBulkAction::make()
                ->before(function ($records, $action) use ($service, $user) {
                    if (! $service->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn() => $service->ehAdmin(Auth::user())),
        ];
    }
}
