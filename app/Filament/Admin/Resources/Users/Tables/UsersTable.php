<?php

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Filament\Admin\Actions\VincularSetorBulkAction;
use App\Models\Servidor;
use App\Models\User;
use App\Services\PessoaAcessoService;
use App\Services\PessoaScopeService;
use App\Services\PessoaUsuarioService;
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
use Illuminate\Support\Facades\Gate;
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
                ->with([
                    'roles:id,name',
                    'escola:id,nome,setor_id',
                    'setor:id,nome',
                    'servidores:id,user_id,nome,cpf',
                    'professores:id,user_id,nome,ativo',
                ]))
            ->paginated([5, 10, 25, 50, 100])
            ->defaultPaginationPageOption(10)
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

            TextColumn::make('pessoa_vinculada')
                ->label('Pessoa')
                ->getStateUsing(function (User $record): string {
                    $nomes = $record->servidores->pluck('nome')->filter()->unique()->values();

                    if ($nomes->isEmpty()) {
                        $nomes = $record->professores->pluck('nome')->filter()->unique()->values();
                    }

                    return $nomes->implode(', ') ?: '—';
                })
                ->wrap()
                ->toggleable(),

            TextColumn::make('cargo_label')
                ->label('Cargo')
                ->badge()
                ->getStateUsing(fn (User $record): string => app(PessoaAcessoService::class)->usuarioEhProfessor($record)
                    ? 'Professor'
                    : '—')
                ->color(fn (string $state): string => $state === 'Professor' ? 'info' : 'gray')
                ->toggleable(),

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
                ->visible(fn () => Gate::allows('toggleEmailApproval', [User::class, null, 'table']))
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

            TernaryFilter::make('cargo_professor')
                ->label('Cargo professor')
                ->trueLabel('Professores')
                ->falseLabel('Sem cargo professor')
                ->placeholder('Todos')
                ->queries(
                    true: fn (Builder $query): Builder => $query->whereHas(
                        'professores',
                        fn (Builder $professores): Builder => $professores->where('ativo', true),
                    ),
                    false: fn (Builder $query): Builder => $query->whereDoesntHave(
                        'professores',
                        fn (Builder $professores): Builder => $professores->where('ativo', true),
                    ),
                    blank: fn (Builder $query): Builder => $query,
                ),

            TernaryFilter::make('sem_pessoa')
                ->label('Vínculo com Pessoa')
                ->trueLabel('Sem Pessoa vinculada')
                ->falseLabel('Com Pessoa vinculada')
                ->placeholder('Todos')
                ->queries(
                    true: fn (Builder $query): Builder => $query
                        ->whereDoesntHave('servidores')
                        ->whereDoesntHave('professores'),
                    false: fn (Builder $query): Builder => $query->where(function (Builder $vinculos): void {
                        $vinculos
                            ->whereHas('servidores')
                            ->orWhereHas('professores');
                    }),
                    blank: fn (Builder $query): Builder => $query,
                ),

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
                ->visible(fn (): bool => Gate::allows('toggleEmailApproval', [User::class, null, 'table'])),
        ];
    }

    // -------------------------------------------------------------------------
    // Ações de linha (record actions)
    // -------------------------------------------------------------------------

    private static function recordActions(UserService $service, User $user): array
    {
        return [

            Action::make('vincular_pessoa')
                ->label('Vincular à pessoa')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->visible(fn (User $record): bool => $record->servidores->isEmpty()
                    && $record->professores->isEmpty()
                    && Gate::forUser($user)->allows('update', $record)
                    && Gate::forUser($user)->allows('viewAny', Servidor::class))
                ->schema([
                    Select::make('pessoa_id')
                        ->label('Pessoa existente')
                        ->helperText('Somente pessoas sem conta e com o mesmo e-mail poderão ser vinculadas.')
                        ->options(function (User $record) use ($user): array {
                            $query = Servidor::query()
                                ->whereNull('user_id')
                                ->whereRaw('LOWER(email) = ?', [mb_strtolower((string) $record->email)]);

                            return app(PessoaScopeService::class)
                                ->applyPessoaScope($query, $user)
                                ->orderBy('nome')
                                ->pluck('nome', 'id')
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->required(),
                ])
                ->action(function (User $record, array $data) use ($user): void {
                    $pessoa = Servidor::query()->findOrFail((int) ($data['pessoa_id'] ?? 0));
                    app(PessoaUsuarioService::class)->vincularContaExistente($pessoa, $record, $user);

                    Notification::make()
                        ->title('Conta vinculada à pessoa')
                        ->success()
                        ->send();
                }),

            Action::make('permissoes')
                ->label('Permissões')
                ->icon('heroicon-o-key')
                ->color('warning')
                ->slideOver()
                ->modalSubmitActionLabel('Salvar')
                ->modalSubmitAction(fn (Action $action) => $action->color('primary'))
                ->visible(fn (User $record): bool => Gate::forUser($user)->allows('applyPermissions', $record))
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

            EditAction::make()
                ->visible(fn (User $record): bool => Gate::forUser($user)->allows('update', $record)),

            DeleteAction::make()
                ->label('Excluir')
                ->requiresConfirmation()
                ->modalHeading('Excluir usuário')
                ->modalDescription(fn (User $record): string => "Excluir a conta \"{$record->email}\"? A ficha em Pessoas e lotações de professor são mantidas; apenas o login é removido.")
                ->visible(fn (User $record): bool => $service->podeDeletar($user, $record))
                ->disabled(fn (User $record): bool => ($record->id === 1) || (Auth::id() === $record->id))
                ->using(function (User $record) use ($service, $user): void {
                    if (! $service->podeDeletar($user, $record)) {
                        Notification::make()
                            ->title('Exclusão não permitida')
                            ->danger()
                            ->send();

                        return;
                    }

                    $service->excluirUsuario($record);

                    Notification::make()
                        ->title('Usuário excluído')
                        ->success()
                        ->send();
                }),
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
                ->visible(fn () => Gate::allows('toggleEmailApproval', [User::class, null, 'table']))
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
                ->visible(fn (): bool => Gate::allows('resetPasswordAny', User::class))
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
                ->action(function ($records, array $data) use ($service, $user) {
                    $senha = (string) ($data['nova_senha'] ?? '');
                    $afetados = 0;
                    $ignorados = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof User) {
                            continue;
                        }

                        try {
                            $service->redefinirSenha($record, $senha, $user);
                            $afetados++;
                        } catch (\Illuminate\Auth\Access\AuthorizationException) {
                            $ignorados++;
                        }
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
                ->visible(fn (): bool => Gate::allows('applyPermissionsAny', User::class))
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

                        $service->sincronizarNiveisAdicionais(
                            $record,
                            $roleIds,
                            (string) $modo,
                            $user,
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
                ->visible(fn (): bool => Gate::allows('applyPermissionsAny', User::class))
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

                        $service->sincronizarPermissoesDiretas(
                            $record,
                            $permissoesSelecionadas,
                            (string) $modo,
                            $user,
                        );

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
                ability: 'editSetor',
                arguments: User::class,
                recordsLabel: 'usuários selecionados',
            ),

            DeleteBulkAction::make()
                ->label('Excluir selecionados')
                ->requiresConfirmation()
                ->modalDescription('Remove apenas as contas de login. Pessoas e professores vinculados permanecem no sistema.')
                ->visible(fn (): bool => $user->hasPermissionTo('Excluir Usuarios')
                    || $user->hasPermissionTo('Excluir Usuários')
                    || $service->ehAdmin($user))
                ->deselectRecordsAfterCompletion()
                ->using(function ($records) use ($service, $user): void {
                    $ok = 0;
                    $falha = 0;

                    foreach ($records as $record) {
                        if (! $record instanceof User || ! $service->podeDeletar($user, $record)) {
                            $falha++;

                            continue;
                        }

                        try {
                            $service->excluirUsuario($record);
                            $ok++;
                        } catch (\Throwable) {
                            $falha++;
                        }
                    }

                    Notification::make()
                        ->title('Exclusão em massa')
                        ->body("{$ok} excluído(s)".($falha > 0 ? ", {$falha} ignorado(s)." : '.'))
                        ->success()
                        ->send();
                }),
        ];
    }
}
