<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Models\Role;
use App\Models\User;
use App\Services\UserService;
use App\Services\UserSetorAccessService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Spatie\Permission\PermissionRegistrar;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        /** @var User */
        $user = Auth::user();
        $service = app(UserService::class);

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $service->listarUsuariosQuery($query, $user)
                ->with(['roles:id,name', 'escola:id,nome,setor_id', 'setor:id,nome']))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(5)
            ->checkIfRecordIsSelectableUsing(fn (User $record) => $service->podeSelecionarRegistro($user, $record))
            ->columns(self::columns($service, $user))
            ->filters(self::filters($service, $user), layout: FiltersLayout::AboveContent)
            ->filtersFormColumns(4)
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

            TextColumn::make('setor.nome')
                ->label('Setor')
                ->sortable()
                ->toggleable()
                ->visible(fn () => $service->podeVisualizarSetor($user)),

            TextColumn::make('escola.nome')
                ->label('Escola')
                ->wrap()
                ->sortable()
                ->grow(false)
                ->searchable(),

            TextColumn::make('name')
                ->label('Nome de usuário')
                ->wrap()
                ->sortable()
                ->grow(false)
                ->searchable(),

            TextColumn::make('email')
                ->label('E-mail')
                ->wrap()
                ->copyable()
                ->alignCenter()
                ->grow(false)
                ->searchable(),

            ToggleColumn::make('email_approved')
                ->label('Verificação')
                ->sortable()
                ->alignCenter()
                ->grow(false)
                ->disabled(
                    fn (User $record) => $service->desabilitarToggleAprovacaoEmail(Auth::user(), $record)
                )
                ->visible(fn () => $service->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table'))
                ->inline(false)
                ->onColor('success')
                ->offColor('danger')
                ->onIcon('heroicon-s-check')
                ->offIcon('heroicon-s-x-mark')
                ->columnSpan(1),

            TextColumn::make('email_verified_at')
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

            TextColumn::make('roles')
                ->label('Níveis de acesso')
                ->grow(false)
                ->wrap()
                ->getStateUsing(fn (User $record) => $record->roles->pluck('name')->join(', ') ?: '-')
                ->toggleable(isToggledHiddenByDefault: false),

            TextColumn::make('created_at')
                ->label('Criado em')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),

            TextColumn::make('updated_at')
                ->label('Atualizado em')
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    // -------------------------------------------------------------------------
    // Filtros
    // -------------------------------------------------------------------------

    private static function filters(UserService $service, User $user): array
    {
        return [
            SelectFilter::make('setor_id')
                ->label('Setor')
                ->options(fn () => app(UserSetorAccessService::class)->optionsForSelect($user))
                ->searchable()
                ->preload()
                ->visible(fn () => $service->podeVisualizarSetor($user)),

            SelectFilter::make('id_escola')
                ->label('Escola')
                ->options(fn () => $service->opcoesDeEscolasParaCampo($user))
                ->searchable()
                ->preload(),

            SelectFilter::make('roles')
                ->label('Nível de acesso')
                ->multiple()
                ->relationship(
                    name: 'roles',
                    titleAttribute: 'name',
                    modifyQueryUsing: fn (Builder $query) => $service
                        ->opcoesDeRoles($query, $user)
                        ->orderBy('name')
                )
                ->searchable()
                ->preload(),

            TernaryFilter::make('email_approved')
                ->label('Verificação')
                ->trueLabel('Apenas aprovados')
                ->falseLabel('Apenas pendentes')
                ->placeholder('Todos')
                ->visible(fn () => $service->podeVerToggleAprovacaoEmail($user, null, 'table')),
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
                ->modalSubmitAction(fn (Action $action) => $action->color('primary'))
                ->visible(function (User $record) use ($user) {
                    if ($record->id === $user->id) {
                        return false;
                    }
                    if ($record->hasRole('Admin')) {
                        return false;
                    }

                    return $user->hasPermissionTo('Aplicar Permissoes');
                })
                ->modalHeading(fn (User $record) => 'Permissões do usuário')
                ->modalDescription(fn (User $record) => "{$record->name} • {$record->email}")
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
                        ->filter(fn ($_, $key) => str_starts_with($key, 'permissions_'))
                        ->flatten()
                        ->unique()
                        ->values();

                    $permissoesAtuais = $record->getDirectPermissions()->pluck('name');

                    $permissoesDaRole = $record->roles
                        ->flatMap(fn ($role) => $role->permissions)
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
                        Notification::make()
                            ->title('Permissões removidas')
                            ->body($paraRemover->map(fn ($p) => "• {$p}")->implode('<br>'))
                            ->danger()
                            ->icon('heroicon-s-x-mark')
                            ->send();
                    }

                    if ($paraAdicionar->isNotEmpty()) {
                        Notification::make()
                            ->title('Permissões adicionadas')
                            ->body($paraAdicionar->map(fn ($p) => "• {$p}")->implode('<br>'))
                            ->success()
                            ->icon('heroicon-s-check')
                            ->send();
                    }

                    if ($paraRemover->isEmpty() && $paraAdicionar->isEmpty()) {
                        Notification::make()
                            ->title('Nenhuma alteração foi realizada')
                            ->info()
                            ->send();
                    }
                }),

            EditAction::make(),

            DeleteAction::make()
                ->before(function (User $record, DeleteAction $action) use ($service, $user) {
                    if (! $service->podeDeletar($user, $record)) {
                        $action->failure();
                        $action->halt();
                    }
                })
                ->disabled(fn (User $record) => ($record->id === 1) || (Auth::id() === $record->id))
                ->visible(fn () => $service->ehAdmin(Auth::user())),
        ];
    }

    // -------------------------------------------------------------------------
    // Ações em massa (bulk actions)
    // -------------------------------------------------------------------------

    private static function bulkActions(UserService $service, User $user): array
    {
        return [

            Action::make('verificacao_em_massa')
                ->label('Verificação de acesso')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->accessSelectedRecords()
                ->slideOver()
                ->visible(fn () => $service->podeVerToggleAprovacaoEmail(Auth::user(), null, 'table'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn (Action $action) => $action->label('Fechar'))
                ->modalHeading('Verificação de acesso em massa')
                ->modalDescription('Aprove ou desaprove o acesso dos usuários selecionados. O Admin do sistema sempre será ignorado.')
                ->modalIcon('heroicon-o-check-badge')
                ->schema(fn () => [
                    Select::make('acao_verificacao')
                        ->label('Ação')
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
                                ? "{$afetados} usuário(s) atualizados. {$ignorados} admin(s) ignorado(s)."
                                : "{$afetados} usuário(s) atualizados."
                        )
                        ->success()
                        ->send();
                }),

            Action::make('setar_senha_padrao')
                ->label('Setar Senha Padrao')
                ->icon('heroicon-o-key')
                ->color('danger')
                ->accessSelectedRecords()
                ->visible(fn () => $service->ehAdmin($user) || $user->hasPermissionTo('Editar Usuários'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalSubmitActionLabel('Setar senha')
                ->modalCancelAction(fn (Action $action) => $action->label('Fechar'))
                ->modalHeading('Setar Senha Padrao')
                ->modalDescription('A senha será aplicada aos usuários selecionados. No próximo acesso, eles serão obrigados a cadastrar uma nova senha.')
                ->modalIcon('heroicon-o-key')
                ->schema(fn () => [
                    TextInput::make('nova_senha')
                        ->label('Nova senha')
                        ->default('Mudar@1234')
                        ->password()
                        ->revealable()
                        ->required()
                        ->rules([PasswordRule::min(8)->mixedCase()->numbers()->symbols()]),
                ])
                ->action(function ($records, array $data) use ($user) {
                    $senha = (string) ($data['nova_senha'] ?? '');
                    $afetados = 0;
                    $ignorados = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof User) {
                            continue;
                        }

                        if ($record->id === 1 || $record->id === $user->id || $record->hasRole('Admin')) {
                            $ignorados++;

                            continue;
                        }

                        $record->forceFill([
                            'password' => Hash::make($senha),
                            'must_change_password' => true,
                        ])->save();

                        $afetados++;
                    }

                    Notification::make()
                        ->title('Senha padrao aplicada')
                        ->body(
                            $ignorados > 0
                                ? "{$afetados} usuário(s) atualizados. {$ignorados} usuário(s) ignorado(s)."
                                : "{$afetados} usuário(s) atualizados. Eles deverão redefinir a senha no próximo acesso."
                        )
                        ->success()
                        ->send();
                }),

            Action::make('niveis_em_massa')
                ->label('Editar níveis')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->accessSelectedRecords()
                ->slideOver()
                ->visible(fn () => $user->hasPermissionTo('Aplicar Permissoes'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn (Action $action) => $action->label('Fechar'))
                ->modalHeading('Editar níveis em massa')
                ->modalDescription('Adicione, substitua ou remova níveis de acesso dos usuários selecionados.')
                ->modalIcon('heroicon-o-shield-check')
                ->schema(fn () => [
                    Select::make('modo_roles')
                        ->label('Como aplicar')
                        ->options([
                            'add' => 'Adicionar níveis',
                            'replace' => 'Substituir níveis atuais',
                            'remove' => 'Remover níveis selecionados',
                        ])
                        ->default('add')
                        ->selectablePlaceholder(false)
                        ->required(),

                    Select::make('roles')
                        ->label('Níveis de acesso')
                        ->helperText('Selecione um ou mais níveis para os usuários escolhidos.')
                        ->options(fn () => $service->opcoesDeRolesParaSelect($user))
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

                        $roleIdsAtuais = $record->roles()->pluck('id')->map(fn ($roleId) => (int) $roleId);

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
                        ->title('Níveis de acesso atualizados')
                        ->body("{$afetados} usuário(s) atualizados.")
                        ->success()
                        ->send();
                }),

            Action::make('permissoes_em_massa')
                ->label('Editar permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->accessSelectedRecords()
                ->slideOver()
                ->visible(fn () => $user->hasPermissionTo('Aplicar Permissoes'))
                ->closeModalByClickingAway(false)
                ->closeModalByEscaping(false)
                ->modalCloseButton(false)
                ->modalCancelAction(fn (Action $action) => $action->label('Fechar'))
                ->modalHeading('Editar permissões em massa')
                ->modalDescription('As permissões selecionadas serão aplicadas aos usuários escolhidos.')
                ->modalIcon('heroicon-o-key')
                ->schema(fn () => [
                    Select::make('modo_permissoes')
                        ->label('Como aplicar')
                        ->options([
                            'add' => 'Adicionar permissões',
                            'replace' => 'Substituir permissões diretas',
                            'remove' => 'Remover permissões diretas',
                        ])
                        ->default('add')
                        ->selectablePlaceholder(false)
                        ->required(),

                    Components\Section::make('Permissões')
                        ->collapsible()
                        ->schema(fn (Get $get) => [
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
                            ->flatMap(fn ($role) => $role->permissions->pluck('name'))
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
                        ->title('Permissões atualizadas')
                        ->body("{$afetados} usuário(s) atualizados.")
                        ->success()
                        ->send();
                }),

            VincularSetorBulkAction::make(
                permission: 'Editar Setor do Usuário',
                recordsLabel: 'usuários selecionados',
            ),

            DeleteBulkAction::make()
                ->before(function ($records, $action) use ($service, $user) {
                    if (! $service->podeDeletarEmLote($user, $records)) {
                        $action->halt();
                    }
                })
                ->visible(fn () => $service->ehAdmin(Auth::user())),
        ];
    }
}
